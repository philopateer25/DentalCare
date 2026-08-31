<?php

namespace App\Filament\Resources\LabOrderResource\Pages;

use App\Filament\Resources\LabOrderResource;
use App\Filament\Resources\LabOrderResource\Widgets\LabStatsOverview;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLabOrders extends ListRecords
{
    protected static string $resource = LabOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New Lab Rx / Prescription')
                ->icon('heroicon-o-plus'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            LabStatsOverview::class,
        ];
    }
}
