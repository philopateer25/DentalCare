<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $authUser = auth()->user();

        if ($authUser && !$authUser->hasRole('developer')) {
            $data['practice_id'] = Filament::getTenant()?->id ?? $authUser->practice_id;
        }

        return $data;
    }
}
