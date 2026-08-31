<?php

namespace App\Filament\Resources\ClinicExpenseResource\Pages;

use App\Filament\Resources\ClinicExpenseResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListClinicExpenses extends ListRecords
{
    protected static string $resource = ClinicExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Record Operating Expense')
                ->icon('heroicon-o-plus'),
        ];
    }
}
