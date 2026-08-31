<?php

namespace App\Filament\Resources\InstallmentPlanResource\Pages;

use App\Filament\Resources\InstallmentPlanResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInstallmentPlans extends ListRecords
{
    protected static string $resource = InstallmentPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New Patient Financing Plan')
                ->icon('heroicon-o-plus'),
        ];
    }
}
