<?php

namespace App\Filament\Resources\DoctorCommissionResource\Pages;

use App\Filament\Resources\DoctorCommissionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDoctorCommissions extends ListRecords
{
    protected static string $resource = DoctorCommissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Record Doctor Commission')
                ->icon('heroicon-o-plus'),
        ];
    }
}
