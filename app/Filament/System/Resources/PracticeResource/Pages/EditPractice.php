<?php

namespace App\Filament\System\Resources\PracticeResource\Pages;

use App\Filament\System\Resources\PracticeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPractice extends EditRecord
{
    protected static string $resource = PracticeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
