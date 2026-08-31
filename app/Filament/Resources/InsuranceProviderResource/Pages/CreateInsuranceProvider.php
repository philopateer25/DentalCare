<?php

namespace App\Filament\Resources\InsuranceProviderResource\Pages;

use App\Filament\Resources\InsuranceProviderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateInsuranceProvider extends CreateRecord
{
    protected static string $resource = InsuranceProviderResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
