<?php

namespace App\Filament\Resources\ProcedureCodeResource\Pages;

use App\Filament\Resources\ProcedureCodeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProcedureCode extends CreateRecord
{
    protected static string $resource = ProcedureCodeResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
