<?php

namespace App\Filament\Resources\ProcedureCodeResource\Pages;

use App\Filament\Resources\ProcedureCodeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProcedureCodes extends ListRecords
{
    protected static string $resource = ProcedureCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New Procedure Code')
                ->icon('heroicon-o-plus'),
        ];
    }
}
