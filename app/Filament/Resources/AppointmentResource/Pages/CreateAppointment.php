<?php

namespace App\Filament\Resources\AppointmentResource\Pages;

use App\Filament\Resources\AppointmentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAppointment extends CreateRecord
{
    protected static string $resource = AppointmentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenant = \Filament\Facades\Filament::getTenant();
        
        $data['practice_id'] = $tenant->id;
        
        // Find a branch if not provided (e.g. from operatory or default to first branch)
        if (!isset($data['branch_id'])) {
            if (isset($data['operatory_id'])) {
                $operatory = \App\Models\Operatory::find($data['operatory_id']);
                $data['branch_id'] = $operatory?->branch_id ?? $tenant->branches()->first()?->id;
            } else {
                $data['branch_id'] = $tenant->branches()->first()?->id;
            }
        }

        return $data;
    }
}
