<?php

namespace App\Filament\Resources\SupplierResource\RelationManagers;

use App\Models\InventoryItem;
use App\Services\CurrencyHelper;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class InventoryItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'inventoryItems';

    protected static ?string $recordTitleAttribute = 'name';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('practice_id')
                    ->default(fn () => \Filament\Facades\Filament::getTenant()?->id ?? auth()->user()?->practice_id),
                Forms\Components\TextInput::make('name')
                    ->label('Item Name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('sku')
                    ->label('SKU')
                    ->maxLength(50),
                Forms\Components\TextInput::make('brand')
                    ->label('Brand')
                    ->maxLength(100),
                Forms\Components\Select::make('category')
                    ->label('Clinical Category')
                    ->options([
                        'Diagnostic & Examination' => 'Diagnostic & Examination',
                        'Operative & Restorative' => 'Operative & Restorative',
                        'Endodontics' => 'Endodontics',
                        'Periodontics & Hygiene' => 'Periodontics & Hygiene',
                        'Oral Surgery & Extractions' => 'Oral Surgery & Extractions',
                        'Prosthodontics & Impression' => 'Prosthodontics & Impression',
                        'Orthodontics' => 'Orthodontics',
                        'Pediatric Dentistry' => 'Pediatric Dentistry',
                        'Dental Implantology' => 'Dental Implantology',
                        'Sterilization & Infection Control' => 'Sterilization & Infection Control',
                        'Local Anesthesia & Pharma' => 'Local Anesthesia & Pharma',
                        'Radiology & Imaging' => 'Radiology & Imaging',
                        'Dental Equipment & Handpieces' => 'Dental Equipment & Handpieces',
                        'Consumables & PPE' => 'Consumables & PPE',
                    ])
                    ->default('Consumables & PPE')
                    ->required(),
                Forms\Components\TextInput::make('unit')
                    ->default('pcs')
                    ->required(),
                Forms\Components\TextInput::make('unit_price')
                    ->numeric()
                    ->prefix(fn () => CurrencyHelper::symbol())
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
                    ->money(fn () => CurrencyHelper::currentCurrency())
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_stock')
                    ->label('Stock')
                    ->state(fn (InventoryItem $record) => $record->total_stock . ' ' . $record->unit),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['practice_id'] = \Filament\Facades\Filament::getTenant()?->id ?? auth()->user()?->practice_id;
                        return $data;
                    }),
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
