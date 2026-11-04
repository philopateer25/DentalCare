<?php

namespace App\Filament\Resources\ProcedureCodeResource\Pages;

use App\Filament\Resources\ProcedureCodeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProcedureCode extends EditRecord
{
    protected static string $resource = ProcedureCodeResource::class;

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
