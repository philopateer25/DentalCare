<?php

namespace App\Filament\Resources\PatientInsurancePolicyResource\Pages;

use App\Filament\Resources\PatientInsurancePolicyResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePatientInsurancePolicy extends CreateRecord
{
    protected static string $resource = PatientInsurancePolicyResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
