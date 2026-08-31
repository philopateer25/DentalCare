<?php

namespace App\Filament\Resources\DentalLabResource\Pages;

use App\Filament\Resources\DentalLabResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDentalLab extends CreateRecord
{
    protected static string $resource = DentalLabResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
