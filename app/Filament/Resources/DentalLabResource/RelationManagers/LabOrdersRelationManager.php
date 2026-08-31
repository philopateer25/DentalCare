<?php

namespace App\Filament\Resources\DentalLabResource\RelationManagers;

use App\Models\LabOrder;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class LabOrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'labOrders';

    protected static ?string $recordTitleAttribute = 'tracking_number';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('tracking_number')
                    ->label('Case #')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('patient.first_name')
                    ->label('Patient')
                    ->formatStateUsing(fn ($record) => "{$record->patient?->first_name} {$record->patient?->last_name}")
                    ->searchable(),
                Tables\Columns\TextColumn::make('doctor.name')
                    ->label('Doctor')
                    ->searchable(),
                Tables\Columns\TextColumn::make('order_type')
                    ->label('Restoration')
                    ->badge(),
                Tables\Columns\TextColumn::make('material')
                    ->placeholder('N/A'),
                Tables\Columns\TextColumn::make('shade')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'impression_sent' => 'info',
                        'lab_acknowledged' => 'primary',
                        'in_production' => 'warning',
                        'try_in_stage' => 'secondary',
                        'shipped_by_lab' => 'info',
                        'received_at_clinic' => 'success',
                        'seated_delivered' => 'success',
                        'returned_for_redo' => 'danger',
                        'cancelled' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('expected_delivery_at')
                    ->label('Due Date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('cost')
                    ->money('USD')
                    ->sortable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
