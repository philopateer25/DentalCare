<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvoiceResource\Pages;
use App\Filament\Resources\InvoiceResource\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\InvoiceResource\RelationManagers\PaymentsRelationManager;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Practice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-currency-dollar';

    protected static ?string $navigationGroup = 'Finance & Treasury';

    protected static ?string $navigationLabel = 'Invoices & Billing';

    protected static ?string $modelLabel = 'Invoice';

    protected static ?string $pluralModelLabel = 'Invoices & Billing Hub';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Invoice Header & Patient Account')
                    ->schema([
                        Forms\Components\TextInput::make('invoice_number')
                            ->label('Invoice #')
                            ->disabled()
                            ->dehydrated()
                            ->placeholder('Auto-generated (e.g. INV-2026-0001)'),
                        Forms\Components\Select::make('patient_id')
                            ->label('Patient')
                            ->relationship('patient', 'first_name')
                            ->getOptionLabelFromRecordUsing(fn (Patient $record) => "{$record->first_name} {$record->last_name} ({$record->file_number})")
                            ->searchable(['first_name', 'last_name', 'file_number'])
                            ->preload()
                            ->required(),
                        Forms\Components\DatePicker::make('issue_date')
                            ->label('Issue Date')
                            ->default(now())
                            ->required(),
                        Forms\Components\DatePicker::make('due_date')
                            ->label('Due Date')
                            ->default(now()->addDays(30)),
                        Forms\Components\Select::make('practice_id')
                            ->label('Practice')
                            ->relationship('practice', 'name')
                            ->default(fn () => Practice::firstOrCreate(['name' => 'Main Clinic'])->id)
                            ->required(),
                        Forms\Components\Select::make('status')
                            ->label('Payment Status')
                            ->options([
                                'unpaid' => 'Unpaid',
                                'partially_paid' => 'Partially Paid',
                                'paid' => 'Fully Paid (Settled)',
                                'overdue' => 'Overdue (Past Due)',
                                'cancelled' => 'Cancelled / Void',
                            ])
                            ->default('unpaid')
                            ->required(),
                    ])->columns(3),

                Forms\Components\Section::make('Financial Ledger & Cost Breakdown')
                    ->schema([
                        Forms\Components\TextInput::make('subtotal')
                            ->label('Subtotal ($)')
                            ->numeric()
                            ->prefix('$')
                            ->default(0.00)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => 
                                $set('total_amount', max(0, (float)$state - (float)$get('discount_amount') + (float)$get('tax_amount')))
                            ),
                        Forms\Components\TextInput::make('discount_amount')
                            ->label('Courtesy / Discount ($)')
                            ->numeric()
                            ->prefix('$')
                            ->default(0.00)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => 
                                $set('total_amount', max(0, (float)$get('subtotal') - (float)$state + (float)$get('tax_amount')))
                            ),
                        Forms\Components\TextInput::make('tax_amount')
                            ->label('Sales / Service Tax ($)')
                            ->numeric()
                            ->prefix('$')
                            ->default(0.00)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => 
                                $set('total_amount', max(0, (float)$get('subtotal') - (float)$get('discount_amount') + (float)$state))
                            ),
                        Forms\Components\TextInput::make('total_amount')
                            ->label('Total Invoiced Amount ($)')
                            ->numeric()
                            ->prefix('$')
                            ->default(0.00)
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => 
                                $set('balance_due', max(0, (float)$state - (float)$get('paid_amount')))
                            ),
                        Forms\Components\TextInput::make('paid_amount')
                            ->label('Total Paid / Collected ($)')
                            ->numeric()
                            ->prefix('$')
                            ->default(0.00)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => 
                                $set('balance_due', max(0, (float)$get('total_amount') - (float)$state))
                            ),
                        Forms\Components\TextInput::make('balance_due')
                            ->label('Remaining Balance Due ($)')
                            ->numeric()
                            ->prefix('$')
                            ->default(0.00)
                            ->required(),
                        Forms\Components\TextInput::make('insurance_covered_amount')
                            ->label('Insurance / TPA Portion ($)')
                            ->numeric()
                            ->prefix('$')
                            ->default(0.00),
                        Forms\Components\TextInput::make('patient_copay_amount')
                            ->label('Patient Co-Pay ($)')
                            ->numeric()
                            ->prefix('$')
                            ->default(0.00),
                    ])->columns(3),

                Forms\Components\Section::make('Terms, Conditions & Internal Notes')
                    ->schema([
                        Forms\Components\Textarea::make('terms_and_conditions')
                            ->label('Payment Terms & Policy')
                            ->default('Payment is due upon receipt of service unless an installment contract is executed.')
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('notes')
                            ->label('Billing & Insurance Notes')
                            ->columnSpanFull(),
                    ])->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('Invoice #')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('patient.first_name')
                    ->label('Patient Name')
                    ->formatStateUsing(fn (Invoice $record) => "{$record->patient?->first_name} {$record->patient?->last_name}")
                    ->description(fn (Invoice $record) => "File: {$record->patient?->file_number}")
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),
                Tables\Columns\TextColumn::make('issue_date')
                    ->label('Issue Date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total Invoiced')
                    ->money('USD')
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('paid_amount')
                    ->label('Collected')
                    ->money('USD')
                    ->color('success')
                    ->sortable(),
                Tables\Columns\TextColumn::make('balance_due')
                    ->label('Balance Due')
                    ->money('USD')
                    ->color(fn ($state) => (float)$state > 0 ? 'danger' : 'gray')
                    ->weight('bold')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'partially_paid' => 'warning',
                        'unpaid' => 'info',
                        'overdue' => 'danger',
                        'cancelled' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('due_date')
                    ->label('Due Date')
                    ->date()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'unpaid' => 'Unpaid',
                        'partially_paid' => 'Partially Paid',
                        'paid' => 'Fully Paid',
                        'overdue' => 'Overdue',
                        'cancelled' => 'Cancelled',
                    ]),
                Tables\Filters\Filter::make('unsettled')
                    ->label('Outstanding Balances (A/R > $0)')
                    ->query(fn (Builder $query): Builder => $query->where('balance_due', '>', 0)),
                Tables\Filters\Filter::make('overdue')
                    ->label('Past Due Overdue Invoices')
                    ->query(fn (Builder $query): Builder => $query->where('balance_due', '>', 0)->where('due_date', '<', now())),
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

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
            PaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoices::route('/'),
            'create' => Pages\CreateInvoice::route('/create'),
            'edit' => Pages\EditInvoice::route('/{record}/edit'),
        ];
    }
}
