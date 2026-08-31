<?php

namespace App\Filament\Resources\ClinicExpenseResource\Pages;

use App\Filament\Resources\ClinicExpenseResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditClinicExpense extends EditRecord
{
    protected static string $resource = ClinicExpenseResource::class;

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
