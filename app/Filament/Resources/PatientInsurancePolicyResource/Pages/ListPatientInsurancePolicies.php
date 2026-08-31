<?php

namespace App\Filament\Resources\PatientInsurancePolicyResource\Pages;

use App\Filament\Resources\PatientInsurancePolicyResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPatientInsurancePolicies extends ListRecords
{
    protected static string $resource = PatientInsurancePolicyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Attach Patient Policy')
                ->icon('heroicon-o-plus'),
        ];
    }
}
