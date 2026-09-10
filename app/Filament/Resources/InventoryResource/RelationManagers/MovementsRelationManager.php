<?php

namespace App\Filament\Resources\InventoryResource\RelationManagers;

use App\Models\InventoryStockMovement;
use App\Services\CurrencyHelper;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class MovementsRelationManager extends RelationManager
{
    protected static string $relationship = 'movements';

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?string $title = 'Stock Movement & Audit Log';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Timestamp')
                    ->dateTime()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Performed By')
                    ->placeholder('System')
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Movement Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'addition', 'purchase' => 'success',
                        'subtraction', 'waste', 'expired' => 'danger',
                        'adjustment' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('quantity_change')
                    ->label('Qty Change')
                    ->formatStateUsing(fn ($state) => ($state > 0 ? "+{$state}" : "{$state}"))
                    ->weight('bold')
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('previous_quantity')
                    ->label('Prev Stock'),
                Tables\Columns\TextColumn::make('resulting_quantity')
                    ->label('Resulting Stock')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('unit_cost')
                    ->label('Unit Cost')
                    ->money(fn () => CurrencyHelper::currentCurrency()),
                Tables\Columns\TextColumn::make('inventoryBatch.batch_number')
                    ->label('Batch #')
                    ->placeholder('N/A'),
                Tables\Columns\TextColumn::make('reason')
                    ->label('Reason / Notes')
                    ->placeholder('None'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
