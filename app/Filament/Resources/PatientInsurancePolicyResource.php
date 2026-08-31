<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PatientInsurancePolicyResource\Pages;
use App\Models\InsuranceProvider;
use App\Models\Patient;
use App\Models\PatientInsurancePolicy;
use App\Models\Practice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PatientInsurancePolicyResource extends Resource
{
    protected static ?string $model = PatientInsurancePolicy::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationGroup = 'Insurance & Claims';

    protected static ?string $navigationLabel = 'Patient Insurance Policies';

    protected static ?string $modelLabel = 'Patient Policy';

    protected static ?string $pluralModelLabel = 'Patient Insurance Policies';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Policyholder & Insurance Plan')
                    ->schema([
                        Forms\Components\Select::make('patient_id')
                            ->label('Patient')
                            ->relationship('patient', 'first_name')
                            ->getOptionLabelFromRecordUsing(fn (Patient $record) => "{$record->first_name} {$record->last_name} ({$record->file_number})")
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('insurance_provider_id')
                            ->label('Insurance Carrier / Payer')
                            ->relationship('insuranceProvider', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\TextInput::make('policy_number')
                            ->label('Member / Policy ID #')
                            ->required()
                            ->placeholder('e.g. DEL-9920149'),
                        Forms\Components\TextInput::make('group_number')
                            ->label('Employer Group #')
                            ->placeholder('e.g. GRP-44102'),
                        Forms\Components\TextInput::make('subscriber_name')
                            ->label('Primary Subscriber Name')
                            ->required()
                            ->placeholder('Name as printed on insurance card'),
                        Forms\Components\Select::make('subscriber_relationship')
                            ->label('Relationship to Subscriber')
                            ->options([
                                'self' => 'Self (Primary Insured)',
                                'spouse' => 'Spouse',
                                'child' => 'Dependent Child',
                                'other' => 'Other Dependent',
                            ])
                            ->default('self')
                            ->required(),
                        Forms\Components\Select::make('plan_type')
                            ->options([
                                'PPO' => 'Preferred Provider (PPO)',
                                'HMO / DMO' => 'Dental HMO / Capitation',
                                'Indemnity' => 'Traditional Indemnity',
                                'Direct Reimbursement' => 'Direct Reimbursement',
                            ])
                            ->default('PPO')
                            ->required(),
                        Forms\Components\Select::make('practice_id')
                            ->relationship('practice', 'name')
                            ->default(fn () => Practice::firstOrCreate(['name' => 'Main Clinic'])->id)
                            ->required(),
                    ])->columns(3),

                Forms\Components\Section::make('Financial Maximums & Co-Insurance Coverage')
                    ->schema([
                        Forms\Components\TextInput::make('annual_maximum')
                            ->label('Annual Benefit Maximum ($)')
                            ->numeric()
                            ->prefix('$')
                            ->default(1500.00)
                            ->required(),
                        Forms\Components\TextInput::make('annual_deductible')
                            ->label('Annual Individual Deductible ($)')
                            ->numeric()
                            ->prefix('$')
                            ->default(50.00)
                            ->required(),
                        Forms\Components\TextInput::make('deductible_met')
                            ->label('Current Year Deductible Met ($)')
                            ->numeric()
                            ->prefix('$')
                            ->default(0.00),
                        Forms\Components\TextInput::make('preventive_coverage_pct')
                            ->label('Preventive & Cleanings %')
                            ->numeric()
                            ->suffix('%')
                            ->default(100.00)
                            ->required(),
                        Forms\Components\TextInput::make('basic_coverage_pct')
                            ->label('Basic Restorative (Fillings/Endo) %')
                            ->numeric()
                            ->suffix('%')
                            ->default(80.00)
                            ->required(),
                        Forms\Components\TextInput::make('major_coverage_pct')
                            ->label('Major (Crowns/Bridges/Implants) %')
                            ->numeric()
                            ->suffix('%')
                            ->default(50.00)
                            ->required(),
                        Forms\Components\TextInput::make('ortho_coverage_pct')
                            ->label('Orthodontic Coverage %')
                            ->numeric()
                            ->suffix('%')
                            ->default(50.00),
                        Forms\Components\TextInput::make('ortho_lifetime_max')
                            ->label('Ortho Lifetime Max ($)')
                            ->numeric()
                            ->prefix('$')
                            ->default(1500.00),
                    ])->columns(4),

                Forms\Components\Section::make('Policy Term & Validity')
                    ->schema([
                        Forms\Components\DatePicker::make('effective_date')
                            ->label('Effective Date')
                            ->default(now()->startOfYear()),
                        Forms\Components\DatePicker::make('expiration_date')
                            ->label('Renewal / Expiration Date')
                            ->default(now()->endOfYear()),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Policy Currently Active')
                            ->default(true),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('patient.first_name')
                    ->label('Patient')
                    ->formatStateUsing(fn (PatientInsurancePolicy $record) => "{$record->patient?->first_name} {$record->patient?->last_name}")
                    ->description(fn (PatientInsurancePolicy $record) => "File: {$record->patient?->file_number}")
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('insuranceProvider.name')
                    ->label('Insurance Payer')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('policy_number')
                    ->label('Policy #')
                    ->searchable()
                    ->copyable()
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('plan_type')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('annual_maximum')
                    ->label('Annual Max')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('coverage_summary')
                    ->label('Coverage (Prev/Basic/Major)')
                    ->state(fn (PatientInsurancePolicy $record) => "{$record->preventive_coverage_pct}% / {$record->basic_coverage_pct}% / {$record->major_coverage_pct}%")
                    ->badge()
                    ->color('success'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('insurance_provider_id')
                    ->label('Carrier')
                    ->relationship('insuranceProvider', 'name'),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Policies'),
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
            'index' => Pages\ListPatientInsurancePolicies::route('/'),
            'create' => Pages\CreatePatientInsurancePolicy::route('/create'),
            'edit' => Pages\EditPatientInsurancePolicy::route('/{record}/edit'),
        ];
    }
}
