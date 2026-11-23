<?php

namespace App\Filament\Resources\OperatoryResource\Pages;

use App\Filament\Resources\OperatoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOperatories extends ListRecords
{
    protected static string $resource = OperatoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label(__('Add room')),
        ];
    }
}
