<?php

namespace App\Filament\Resources\DentalLabResource\Pages;

use App\Filament\Resources\DentalLabResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDentalLab extends EditRecord
{
    protected static string $resource = DentalLabResource::class;

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
