<?php

namespace App\Filament\Resources\PatientInsurancePolicyResource\Pages;

use App\Filament\Resources\PatientInsurancePolicyResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPatientInsurancePolicy extends EditRecord
{
    protected static string $resource = PatientInsurancePolicyResource::class;

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
