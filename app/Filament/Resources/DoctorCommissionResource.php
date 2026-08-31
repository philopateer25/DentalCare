<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DoctorCommissionResource\Pages;
use App\Models\DoctorCommission;
use App\Models\Payment;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DoctorCommissionResource extends Resource
{
    protected static ?string $model = DoctorCommission::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Finance & Treasury';

    protected static ?string $navigationLabel = 'Doctor Payroll & Splits';

    protected static ?string $modelLabel = 'Doctor Commission';

    protected static ?string $pluralModelLabel = 'Doctor Payroll & Commission Ledgers';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Doctor & Procedure Attribution')
                    ->schema([
                        Forms\Components\Select::make('doctor_id')
                            ->label('Doctor / Associate')
                            ->relationship('doctor', 'name')
                            ->searchable()
                            ->required(),
                        Forms\Components\Select::make('payment_id')
                            ->label('Linked Payment Transaction')
                            ->relationship('payment', 'id')
                            ->getOptionLabelFromRecordUsing(fn (Payment $record) => "Payment #{$record->id} - \${$record->amount} ({$record->patient?->first_name} {$record->patient?->last_name})")
                            ->searchable()
                            ->required(),
                    ])->columns(2),

                Forms\Components\Section::make('Commission Calculations & Lab Split')
                    ->schema([
                        Forms\Components\TextInput::make('gross_amount')
                            ->label('Gross Procedure Revenue ($)')
                            ->numeric()
                            ->prefix('$')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => 
                                $set('commission_amount', DoctorCommission::calculateCommission((float)$state, (float)$get('lab_deduction_amount'), (float)$get('commission_percentage')))
                            ),
                        Forms\Components\TextInput::make('lab_deduction_amount')
                            ->label('Lab Fee Deductions ($)')
                            ->numeric()
                            ->prefix('$')
                            ->default(0.00)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => 
                                $set('commission_amount', DoctorCommission::calculateCommission((float)$get('gross_amount'), (float)$state, (float)$get('commission_percentage')))
                            ),
                        Forms\Components\TextInput::make('commission_percentage')
                            ->label('Doctor Split / Commission %')
                            ->numeric()
                            ->suffix('%')
                            ->default(40.00)
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => 
                                $set('commission_amount', DoctorCommission::calculateCommission((float)$get('gross_amount'), (float)$get('lab_deduction_amount'), (float)$state))
                            ),
                        Forms\Components\TextInput::make('commission_amount')
                            ->label('Net Payable Commission ($)')
                            ->numeric()
                            ->prefix('$')
                            ->required(),
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending Clearance',
                                'accrued' => 'Accrued (Approved for Payroll)',
                                'settled' => 'Settled / Paid Out',
                            ])
                            ->default('accrued')
                            ->required(),
                        Forms\Components\DateTimePicker::make('settled_at')
                            ->label('Payout Settlement Date'),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('doctor.name')
                    ->label('Doctor / Associate')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('gross_amount')
                    ->label('Gross Rev')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('lab_deduction_amount')
                    ->label('Lab Cost')
                    ->money('USD')
                    ->color('danger')
                    ->placeholder('$0.00'),
                Tables\Columns\TextColumn::make('commission_percentage')
                    ->label('Split %')
                    ->formatStateUsing(fn ($state) => "{$state}%")
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('commission_amount')
                    ->label('Doctor Commission')
                    ->money('USD')
                    ->weight('bold')
                    ->color('success')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'accrued' => 'info',
                        'settled' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('settled_at')
                    ->label('Paid On')
                    ->date()
                    ->placeholder('Unsettled')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('doctor_id')
                    ->label('Doctor')
                    ->relationship('doctor', 'name'),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending Clearance',
                        'accrued' => 'Accrued (Payable)',
                        'settled' => 'Settled (Paid)',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('markSettled')
                    ->label('Settle Payout')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (DoctorCommission $record) => $record->status !== 'settled')
                    ->action(fn (DoctorCommission $record) => $record->update([
                        'status' => 'settled',
                        'settled_at' => now(),
                    ])),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bulkSettle')
                        ->label('Settle Selected Commissions')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update([
                            'status' => 'settled',
                            'settled_at' => now(),
                        ])),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDoctorCommissions::route('/'),
            'create' => Pages\CreateDoctorCommission::route('/create'),
            'edit' => Pages\EditDoctorCommission::route('/{record}/edit'),
        ];
    }
}
