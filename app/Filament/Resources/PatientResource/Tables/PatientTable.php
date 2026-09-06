<?php

namespace App\Filament\Resources\PatientResource\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables;

class PatientTable
{
    public static function columns(): array
    {
        return [
            TextColumn::make('file_number')
                ->searchable()
                ->sortable(),
            TextColumn::make('first_name')
                ->searchable()
                ->sortable(),
            TextColumn::make('last_name')
                ->searchable()
                ->sortable(),
            TextColumn::make('phone')
                ->searchable(),
            TextColumn::make('national_id')
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('status')
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'active' => 'success',
                    'inactive' => 'warning',
                    'archived' => 'danger',
                    default => 'primary',
                }),
        ];
    }

    public static function filters(): array
    {
        return [
            SelectFilter::make('status')
                ->options([
                    'active' => 'Active',
                    'inactive' => 'Inactive',
                    'archived' => 'Archived',
                ]),
        ];
    }

    public static function actions(): array
    {
        return [
            Tables\Actions\ViewAction::make(),
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ];
    }
}
