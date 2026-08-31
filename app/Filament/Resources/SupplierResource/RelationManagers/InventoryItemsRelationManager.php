<?php

namespace App\Filament\Resources\SupplierResource\RelationManagers;

use App\Models\InventoryItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class InventoryItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'inventoryItems';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('sku')
                    ->label('SKU')
                    ->maxLength(50),
                Forms\Components\TextInput::make('brand')
                    ->maxLength(100),
                Forms\Components\TextInput::make('category')
                    ->required(),
                Forms\Components\TextInput::make('unit')
                    ->default('pcs')
                    ->required(),
                Forms\Components\TextInput::make('unit_price')
                    ->numeric()
                    ->prefix('$')
                    ->default(0.00),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Item Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('brand')
                    ->label('Brand')
                    ->searchable(),
                Tables\Columns\TextColumn::make('category')
                    ->badge(),
                Tables\Columns\TextColumn::make('unit_price')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_stock')
                    ->label('Stock')
                    ->state(fn (InventoryItem $record) => $record->total_stock . ' ' . $record->unit),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
