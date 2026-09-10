<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $authUser = auth()->user();

        if ($authUser && !$authUser->hasRole('developer')) {
            $data['practice_id'] = Filament::getTenant()?->id ?? $authUser->practice_id;
        }

        return $data;
    }
}
