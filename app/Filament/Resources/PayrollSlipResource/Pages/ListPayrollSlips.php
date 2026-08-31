<?php

namespace App\Filament\Resources\PayrollSlipResource\Pages;

use App\Filament\Resources\PayrollSlipResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPayrollSlips extends ListRecords
{
    protected static string $resource = PayrollSlipResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Generate Salary Payslip')
                ->icon('heroicon-o-plus'),
        ];
    }
}
