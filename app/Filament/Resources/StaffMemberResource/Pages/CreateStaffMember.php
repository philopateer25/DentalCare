<?php

namespace App\Filament\Resources\StaffMemberResource\Pages;

use App\Filament\Resources\StaffMemberResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStaffMember extends CreateRecord
{
    protected static string $resource = StaffMemberResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['base_salary'] = !empty($data['base_salary']) ? (float)$data['base_salary'] : 0.00;
        $data['hourly_rate'] = !empty($data['hourly_rate']) ? (float)$data['hourly_rate'] : 0.00;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
