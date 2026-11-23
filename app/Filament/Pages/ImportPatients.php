<?php

namespace App\Filament\Pages;

use App\Services\PaperRecords\PatientImport;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Phase 4 — import patients and what they owe from an Excel/CSV list.
 * Two steps: "Check file" (nothing saved, every row's problems listed)
 * then "Import N good rows".
 */
class ImportPatients extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?string $navigationGroup = 'Clinical Management';

    protected static ?string $navigationLabel = 'Import patients';

    protected static ?string $title = 'Import patients from paper / Excel';

    protected static ?string $slug = 'import-patients';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.import-patients';

    /** @var array<string, mixed> */
    public ?array $data = [];

    /** Cache key of the last checked file (the full rows stay server-side). */
    public ?string $checkKey = null;

    /** @var array{total: int, ok: int, bad: int, owed: float, plans: int}|null */
    public ?array $summary = null;

    /** @var list<array{line: int, name: string, phone: string, errors: list<string>}> */
    public array $problems = [];

    /** @var array{patients: int, invoices: int, plans: int, owed: float}|null */
    public ?array $result = null;

    public ?string $fileError = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(['super_admin', 'clinic_admin', 'secretary', 'developer']);
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                FileUpload::make('file')
                    ->label(__('Excel or CSV file'))
                    ->disk('local')
                    ->directory('imports')
                    ->visibility('private')
                    ->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel',
                    ])
                    ->storeFileNamesIn('file_name')
                    ->maxSize(10240)
                    ->required(),
            ])
            ->statePath('data');
    }

    public function checkFile(PatientImport $import): void
    {
        $this->reset(['summary', 'problems', 'result', 'fileError', 'checkKey']);
        $data = $this->form->getState();
        $stored = (string) ($data['file'] ?? '');

        if ($stored === '' || ! Storage::disk('local')->exists($stored)) {
            $this->fileError = __('Upload the file first.');

            return;
        }

        $read = $import->read(Storage::disk('local')->path($stored), $data['file_name'] ?? $stored);
        Storage::disk('local')->delete($stored);
        $this->form->fill();

        if ($read['error']) {
            $this->fileError = $read['error'];

            return;
        }

        $checked = $import->check($read['rows'], $this->practiceId());
        $good = array_values(array_filter($checked, fn ($r) => $r['ok']));

        $this->checkKey = 'patient-import:'.auth()->id().':'.Str::uuid();
        Cache::put($this->checkKey, ['practice_id' => $this->practiceId(), 'rows' => $checked], now()->addHour());

        $this->summary = [
            'total' => count($checked),
            'ok' => count($good),
            'bad' => count($checked) - count($good),
            'owed' => round(array_sum(array_map(fn ($r) => $r['data']['owed'], $good)), 2),
            'plans' => count(array_filter($good, fn ($r) => $r['data']['installments'])),
        ];
        $this->problems = array_map(
            fn ($r) => ['line' => $r['line'], 'name' => $r['name'], 'phone' => $r['phone'], 'errors' => $r['errors']],
            array_values(array_filter($checked, fn ($r) => ! $r['ok'])),
        );
    }

    public function importRows(PatientImport $import): void
    {
        $cached = $this->checkKey ? Cache::pull($this->checkKey) : null;

        if (! is_array($cached) || (int) $cached['practice_id'] !== $this->practiceId()) {
            Notification::make()->title(__('Please check the file again.'))->warning()->send();
            $this->reset(['summary', 'problems', 'checkKey']);

            return;
        }

        $this->result = $import->import($cached['rows'], $this->practiceId());
        $this->reset(['summary', 'problems', 'checkKey']);

        Notification::make()
            ->title(__(':count patients imported', ['count' => $this->result['patients']]))
            ->success()
            ->send();
    }

    public function startOver(): void
    {
        if ($this->checkKey) {
            Cache::forget($this->checkKey);
        }
        $this->reset(['summary', 'problems', 'result', 'fileError', 'checkKey']);
        $this->form->fill();
    }

    private function practiceId(): int
    {
        return (int) (Filament::getTenant()?->id ?? auth()->user()->practice_id);
    }
}
