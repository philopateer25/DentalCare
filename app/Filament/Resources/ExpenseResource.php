<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExpenseResource\Pages;
use App\Models\Expense;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationGroup = 'Financial Ledger';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Expense Entry')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('category')
                            ->options([
                                'lab_fees' => 'Lab Fees',
                                'consumables' => 'Dental Consumables',
                                'salaries' => 'Staff Salaries',
                                'rent' => 'Facility Rent',
                                'utilities' => 'Utilities (Electricity/Water/Net)',
                                'maintenance' => 'Equipment Maintenance',
                                'other' => 'Other Expenses',
                            ])
                            ->required()
                            ->default('other'),
                        Forms\Components\TextInput::make('amount')
                            ->numeric()
                            ->prefix('EGP')
                            ->required(),
                        Forms\Components\DatePicker::make('expense_date')
                            ->default(now())
                            ->required(),
                        Forms\Components\FileUpload::make('receipt_attachment')
                            ->directory('receipts')
                            ->image()
                            ->maxSize(5120)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('description')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('category')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'lab_fees' => 'purple',
                        'consumables' => 'info',
                        'salaries' => 'success',
                        'rent', 'utilities' => 'warning',
                        'maintenance' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->money('EGP')
                    ->sortable(),
                Tables\Columns\TextColumn::make('expense_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('loggedBy.name')
                    ->label('Logged By'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->options([
                        'lab_fees' => 'Lab Fees',
                        'consumables' => 'Dental Consumables',
                        'salaries' => 'Staff Salaries',
                        'rent' => 'Facility Rent',
                        'utilities' => 'Utilities',
                        'maintenance' => 'Maintenance',
                        'other' => 'Other',
                    ]),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExpenses::route('/'),
            'create' => Pages\CreateExpense::route('/create'),
            'edit' => Pages\EditExpense::route('/{record}/edit'),
        ];
    }
}
