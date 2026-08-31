<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClinicExpenseResource\Pages;
use App\Models\ClinicExpense;
use App\Models\DentalLab;
use App\Models\Practice;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ClinicExpenseResource extends Resource
{
    protected static ?string $model = ClinicExpense::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationGroup = 'Finance & Treasury';

    protected static ?string $navigationLabel = 'Expenses & Overhead';

    protected static ?string $modelLabel = 'Clinic Expense';

    protected static ?string $pluralModelLabel = 'Clinic Expenses & Overhead';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Expense Information & Payee')
                    ->schema([
                        Forms\Components\TextInput::make('expense_number')
                            ->label('Expense Voucher #')
                            ->disabled()
                            ->dehydrated()
                            ->placeholder('Auto-generated (e.g. EXP-2026-0001)'),
                        Forms\Components\Select::make('category')
                            ->label('Accounting Category')
                            ->options([
                                'Clinical Supplies & Materials' => 'Clinical Supplies & Consumables',
                                'Dental Lab Fees' => 'Dental Lab Production Invoices',
                                'Staff Payroll & Salaries' => 'Staff Salaries & Hygienist Payroll',
                                'Facility Rent & Lease' => 'Clinic Facility Rent & Lease',
                                'Utilities & Electricity' => 'Utilities (Electricity, Water, Waste Disposal)',
                                'Equipment Lease & Maintenance' => 'Dental Equipment Maintenance & Sterilizer Service',
                                'Marketing & Patient Acquisition' => 'Marketing, Google Ads & Social Media',
                                'Software & Technology' => 'PMS Software, AI Diagnostics & Cloud Storage',
                                'Insurance & Licensing' => 'Malpractice & Clinic Liability Insurance',
                                'Taxes & Accounting' => 'Taxes, CPA & Legal Fees',
                                'Miscellaneous' => 'General Office Supplies & Misc',
                            ])
                            ->required()
                            ->searchable(),
                        Forms\Components\TextInput::make('payee')
                            ->label('Payee / Vendor / Landlord Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g. Henry Schein Dental, City Power & Water, Landlord Realty LLC'),
                        Forms\Components\Select::make('supplier_id')
                            ->label('Linked Supplier (if applicable)')
                            ->relationship('supplier', 'name')
                            ->searchable()
                            ->placeholder('None / Direct Vendor'),
                        Forms\Components\Select::make('dental_lab_id')
                            ->label('Linked Dental Lab (if applicable)')
                            ->relationship('dentalLab', 'name')
                            ->searchable()
                            ->placeholder('None / Not Lab Fee'),
                        Forms\Components\Select::make('practice_id')
                            ->relationship('practice', 'name')
                            ->default(fn () => Practice::firstOrCreate(['name' => 'Main Clinic'])->id)
                            ->required(),
                    ])->columns(3),

                Forms\Components\Section::make('Financial Disbursement & Compliance')
                    ->schema([
                        Forms\Components\TextInput::make('amount')
                            ->label('Expense Amount ($)')
                            ->numeric()
                            ->prefix('$')
                            ->required(),
                        Forms\Components\DatePicker::make('expense_date')
                            ->label('Transaction Date')
                            ->default(now())
                            ->required(),
                        Forms\Components\Select::make('payment_method')
                            ->options([
                                'bank_transfer' => 'Bank Wire / ACH Transfer',
                                'credit_card' => 'Corporate Credit Card',
                                'auto_debit' => 'Automated Bank Direct Debit',
                                'cheque' => 'Corporate Bank Cheque',
                                'cash' => 'Petty Cash',
                            ])
                            ->default('bank_transfer')
                            ->required(),
                        Forms\Components\TextInput::make('reference_number')
                            ->label('Invoice / Voucher / Cheque Ref #')
                            ->placeholder('e.g. INV-88291, CHQ-00192'),
                        Forms\Components\Toggle::make('tax_deductible')
                            ->label('Tax Deductible Business Expense')
                            ->default(true),
                        Forms\Components\Toggle::make('is_recurring')
                            ->label('Recurring Fixed Overhead (e.g. Rent, Subscriptions)')
                            ->default(false),
                        Forms\Components\Select::make('recurring_frequency')
                            ->options([
                                'monthly' => 'Monthly',
                                'quarterly' => 'Quarterly',
                                'yearly' => 'Annual',
                            ])
                            ->placeholder('Select Frequency'),
                        Forms\Components\TextInput::make('receipt_url')
                            ->label('Scanned Invoice / Receipt URL')
                            ->url()
                            ->prefix('https://')
                            ->placeholder('drive.google.com/receipts/...'),
                        Forms\Components\Textarea::make('notes')
                            ->label('Accounting Memo & Details')
                            ->columnSpanFull(),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('expense_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('expense_number')
                    ->label('Voucher #')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('category')
                    ->label('Category')
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('payee')
                    ->label('Payee / Vendor')
                    ->searchable()
                    ->weight('medium'),
                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Method')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Disbursed Amount')
                    ->money('USD')
                    ->weight('bold')
                    ->color('danger')
                    ->sortable(),
                Tables\Columns\IconColumn::make('tax_deductible')
                    ->label('Tax Ded.')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_recurring')
                    ->label('Recurring')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->options([
                        'Clinical Supplies & Materials' => 'Clinical Supplies & Materials',
                        'Dental Lab Fees' => 'Dental Lab Fees',
                        'Staff Payroll & Salaries' => 'Staff Payroll & Salaries',
                        'Facility Rent & Lease' => 'Facility Rent & Lease',
                        'Utilities & Electricity' => 'Utilities & Electricity',
                        'Equipment Lease & Maintenance' => 'Equipment Maintenance',
                        'Marketing & Patient Acquisition' => 'Marketing & Acquisition',
                        'Software & Technology' => 'Software & Technology',
                        'Taxes & Accounting' => 'Taxes & Accounting',
                    ]),
                Tables\Filters\TernaryFilter::make('tax_deductible')
                    ->label('Tax Deductible Only'),
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
            'index' => Pages\ListClinicExpenses::route('/'),
            'create' => Pages\CreateClinicExpense::route('/create'),
            'edit' => Pages\EditClinicExpense::route('/{record}/edit'),
        ];
    }
}
