<?php

namespace App\Filament\Resources\PayrollSlipResource\Pages;

use App\Filament\Resources\PayrollSlipResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPayrollSlip extends EditRecord
{
    protected static string $resource = PayrollSlipResource::class;

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
