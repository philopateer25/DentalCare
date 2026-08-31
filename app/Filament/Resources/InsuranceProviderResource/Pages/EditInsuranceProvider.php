<?php

namespace App\Filament\Resources\InsuranceProviderResource\Pages;

use App\Filament\Resources\InsuranceProviderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInsuranceProvider extends EditRecord
{
    protected static string $resource = InsuranceProviderResource::class;

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
