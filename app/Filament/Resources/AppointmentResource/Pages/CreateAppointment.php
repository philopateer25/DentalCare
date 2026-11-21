<?php

namespace App\Filament\Resources\AppointmentResource\Pages;

use App\Filament\Concerns\GuardsRoomMaintenance;
use App\Filament\Resources\AppointmentResource;
use App\Models\Operatory;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateAppointment extends CreateRecord
{
    use GuardsRoomMaintenance;

    protected static string $resource = AppointmentResource::class;

    protected bool $pendingCreateAnother = false;

    public function create(bool $another = false): void
    {
        $this->pendingCreateAnother = $another;

        if (! $this->passesMaintenanceGuard($this->form->getState())) {
            return;
        }

        parent::create($another);
    }

    protected function resumeAfterMaintenanceAcknowledged(): void
    {
        $this->create($this->pendingCreateAnother);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenant = Filament::getTenant();

        $data['practice_id'] = $tenant->id;

        // Find a branch if not provided (e.g. from operatory or default to first branch)
        if (! isset($data['branch_id'])) {
            if (isset($data['operatory_id'])) {
                $operatory = Operatory::find($data['operatory_id']);
                $data['branch_id'] = $operatory?->branch_id ?? $tenant->branches()->first()?->id;
            } else {
                $data['branch_id'] = $tenant->branches()->first()?->id;
            }
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
