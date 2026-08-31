<?php

namespace App\Filament\Resources\InsuranceClaimResource\Pages;

use App\Filament\Resources\InsuranceClaimResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInsuranceClaims extends ListRecords
{
    protected static string $resource = InsuranceClaimResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('File Electronic Dental Claim')
                ->icon('heroicon-o-plus'),
        ];
    }
}
