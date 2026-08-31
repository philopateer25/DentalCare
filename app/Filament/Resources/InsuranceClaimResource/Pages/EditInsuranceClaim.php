<?php

namespace App\Filament\Resources\InsuranceClaimResource\Pages;

use App\Filament\Resources\InsuranceClaimResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInsuranceClaim extends EditRecord
{
    protected static string $resource = InsuranceClaimResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
