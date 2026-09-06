<?php

namespace App\Filament\System\Resources\PracticeResource\Pages;

use App\Filament\System\Resources\PracticeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPractices extends ListRecords
{
    protected static string $resource = PracticeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
