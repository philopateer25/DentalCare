<?php

namespace App\Filament\Resources\InventoryResource\RelationManagers;

use App\Models\InventoryBatch;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class BatchesRelationManager extends RelationManager
{
    protected static string $relationship = 'batches';

    protected static ?string $recordTitleAttribute = 'batch_number';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('batch_number')
                    ->label('Batch / Lot Number')
                    ->default(fn () => 'BAT-' . date('Ymd') . '-' . str_pad((string) (InventoryBatch::max('id') + 1), 4, '0', STR_PAD_LEFT))
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Auto-generated (e.g. BAT-20260901-0001)'),
                Forms\Components\Select::make('supplier_id')
                    ->label('Supplier')
                    ->options(Supplier::query()->pluck('name', 'id'))
                    ->searchable()
                    ->placeholder('Select Supplier'),
                Forms\Components\DatePicker::make('received_date')
                    ->label('Date Received')
                    ->default(now())
                    ->native(false),
                Forms\Components\DatePicker::make('expiry_date')
                    ->label('Expiration Date')
                    ->native(false),
                Forms\Components\TextInput::make('unit_cost')
                    ->label('Unit Cost')
                    ->numeric()
                    ->prefix('$')
                    ->default(0.00)
                    ->required(),
                Forms\Components\TextInput::make('quantity_received')
                    ->label('Quantity Received')
                    ->numeric()
                    ->default(1)
                    ->required(),
                Forms\Components\TextInput::make('quantity_remaining')
                    ->label('Quantity Remaining')
                    ->numeric()
                    ->default(1)
                    ->required(),
                Forms\Components\TextInput::make('notes')
                    ->label('Batch Notes')
                    ->columnSpanFull(),
            ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('batch_number')
                    ->label('Batch / Lot #')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->placeholder('N/A')
                    ->sortable(),
                Tables\Columns\TextColumn::make('received_date')
                    ->label('Received')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('expiry_date')
                    ->label('Expiry Date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('expiry_status')
                    ->label('Expiry Alert')
                    ->badge()
                    ->state(function ($record) {
                        if (! $record->expiry_date) {
                            return 'No Expiry';
                        }
                        if ($record->expiry_date->isPast()) {
                            return 'Expired';
                        }
                        if ($record->expiry_date->diffInDays(now()) <= 60) {
                            return 'Expiring Soon';
                        }

                        return 'Active';
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Expired' => 'danger',
                        'Expiring Soon' => 'warning',
                        'Active' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('unit_cost')
                    ->label('Unit Cost')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('quantity_received')
                    ->label('Received')
                    ->sortable(),
                Tables\Columns\TextColumn::make('quantity_remaining')
                    ->label('Remaining')
                    ->sortable()
                    ->weight('bold'),
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
