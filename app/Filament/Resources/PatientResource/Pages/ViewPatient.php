<?php

namespace App\Filament\Resources\PatientResource\Pages;

use App\Filament\Resources\PatientResource;
use App\Models\PatientFile;
use App\Services\PaperRecords\PaperCardScan;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewPatient extends ViewRecord
{
    protected static string $resource = PatientResource::class;

    /** Files tab index in PatientResource::getRelations(). */
    public const FILES_TAB = 3;

    /** The open "Scan paper card" session: url, path, expires (strings). */
    public ?array $scanSession = null;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        // "New from paper card" lands here with ?scan=1 → open the QR straight away.
        if (request()->boolean('scan')) {
            $this->defaultAction = 'scanPaperCard';
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('scanPaperCard')
                ->label(__('Scan paper card'))
                ->icon('heroicon-o-camera')
                ->color('gray')
                ->modalHeading(__('Scan the paper card with your phone'))
                ->modalWidth('md')
                ->mountUsing(function () {
                    $scan = app(PaperCardScan::class)->start($this->record, (int) auth()->id());
                    $this->scanSession = [
                        'url' => $scan['url'],
                        'path' => $scan['path'],
                        'expires' => $scan['expires_at']->timezone(config('app.timezone'))->format('H:i'),
                        'started' => now()->subSeconds(5)->toIso8601String(),
                    ];
                })
                ->modalContent(fn () => view('filament.patients.scan-paper-card', ['scan' => $this->scanSession, 'page' => $this]))
                ->modalSubmitActionLabel(__('Done — show the photos'))
                ->modalCancelActionLabel(__('Close'))
                ->action(fn () => $this->redirect(PatientResource::getUrl('view', ['record' => $this->record]).'?activeRelationManager='.self::FILES_TAB)),
        ];
    }

    /** Photos received so far in the open scan session (polled by the modal). */
    public function paperCardsReceived(): int
    {
        if (! $this->scanSession) {
            return 0;
        }

        return PatientFile::query()
            ->where('patient_id', $this->record->id)
            ->where('type', PaperCardScan::FILE_TYPE)
            ->where('created_at', '>=', $this->scanSession['started'])
            ->count();
    }

    protected function getHeaderWidgets(): array
    {
        return [
            // Stitch 04-patient-profile header + overview. It includes the medical alerts,
            // so the older PatientAlertWidget banner is no longer listed here (class kept).
            PatientResource\Widgets\PatientProfileHeaderWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    public function getHeading(): string
    {
        return '';
    }
}
