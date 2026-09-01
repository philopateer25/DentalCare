<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PayrollSlipResource\Pages;
use App\Models\PayrollSlip;
use App\Models\Practice;
use App\Models\StaffMember;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PayrollSlipResource extends Resource
{
    protected static ?string $model = PayrollSlip::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationGroup = 'Staff & HR Management';

    protected static ?string $navigationLabel = 'Payroll & Payslips';

    protected static ?string $modelLabel = 'Payslip';

    protected static ?string $pluralModelLabel = 'Monthly Staff Payroll Slips';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Pay Period & Employee')
                    ->schema([
                        Forms\Components\TextInput::make('payslip_number')
                            ->label('Payslip Voucher #')
                            ->default(fn () => 'PAY-' . date('Y') . '-' . date('m') . '-' . str_pad((string) (PayrollSlip::max('id') + 1), 4, '0', STR_PAD_LEFT))
                            ->disabled()
                            ->dehydrated()
                            ->placeholder('Auto-generated (e.g. PAY-2026-08-0001)'),
                        Forms\Components\Select::make('staff_member_id')
                            ->label('Staff Member')
                            ->relationship('staffMember', 'first_name')
                            ->getOptionLabelFromRecordUsing(fn (StaffMember $record) => "{$record->first_name} {$record->last_name} ({$record->role})")
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if ($staff = StaffMember::find($state)) {
                                    $set('base_salary', $staff->base_salary);
                                    $set('net_salary', $staff->base_salary);
                                }
                            }),
                        Forms\Components\Select::make('pay_period_month')
                            ->label('Pay Month')
                            ->options([
                                1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                                5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                                9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
                            ])
                            ->default((int) date('m'))
                            ->required(),
                        Forms\Components\TextInput::make('pay_period_year')
                            ->label('Pay Year')
                            ->numeric()
                            ->default((int) date('Y'))
                            ->required(),
                        Forms\Components\Select::make('practice_id')
                            ->relationship('practice', 'name')
                            ->default(fn () => Practice::firstOrCreate(['name' => 'Main Clinic'])->id)
                            ->required(),
                    ])->columns(3),

                Forms\Components\Section::make('Earnings & Allowances')
                    ->schema([
                        Forms\Components\TextInput::make('base_salary')
                            ->label('Base Contract Salary ($)')
                            ->numeric()
                            ->prefix('$')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => 
                                $set('net_salary', PayrollSlip::calculateNet(
                                    (float)$state, (float)$get('overtime_amount'), (float)$get('bonus_amount'), (float)$get('allowance_amount'),
                                    (float)$get('tax_deduction'), (float)$get('insurance_deduction'), (float)$get('other_deductions')
                                ))
                            ),
                        Forms\Components\TextInput::make('overtime_amount')
                            ->label('Overtime & Weekend Pay ($)')
                            ->numeric()
                            ->prefix('$')
                            ->default(0.00)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => 
                                $set('net_salary', PayrollSlip::calculateNet(
                                    (float)$get('base_salary'), (float)$state, (float)$get('bonus_amount'), (float)$get('allowance_amount'),
                                    (float)$get('tax_deduction'), (float)$get('insurance_deduction'), (float)$get('other_deductions')
                                ))
                            ),
                        Forms\Components\TextInput::make('bonus_amount')
                            ->label('Performance Bonus ($)')
                            ->numeric()
                            ->prefix('$')
                            ->default(0.00)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => 
                                $set('net_salary', PayrollSlip::calculateNet(
                                    (float)$get('base_salary'), (float)$get('overtime_amount'), (float)$state, (float)$get('allowance_amount'),
                                    (float)$get('tax_deduction'), (float)$get('insurance_deduction'), (float)$get('other_deductions')
                                ))
                            ),
                        Forms\Components\TextInput::make('allowance_amount')
                            ->label('Transport / Healthcare Allowance ($)')
                            ->numeric()
                            ->prefix('$')
                            ->default(0.00)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => 
                                $set('net_salary', PayrollSlip::calculateNet(
                                    (float)$get('base_salary'), (float)$get('overtime_amount'), (float)$get('bonus_amount'), (float)$state,
                                    (float)$get('tax_deduction'), (float)$get('insurance_deduction'), (float)$get('other_deductions')
                                ))
                            ),
                    ])->columns(4),

                Forms\Components\Section::make('Statutory Deductions & Net Take-Home')
                    ->schema([
                        Forms\Components\TextInput::make('tax_deduction')
                            ->label('Tax Withholding ($)')
                            ->numeric()
                            ->prefix('$')
                            ->default(0.00)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => 
                                $set('net_salary', PayrollSlip::calculateNet(
                                    (float)$get('base_salary'), (float)$get('overtime_amount'), (float)$get('bonus_amount'), (float)$get('allowance_amount'),
                                    (float)$state, (float)$get('insurance_deduction'), (float)$get('other_deductions')
                                ))
                            ),
                        Forms\Components\TextInput::make('insurance_deduction')
                            ->label('Health & Social Insurance ($)')
                            ->numeric()
                            ->prefix('$')
                            ->default(0.00)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => 
                                $set('net_salary', PayrollSlip::calculateNet(
                                    (float)$get('base_salary'), (float)$get('overtime_amount'), (float)$get('bonus_amount'), (float)$get('allowance_amount'),
                                    (float)$get('tax_deduction'), (float)$state, (float)$get('other_deductions')
                                ))
                            ),
                        Forms\Components\TextInput::make('other_deductions')
                            ->label('Other Deductions / Advances ($)')
                            ->numeric()
                            ->prefix('$')
                            ->default(0.00)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => 
                                $set('net_salary', PayrollSlip::calculateNet(
                                    (float)$get('base_salary'), (float)$get('overtime_amount'), (float)$get('bonus_amount'), (float)$get('allowance_amount'),
                                    (float)$get('tax_deduction'), (float)$get('insurance_deduction'), (float)$state
                                ))
                            ),
                        Forms\Components\TextInput::make('net_salary')
                            ->label('Net Payable Salary ($)')
                            ->numeric()
                            ->prefix('$')
                            ->required(),
                    ])->columns(4),

                Forms\Components\Section::make('Disbursement & Authorization')
                    ->schema([
                        Forms\Components\Select::make('payment_method')
                            ->options([
                                'bank_direct_deposit' => 'Bank Direct Deposit (ACH / IBAN)',
                                'cheque' => 'Corporate Bank Cheque',
                                'instapay' => 'InstaPay / Instant Wire',
                                'cash' => 'Direct Cash Payment',
                            ])
                            ->default('bank_direct_deposit')
                            ->required(),
                        Forms\Components\Select::make('status')
                            ->options([
                                'draft' => 'Draft Calculation',
                                'approved' => 'Approved by Management',
                                'disbursed' => 'Disbursed / Paid to Bank',
                            ])
                            ->default('approved')
                            ->required(),
                        Forms\Components\DateTimePicker::make('disbursed_at')
                            ->label('Bank Disbursement Timestamp'),
                        Forms\Components\Textarea::make('notes')
                            ->columnSpanFull(),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('payslip_number')
                    ->label('Voucher #')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('staffMember.full_name')
                    ->label('Employee')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),
                Tables\Columns\TextColumn::make('staffMember.role')
                    ->label('Role')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('pay_period_month')
                    ->label('Period')
                    ->formatStateUsing(fn ($record) => date('M', mktime(0, 0, 0, $record->pay_period_month, 1)) . " {$record->pay_period_year}")
                    ->sortable(),
                Tables\Columns\TextColumn::make('base_salary')
                    ->label('Base')
                    ->money('USD'),
                Tables\Columns\TextColumn::make('net_salary')
                    ->label('Net Take-Home')
                    ->money('USD')
                    ->weight('bold')
                    ->color('success')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'approved' => 'warning',
                        'disbursed' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('disbursed_at')
                    ->label('Paid Date')
                    ->date()
                    ->placeholder('Pending')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'approved' => 'Approved',
                        'disbursed' => 'Disbursed',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('markDisbursed')
                    ->label('Disburse Salary')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (PayrollSlip $record) => $record->status !== 'disbursed')
                    ->action(fn (PayrollSlip $record) => $record->update([
                        'status' => 'disbursed',
                        'disbursed_at' => now(),
                    ])),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bulkDisburse')
                        ->label('Disburse Selected Payslips')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update([
                            'status' => 'disbursed',
                            'disbursed_at' => now(),
                        ])),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayrollSlips::route('/'),
            'create' => Pages\CreatePayrollSlip::route('/create'),
            'edit' => Pages\EditPayrollSlip::route('/{record}/edit'),
        ];
    }
}
