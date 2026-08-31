<?php

namespace App\Filament\Resources\InvoiceResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $recordTitleAttribute = 'description';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('description')
                    ->label('Procedure / Service Description')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g. Composite Restoration 2-Surfaces MOD (Tooth #16), Dental Cleaning & Polishing'),
                Forms\Components\TextInput::make('quantity')
                    ->numeric()
                    ->default(1)
                    ->required(),
                Forms\Components\TextInput::make('unit_price')
                    ->label('Unit Price')
                    ->numeric()
                    ->prefix('$')
                    ->required(),
                Forms\Components\TextInput::make('total_price')
                    ->label('Line Total')
                    ->numeric()
                    ->prefix('$')
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('description')
                    ->label('Clinical Procedure / Service')
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Qty')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('unit_price')
                    ->label('Unit Price')
                    ->money('USD'),
                Tables\Columns\TextColumn::make('total_price')
                    ->label('Total')
                    ->money('USD')
                    ->weight('bold'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->after(fn ($livewire) => $livewire->ownerRecord->recalculateTotals()),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->after(fn ($livewire) => $livewire->ownerRecord->recalculateTotals()),
                Tables\Actions\DeleteAction::make()
                    ->after(fn ($livewire) => $livewire->ownerRecord->recalculateTotals()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->after(fn ($livewire) => $livewire->ownerRecord->recalculateTotals()),
                ]),
            ]);
    }
}
