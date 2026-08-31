<?php

namespace App\Filament\Resources\DoctorCommissionResource\Pages;

use App\Filament\Resources\DoctorCommissionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDoctorCommission extends EditRecord
{
    protected static string $resource = DoctorCommissionResource::class;

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
