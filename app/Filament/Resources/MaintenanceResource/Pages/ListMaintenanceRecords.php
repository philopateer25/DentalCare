<?php

namespace App\Filament\Resources\MaintenanceResource\Pages;

use App\Filament\Resources\MaintenanceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMaintenanceRecords extends ListRecords
{
    protected static string $resource = MaintenanceResource::class;

    public function getSubheading(): ?string
    {
        return __('Track equipment repairs, room closures and partial outages that affect booking.');
    }

    protected function getHeaderWidgets(): array
    {
        return [
            MaintenanceResource\Widgets\MaintenanceStatsWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Log Maintenance')
                ->icon('heroicon-o-plus'),
        ];
    }
}
