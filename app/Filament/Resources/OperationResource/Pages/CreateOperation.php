<?php

namespace App\Filament\Resources\OperationResource\Pages;

use App\Filament\Resources\OperationResource;
use App\Models\Branch;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateOperation extends CreateRecord
{
    protected static string $resource = OperationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Was hard-coded to practice/branch 1: records landed in the wrong clinic.
        $practiceId = Filament::getTenant()?->id ?? auth()->user()->practice_id;
        $data['practice_id'] = $practiceId;
        $data['branch_id'] = auth()->user()->branch_id
            ?? Branch::where('practice_id', $practiceId)->orderBy('id')->value('id');
        $data['status'] = 'completed'; // Force status to completed for the log
        $data['type'] = 'new_visit'; // Must match the enum check constraint

        return $data;
    }
}
