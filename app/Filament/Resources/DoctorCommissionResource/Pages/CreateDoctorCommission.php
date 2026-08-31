<?php

namespace App\Filament\Resources\DoctorCommissionResource\Pages;

use App\Filament\Resources\DoctorCommissionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDoctorCommission extends CreateRecord
{
    protected static string $resource = DoctorCommissionResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
