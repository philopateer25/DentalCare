<?php

namespace App\Filament\Resources\PatientResource\Pages;

use App\Filament\Resources\PatientResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPatients extends ListRecords
{
    protected static string $resource = PatientResource::class;

    public function getTitle(): string
    {
        return __('Patients Directory');
    }

    public function getSubheading(): ?string
    {
        $count = static::getResource()::getEloquentQuery()->count();

        return __('Manage :count registered patient files, appointments and billing history', ['count' => number_format($count)]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label(__('New patient'))
                ->icon('heroicon-o-user-plus'),
        ];
    }
}
