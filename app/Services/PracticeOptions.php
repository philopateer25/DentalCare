<?php

namespace App\Services;

use App\Models\PracticeOption;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;

/**
 * Phase 5 — "I don't have time to type".
 *
 * Every clinic keeps its own list per field (medications, dosages, conditions,
 * expense categories, note phrases…). Anything typed once is remembered and the
 * most-used values come first, so next time it's one tap. Built-in defaults
 * fill the list for a new clinic.
 */
class PracticeOptions
{
    /** Values used in this many days rank above older favourites. */
    public const RECENT_DAYS = 90;

    public const MAX_LENGTH = 255;

    /** Built-in starting values per list. Keys may map to a nicer label. */
    public static function defaults(string $list): array
    {
        return match ($list) {
            'medication' => ['Amoxicillin 500mg', 'Amoxicillin + Clavulanic acid 1g', 'Clindamycin 300mg', 'Metronidazole 500mg', 'Azithromycin 500mg', 'Ibuprofen 400mg', 'Ibuprofen 600mg', 'Paracetamol 500mg', 'Diclofenac 50mg', 'Ketorolac 10mg', 'Chlorhexidine mouthwash 0.12%', 'Benzydamine mouthwash'],
            'rx_dosage' => ['1 tablet', '2 tablets', '1 capsule', '500mg', '1g', '10 ml rinse'],
            'rx_frequency' => ['Every 8 hours', 'Every 12 hours', 'Every 6 hours', 'Once daily', 'Twice daily', 'As needed for pain'],
            'rx_duration' => ['3 days', '5 days', '7 days', '10 days', '14 days', 'Until finished'],
            'medical_condition' => ['Hypertension', 'Diabetes', 'Asthma', 'Hepatitis C', 'Thyroid disorder', 'Kidney disease', 'Epilepsy', 'Anemia'],
            'active_medication' => ['Aspirin', 'Warfarin', 'Metformin', 'Insulin', 'Amlodipine', 'Thyroxine', 'Atorvastatin'],
            'cardiac_condition' => ClinicalPresets::cardiacConditions(),
            'diabetes_medication' => ClinicalPresets::diabetesMedications(),
            'tool' => ClinicalPresets::toolsAndEquipment(),
            'material' => ClinicalPresets::clinicalMaterials(),
            'procedure_note' => ['Well tolerated', 'No complications', 'Post-op instructions given', 'Patient to return in 1 week', 'Temporary filling placed', 'Rubber dam used', 'Sensitivity explained', 'Review X-ray next visit'],
            'expense_category' => [
                'Clinical Supplies & Materials' => 'Clinical Supplies & Consumables',
                'Dental Lab Fees' => 'Dental Lab Production Invoices',
                'Staff Payroll & Salaries' => 'Staff Salaries & Hygienist Payroll',
                'Facility Rent & Lease' => 'Clinic Facility Rent & Lease',
                'Utilities & Electricity' => 'Utilities (Electricity, Water, Waste Disposal)',
                'Equipment Lease & Maintenance' => 'Dental Equipment Maintenance & Sterilizer Service',
                'Marketing & Patient Acquisition' => 'Marketing, Google Ads & Social Media',
                'Software & Technology' => 'PMS Software, AI Diagnostics & Cloud Storage',
                'Insurance & Licensing' => 'Malpractice & Clinic Liability Insurance',
                'Taxes & Accounting' => 'Taxes, CPA & Legal Fees',
                'Miscellaneous' => 'General Office Supplies & Misc',
            ],
            default => [],
        };
    }

    /**
     * value => label for a list: the clinic's own values (most used first),
     * then the defaults it hasn't used yet.
     *
     * @return array<string, string>
     */
    public function options(string $list, ?int $practiceId = null, int $limit = 60): array
    {
        $defaults = self::assoc(self::defaults($list));
        $practiceId ??= $this->currentPracticeId();

        $own = $practiceId === null ? collect() : PracticeOption::query()
            ->where('practice_id', $practiceId)
            ->where('list', $list)
            // Recent favourites first, then all-time count, then alphabetical.
            ->orderByRaw('CASE WHEN last_used_at >= ? THEN 0 ELSE 1 END', [now()->subDays(self::RECENT_DAYS)])
            ->orderByDesc('use_count')
            ->orderBy('value')
            ->limit($limit)
            ->pluck('value');

        $result = [];
        foreach ($own as $value) {
            $result[$value] = $defaults[$value] ?? $value;
        }
        foreach ($defaults as $value => $label) {
            if (count($result) >= $limit) {
                break;
            }
            $result[$value] ??= $label;
        }

        return $result;
    }

    /**
     * Search the list; offers the typed text itself as a new value when it
     * isn't there yet.
     *
     * @return array<string, string>
     */
    public function search(string $list, string $search, ?int $practiceId = null): array
    {
        $needle = self::clean($search);
        if ($needle === '') {
            return $this->options($list, $practiceId);
        }

        $all = $this->options($list, $practiceId, 500);
        $lower = mb_strtolower($needle);
        $matches = array_filter($all, fn ($label, $value) => str_contains(mb_strtolower($value), $lower) || str_contains(mb_strtolower($label), $lower), ARRAY_FILTER_USE_BOTH);

        $exact = array_filter(array_keys($all), fn ($v) => mb_strtolower($v) === $lower);
        if ($exact === []) {
            $matches = [$needle => __('Add “:value”', ['value' => $needle])] + $matches;
        }

        return array_slice($matches, 0, 50, true);
    }

    /**
     * Count a use of one or more values (adds new ones to the clinic's list).
     *
     * @param  string|list<string>|null  $values
     */
    public function record(string $list, string|array|null $values, ?int $practiceId = null): void
    {
        $practiceId ??= $this->currentPracticeId();
        if ($practiceId === null || $values === null) {
            return;
        }

        $values = array_unique(array_filter(array_map(fn ($v) => self::clean((string) $v), (array) $values)));

        foreach ($values as $value) {
            DB::transaction(function () use ($practiceId, $list, $value) {
                // Same text with different capitals counts as the same entry.
                $option = PracticeOption::query()
                    ->where('practice_id', $practiceId)
                    ->where('list', $list)
                    ->whereRaw('LOWER(value) = ?', [mb_strtolower($value)])
                    ->lockForUpdate()
                    ->first();

                if ($option) {
                    $option->increment('use_count', 1, ['last_used_at' => now()]);
                } else {
                    PracticeOption::create([
                        'practice_id' => $practiceId,
                        'list' => $list,
                        'value' => $value,
                        'use_count' => 1,
                        'last_used_at' => now(),
                    ]);
                }
            });
        }
    }

    /** Remove a value from a clinic's list (e.g. a typo). Defaults can't be removed. */
    public function forget(string $list, string $value, int $practiceId): void
    {
        PracticeOption::query()
            ->where('practice_id', $practiceId)
            ->where('list', $list)
            ->where('value', $value)
            ->delete();
    }

    public static function clean(string $value): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        return mb_substr($value, 0, self::MAX_LENGTH);
    }

    /** @return array<string, string> */
    private static function assoc(array $values): array
    {
        return array_is_list($values) ? array_combine($values, $values) : $values;
    }

    private function currentPracticeId(): ?int
    {
        $id = Filament::getTenant()?->id ?? auth()->user()?->practice_id;

        return $id === null ? null : (int) $id;
    }
}
