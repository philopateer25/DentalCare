<?php

namespace App\Filament\Resources\InsuranceProviderResource\Pages;

use App\Filament\Resources\InsuranceProviderResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInsuranceProviders extends ListRecords
{
    protected static string $resource = InsuranceProviderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New Insurance Provider')
                ->icon('heroicon-o-plus'),
        ];
    }
}
