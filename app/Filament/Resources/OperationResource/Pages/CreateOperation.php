<?php

namespace App\Filament\Resources\OperationResource\Pages;

use App\Filament\Resources\OperationResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateOperation extends CreateRecord
{
    protected static string $resource = OperationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['practice_id'] = 1;
        $data['branch_id'] = 1;
        $data['status'] = 'completed'; // Force status to completed for the log
        $data['type'] = 'new_visit'; // Must match the enum check constraint
        return $data;
    }
}
