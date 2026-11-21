<?php

namespace App\Filament\Resources\AppointmentResource\Pages;

use App\Filament\Concerns\GuardsRoomMaintenance;
use App\Filament\Resources\AppointmentResource;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAppointment extends EditRecord
{
    use GuardsRoomMaintenance;

    protected static string $resource = AppointmentResource::class;

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        $data = $this->form->getState();

        // Only re-check maintenance when the room or time actually changes.
        if ($this->slotChanged($data) && ! $this->passesMaintenanceGuard($data, $this->record->id)) {
            return;
        }

        parent::save($shouldRedirect, $shouldSendSavedNotification);
    }

    protected function resumeAfterMaintenanceAcknowledged(): void
    {
        $this->save();
    }

    protected function slotChanged(array $data): bool
    {
        $same = fn ($a, $b) => $a && $b && Carbon::parse($a)->equalTo(Carbon::parse($b));

        return (int) ($data['operatory_id'] ?? 0) !== (int) $this->record->operatory_id
            || ! $same($data['start_time'] ?? null, $this->record->start_time)
            || ! $same($data['end_time'] ?? null, $this->record->end_time);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
