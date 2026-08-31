<?php

namespace App\Filament\Resources\DentalLabResource\Pages;

use App\Filament\Resources\DentalLabResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDentalLabs extends ListRecords
{
    protected static string $resource = DentalLabResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New Lab Partner')
                ->icon('heroicon-o-plus'),
        ];
    }
}
