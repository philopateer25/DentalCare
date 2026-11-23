<?php

namespace App\Services\PaperRecords;

use App\Models\InstallmentPlan;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Patient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

/**
 * Phase 4 — "start from today, keep your notebooks".
 *
 * Reads an Excel/CSV list of patients (Arabic or English headers), checks every
 * row, and imports the good ones: the patient, an opening-balance invoice for
 * what they owe, and an optional installment plan. Always scoped to one practice.
 */
class PatientImport
{
    public const MAX_ROWS = 2000;

    /** Column => accepted header spellings (lower-case, Arabic and English). */
    public const HEADERS = [
        'name' => ['name', 'full name', 'patient', 'patient name', 'الاسم', 'اسم المريض', 'الإسم'],
        'phone' => ['phone', 'mobile', 'phone number', 'mobile number', 'الموبايل', 'الهاتف', 'رقم الموبايل', 'التليفون', 'رقم الهاتف', 'تليفون', 'موبايل'],
        'gender' => ['gender', 'sex', 'النوع', 'الجنس'],
        'age' => ['age', 'السن', 'العمر'],
        'owed' => ['amount owed', 'owed', 'balance', 'remaining', 'المبلغ المستحق', 'المتبقي', 'المبلغ المتبقي', 'عليه', 'الباقي'],
        'installments' => ['installments', 'number of installments', 'عدد الأقساط', 'الأقساط', 'عدد الاقساط'],
        'first_due' => ['first installment date', 'first due date', 'first installment', 'تاريخ أول قسط', 'تاريخ اول قسط', 'أول قسط'],
        'frequency' => ['frequency', 'every', 'كل', 'التكرار'],
        'notes' => ['notes', 'note', 'ملاحظات', 'ملاحظة'],
    ];

    /** The template's header row: English / Arabic. */
    public const TEMPLATE_HEADERS = [
        'Name / الاسم', 'Phone / الموبايل', 'Gender / النوع', 'Age / السن', 'Amount owed / المبلغ المستحق',
        'Installments / عدد الأقساط', 'First installment date / تاريخ أول قسط', 'Frequency / التكرار', 'Notes / ملاحظات',
    ];

    // ── Reading ─────────────────────────────────

