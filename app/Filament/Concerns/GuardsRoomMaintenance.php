<?php

namespace App\Filament\Concerns;

use App\Services\Scheduling\RoomAvailabilityService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;

/**
 * For Create/Edit appointment pages: blocks rooms closed for maintenance and
 * shows a confirmation pop-up when the room is operational but equipment is
 * missing. Pages implement resumeAfterMaintenanceAcknowledged() to re-run the
 * original create()/save() once the user confirms.
 */
trait GuardsRoomMaintenance
{
    public bool $maintenanceAcknowledged = false;

    public array $maintenanceWarnings = [];

    public ?string $maintenanceRoomName = null;

    abstract protected function resumeAfterMaintenanceAcknowledged(): void;

    /** @return bool true when the save may proceed */
    protected function passesMaintenanceGuard(array $data, ?int $ignoreAppointmentId = null): bool
    {
        if (empty($data['operatory_id']) || empty($data['start_time']) || empty($data['end_time'])) {
            return true;
        }

        $tenantId = Filament::getTenant()?->id ?? auth()->user()?->practice_id;
        $service = app(RoomAvailabilityService::class);
        $assessment = $service->assess(
            (int) $tenantId,
            (int) $data['operatory_id'],
            RoomAvailabilityService::parse($data['start_time']),
            RoomAvailabilityService::parse($data['end_time']),
            null,
            $ignoreAppointmentId,
        );

        if ($message = $service->closureMessage($assessment)) {
            throw ValidationException::withMessages(['data.operatory_id' => $message]);
        }

        if (! empty($assessment['warnings']) && ! $this->maintenanceAcknowledged) {
            $this->maintenanceWarnings = $assessment['warnings'];
            $this->maintenanceRoomName = $assessment['operatory_name'];
            $this->mountAction('maintenanceWarning');

            return false;
        }

        return true;
    }

    public function maintenanceWarningAction(): Action
    {
        return Action::make('maintenanceWarning')
            ->modalHeading(fn () => 'Something will be missing in '.($this->maintenanceRoomName ?? 'this room'))
            ->modalDescription('This room stays open, but maintenance is under way during the appointment time.')
            ->modalIcon('heroicon-o-exclamation-triangle')
            ->modalIconColor('warning')
            ->color('warning')
            ->modalContent(fn () => view('filament.components.maintenance-warning', ['warnings' => $this->maintenanceWarnings]))
            ->modalSubmitActionLabel('Book anyway')
            ->modalCancelActionLabel('Go back')
            ->action(function () {
                $this->maintenanceAcknowledged = true;
                $this->resumeAfterMaintenanceAcknowledged();
            });
    }
}
