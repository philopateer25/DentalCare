<?php

namespace App\Filament\Resources\ClinicExpenseResource\Pages;

use App\Filament\Resources\ClinicExpenseResource;
use Filament\Resources\Pages\CreateRecord;

class CreateClinicExpense extends CreateRecord
{
    protected static string $resource = ClinicExpenseResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['logged_by_user_id'] = auth()->id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