    /**
     * @return array{rows: list<array<string, string>>, missing: list<string>, error: ?string}
     *                                                                                         rows keyed by column name, plus `_line` (spreadsheet row number)
     */
    public function read(string $path, ?string $originalName = null): array
    {
        $ext = strtolower(pathinfo($originalName ?? $path, PATHINFO_EXTENSION));

        if (! in_array($ext, ['xlsx', 'csv', 'txt'], true)) {
            return ['rows' => [], 'missing' => [], 'error' => __('Please upload an Excel (.xlsx) or CSV file.')];
        }

        $reader = $ext === 'xlsx' ? new XlsxReader : new CsvReader;

        try {
            $reader->open($path);
        } catch (\Throwable) {
            return ['rows' => [], 'missing' => [], 'error' => __('The file could not be read. Save it again as .xlsx and retry.')];
        }

        $map = null;
        $rows = [];
        $line = 0;

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $line++;
                    $cells = array_map(fn ($c) => $this->cellToString($c), $row->toArray());

                    if ($map === null) {
                        if (array_filter($cells, fn ($c) => $c !== '') === []) {
                            continue; // blank lines above the header
                        }
                        $map = $this->mapHeaders($cells);

                        continue;
                    }

                    if (array_filter($cells, fn ($c) => $c !== '') === []) {
                        continue;
                    }

                    $record = ['_line' => (string) $line];
                    foreach ($map as $index => $column) {
                        $record[$column] = $cells[$index] ?? '';
                    }
                    $rows[] = $record;

                    if (count($rows) > self::MAX_ROWS) {
                        return ['rows' => [], 'missing' => [], 'error' => __('Too many rows (more than :max). Split the file.', ['max' => self::MAX_ROWS])];
                    }
                }
                break; // first sheet only
            }
        } finally {
            $reader->close();
        }

        $missing = array_values(array_diff(['name', 'phone'], array_values($map ?? [])));

        return [
            'rows' => $missing === [] ? $rows : [],
            'missing' => $missing,
            'error' => $map === null ? __('The file is empty.') : ($missing !== [] ? __('Missing column(s): :cols. Use the template.', ['cols' => implode(', ', $missing)]) : null),
        ];
    }

    /** @return array<int, string> cell index => column key */
    private function mapHeaders(array $cells): array
    {
        $map = [];
        foreach ($cells as $i => $header) {
            // "Name / الاسم" → try each part.
            $parts = array_map(fn ($p) => mb_strtolower(trim($p)), preg_split('#[/|]#u', $header) ?: [$header]);
            foreach (self::HEADERS as $key => $names) {
                if (array_intersect($parts, $names) !== [] && ! in_array($key, $map, true)) {
                    $map[$i] = $key;
                    break;
                }
            }
        }

        return $map;
    }

    private function cellToString(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (is_float($value) && floor($value) === $value) {
            return (string) (int) $value;
        }

        return trim((string) $value);
    }

    // ── Checking ────────────────────────────────

    /**
     * Check every row. Each result has `ok`, `errors` and, when ok, `data`
     * (normalised values ready to import).
     *
     * @param  list<array<string, string>>  $rows
     * @return list<array{line: int, name: string, phone: string, owed: string, ok: bool, errors: list<string>, data: ?array}>
     */
    public function check(array $rows, int $practiceId): array
    {
        $phones = array_filter(array_map(fn ($r) => self::normalisePhone($r['phone'] ?? ''), $rows));
        $existing = Patient::query()
            ->where('practice_id', $practiceId)
            ->whereIn('phone', $phones)
            ->get(['phone', 'first_name', 'last_name', 'file_number'])
            ->keyBy('phone');

        $seen = [];
        $results = [];

        foreach ($rows as $row) {
            $errors = [];
            $name = preg_replace('/\s+/u', ' ', trim($row['name'] ?? '')) ?? '';
            $phone = self::normalisePhone($row['phone'] ?? '');

            if ($name === '') {
                $errors[] = __('Name is missing.');
            } elseif (mb_strlen($name) > 120) {
                $errors[] = __('Name is too long.');
            }

            if (trim($row['phone'] ?? '') === '') {
                $errors[] = __('Phone is missing.');
            } elseif ($phone === null) {
                $errors[] = __('Phone ":phone" is not a valid number.', ['phone' => $row['phone']]);
            } elseif (isset($existing[$phone])) {
                $p = $existing[$phone];
                $errors[] = __('Already a patient: :name (:file).', ['name' => trim($p->first_name.' '.$p->last_name), 'file' => $p->file_number]);
            } elseif (isset($seen[$phone])) {
                $errors[] = __('Same phone as row :line.', ['line' => $seen[$phone]]);
            }

            $gender = self::gender($row['gender'] ?? '');
            if ($gender === false) {
                $errors[] = __('Gender ":value" not understood (use male/female or ذكر/أنثى).', ['value' => $row['gender']]);
            }

            $age = null;
            if (trim($row['age'] ?? '') !== '') {
                $age = self::number($row['age']);
                if ($age === null || $age < 0 || $age > 120 || floor($age) !== $age) {
                    $errors[] = __('Age ":value" is not a whole number between 0 and 120.', ['value' => $row['age']]);
                    $age = null;
                }
            }

            $owed = 0.0;
            if (trim($row['owed'] ?? '') !== '') {
                $owed = self::number($row['owed']);
                if ($owed === null || $owed < 0) {
                    $errors[] = __('Amount owed ":value" is not a number.', ['value' => $row['owed']]);
                    $owed = 0.0;
                }
            }

            $installments = null;
            if (trim($row['installments'] ?? '') !== '') {
                $n = self::number($row['installments']);
                if ($n === null || $n < 1 || $n > 60 || floor($n) !== $n) {
                    $errors[] = __('Installments must be a whole number from 1 to 60.');
                } elseif ($owed <= 0) {
                    $errors[] = __('Installments need an amount owed.');
                } else {
                    $installments = (int) $n;
                }
            }

            $firstDue = null;
            if (trim($row['first_due'] ?? '') !== '') {
                $firstDue = self::date($row['first_due']);
                if ($firstDue === null) {
                    $errors[] = __('First installment date ":value" not understood (use 2026-12-01 or 1/12/2026).', ['value' => $row['first_due']]);
                }
            }

            $frequency = self::frequency($row['frequency'] ?? '');
            if ($frequency === null) {
                $errors[] = __('Frequency ":value" not understood (monthly / weekly).', ['value' => $row['frequency']]);
            }

            if ($phone !== null && ! isset($seen[$phone])) {
                $seen[$phone] = (int) $row['_line'];
            }

            [$first, $last] = array_pad(explode(' ', $name, 2), 2, '');

            $results[] = [
                'line' => (int) $row['_line'],
                'name' => $name,
                'phone' => $phone ?? (string) ($row['phone'] ?? ''),
                'owed' => $owed > 0 ? number_format($owed, 2, '.', '') : '',
                'ok' => $errors === [],
                'errors' => $errors,
                'data' => $errors === [] ? [
                    'first_name' => $first,
                    'last_name' => $last,
                    'phone' => $phone,
                    'gender' => $gender ?: 'other',
                    'age' => $age === null ? null : (int) $age,
                    'owed' => round($owed, 2),
                    'installments' => $installments,
                    'first_due' => $firstDue?->toDateString(),
                    'frequency' => $frequency,
                    'notes' => mb_substr(trim($row['notes'] ?? ''), 0, 1000),
                ] : null,
            ];
        }

        return $results;
    }

    // ── Importing ───────────────────────────────

    /**
     * Import checked rows (only `ok` ones). Returns counts.
     *
     * @param  list<array{ok: bool, data: ?array}>  $checked
     * @return array{patients: int, invoices: int, plans: int, owed: float}
     */
    public function import(array $checked, int $practiceId): array
    {
        $counts = ['patients' => 0, 'invoices' => 0, 'plans' => 0, 'owed' => 0.0];

        DB::transaction(function () use ($checked, $practiceId, &$counts) {
            foreach ($checked as $row) {
                if (! $row['ok'] || ! is_array($row['data'])) {
                    continue;
                }
                $d = $row['data'];

                // A phone can be taken between "Check" and "Import" — skip quietly.
                if (Patient::where('practice_id', $practiceId)->where('phone', $d['phone'])->exists()) {
                    continue;
                }

                $patient = Patient::create([
                    'practice_id' => $practiceId,
                    'first_name' => $d['first_name'],
                    'last_name' => $d['last_name'],
                    'phone' => $d['phone'],
                    'whatsapp_number' => str_starts_with($d['phone'], '01') ? $d['phone'] : null,
                    'gender' => $d['gender'],
                    // Only the age is known: 1 July of the birth year.
                    'dob' => $d['age'] === null ? null : Carbon::create(now()->year - $d['age'], 7, 1)->toDateString(),
                    'status' => 'active',
                ]);
                $counts['patients']++;

                if ($d['owed'] <= 0) {
                    continue;
                }

                $invoice = Invoice::create([
                    'practice_id' => $practiceId,
                    'patient_id' => $patient->id,
                    'subtotal' => $d['owed'],
                    'discount_amount' => 0,
                    'tax_amount' => 0,
                    'total_amount' => $d['owed'],
                    'paid_amount' => 0,
                    'balance_due' => $d['owed'],
                    'status' => 'unpaid',
                    'issue_date' => now()->toDateString(),
                    'notes' => trim(__('Opening balance from paper records.').' '.$d['notes']),
                ]);
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'description' => __('Opening balance (paper records)'),
                    'quantity' => 1,
                    'unit_price' => $d['owed'],
                    'total_price' => $d['owed'],
                ]);
                $counts['invoices']++;
                $counts['owed'] += $d['owed'];

                if ($d['installments']) {
                    $plan = InstallmentPlan::create([
                        'invoice_id' => $invoice->id,
                        'total_funded_amount' => $d['owed'],
                        'down_payment' => 0,
                        'number_of_installments' => $d['installments'],
                        'frequency' => $d['frequency'],
                        'status' => 'active',
                        'notes' => __('Imported from paper records.'),
                    ]);
                    $plan->generateSchedules($d['first_due'] ? Carbon::parse($d['first_due']) : now()->addMonth()->startOfDay());
                    $counts['plans']++;
                }
            }
        });

        $counts['owed'] = round($counts['owed'], 2);

        return $counts;
    }

    // ── Template ────────────────────────────────

    public function writeTemplate(string $path): void
    {
        $writer = new XlsxWriter;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(self::TEMPLATE_HEADERS));
        $writer->addRow(Row::fromValues(['محمد أحمد', '01012345678', 'ذكر', 45, 1500, 3, now()->addMonth()->startOfMonth()->format('Y-m-d'), 'شهري', 'تقويم - باقي الأقساط']));
        $writer->addRow(Row::fromValues(['Mona Adel', '01198765432', 'female', 30, 0, '', '', '', '']));
        $writer->close();
    }

    // ── Value helpers ───────────────────────────

    /** Arabic-Indic / Persian digits → 0-9. */
    public static function latinDigits(string $value): string
    {
        return strtr($value, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);
    }

    /** Egyptian mobiles become 01XXXXXXXXX; other numbers keep their digits (7–15). */
    public static function normalisePhone(string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', self::latinDigits($value)) ?? '';

        if (preg_match('/^(?:0020|20)?(1[0125]\d{8})$/', $digits, $m)) {
            return '0'.$m[1];
        }
        if (preg_match('/^01[0125]\d{8}$/', $digits)) {
            return $digits;
        }
        // Spreadsheets drop the leading zero of 1012345678.
        if (preg_match('/^1[0125]\d{8}$/', $digits)) {
            return '0'.$digits;
        }

        return strlen($digits) >= 7 && strlen($digits) <= 15 ? $digits : null;
    }

    public static function number(string $value): ?float
    {
        $clean = str_replace([',', '٬', '،', ' ', 'EGP', 'egp', 'جنيه', 'ج.م', 'ج'], '', self::latinDigits(trim($value)));
        $clean = str_replace('٫', '.', $clean);

        return is_numeric($clean) ? (float) $clean : null;
    }

    /** @return 'male'|'female'|null|false null = blank, false = not understood */
    public static function gender(string $value): string|null|false
    {
        $v = mb_strtolower(trim($value));

        return match (true) {
            $v === '' => null,
            in_array($v, ['m', 'male', 'man', 'ذكر', 'ذ', 'رجل', 'راجل'], true) => 'male',
            in_array($v, ['f', 'female', 'woman', 'أنثى', 'انثى', 'انثي', 'أنثي', 'ا', 'ست', 'سيدة'], true) => 'female',
            default => false,
        };
    }

    public static function frequency(string $value): ?string
    {
        $v = mb_strtolower(trim($value));

        return match (true) {
            $v === '' || in_array($v, ['monthly', 'month', 'شهري', 'شهريا', 'شهرياً', 'شهر'], true) => 'monthly',
            in_array($v, ['weekly', 'week', 'أسبوعي', 'اسبوعي', 'أسبوعيا', 'اسبوع'], true) => 'weekly',
            in_array($v, ['bi_weekly', 'biweekly', 'every 2 weeks', 'كل أسبوعين', 'كل اسبوعين'], true) => 'bi_weekly',
            default => null,
        };
    }

    public static function date(string $value): ?Carbon
    {
        $v = self::latinDigits(trim($value));

        // 2026-12-01 / 2026/12/1, or day first as written in Egypt: 1/12/2026, 01-12-26.
        if (preg_match('#^(\d{4})[-/.](\d{1,2})[-/.](\d{1,2})$#', $v, $m)) {
            [$y, $mo, $d] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('#^(\d{1,2})[-/.](\d{1,2})[-/.](\d{2}|\d{4})$#', $v, $m)) {
            [$d, $mo, $y] = [(int) $m[1], (int) $m[2], (int) $m[3]];
            $y = $y < 100 ? 2000 + $y : $y;
        }

        if (isset($y, $mo, $d)) {
            return checkdate($mo, $d, $y) ? Carbon::create($y, $mo, $d)->startOfDay() : null;
        }

        // Excel serial date (e.g. 46357).
        if (preg_match('/^\d{5}$/', $v)) {
            return Carbon::create(1899, 12, 30)->addDays((int) $v);
        }

        return null;
    }
}
