<?php

namespace App\Filament\Resources\StaffMemberResource\Pages;

use App\Filament\Resources\StaffMemberResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStaffMember extends EditRecord
{
    protected static string $resource = StaffMemberResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['base_salary'] = !empty($data['base_salary']) ? (float)$data['base_salary'] : 0.00;
        $data['hourly_rate'] = !empty($data['hourly_rate']) ? (float)$data['hourly_rate'] : 0.00;

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
