<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InsuranceClaimResource\Pages;
use App\Models\InsuranceClaim;
use App\Models\InsuranceProvider;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\PatientInsurancePolicy;
use App\Models\Practice;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class InsuranceClaimResource extends Resource
{
    protected static ?string $model = InsuranceClaim::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Insurance & Claims';

    protected static ?string $navigationLabel = 'Claims & Pre-Authorizations';

    protected static ?string $modelLabel = 'Insurance Claim';

    protected static ?string $pluralModelLabel = 'Insurance Claims & EOBs';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Claim Identification & Provider Links')
                    ->schema([
                        Forms\Components\TextInput::make('claim_number')
                            ->label('Claim #')
                            ->disabled()
                            ->dehydrated()
                            ->placeholder('Auto-generated (e.g. CLM-2026-0001)'),
                        Forms\Components\Select::make('patient_id')
                            ->label('Patient')
                            ->relationship('patient', 'first_name')
                            ->getOptionLabelFromRecordUsing(fn (Patient $record) => "{$record->first_name} {$record->last_name} ({$record->file_number})")
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if ($policy = PatientInsurancePolicy::where('patient_id', $state)->where('is_active', true)->first()) {
                                    $set('insurance_provider_id', $policy->insurance_provider_id);
                                    $set('patient_insurance_policy_id', $policy->id);
                                }
                            }),
                        Forms\Components\Select::make('insurance_provider_id')
                            ->label('Insurance Carrier / Payer')
                            ->relationship('insuranceProvider', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('patient_insurance_policy_id')
                            ->label('Active Patient Policy')
                            ->relationship('policy', 'policy_number')
                            ->searchable()
                            ->placeholder('Select Policy'),
                        Forms\Components\Select::make('doctor_id')
                            ->label('Treating Dentist (NPI / Provider)')
                            ->relationship('doctor', 'name')
                            ->default(fn () => User::first()?->id)
                            ->searchable()
                            ->required(),
                        Forms\Components\Select::make('claim_type')
                            ->options([
                                'standard_claim' => 'Standard Post-Treatment Claim',
                                'pre_authorization' => 'Pre-Authorization / Treatment Estimate',
                            ])
                            ->default('standard_claim')
                            ->required(),
                        Forms\Components\Select::make('practice_id')
                            ->relationship('practice', 'name')
                            ->default(fn () => Practice::firstOrCreate(['name' => 'Main Clinic'])->id)
                            ->required(),
                    ])->columns(3),

                Forms\Components\Section::make('Adjudication, Benefits & EOB Breakdown')
                    ->schema([
                        Forms\Components\TextInput::make('total_claimed_amount')
                            ->label('Total Billed / Claimed ($)')
                            ->numeric()
                            ->prefix('$')
                            ->required(),
                        Forms\Components\TextInput::make('estimated_insurance_amount')
                            ->label('Estimated Insurance Benefit ($)')
                            ->numeric()
                            ->prefix('$')
                            ->default(0.00),
                        Forms\Components\TextInput::make('patient_copay_amount')
                            ->label('Patient Co-Pay / Out-of-Pocket ($)')
                            ->numeric()
                            ->prefix('$')
                            ->default(0.00),
                        Forms\Components\TextInput::make('actual_paid_amount')
                            ->label('Actual Paid by Insurance ($)')
                            ->numeric()
                            ->prefix('$')
                            ->default(0.00),
                        Forms\Components\TextInput::make('eob_reference_number')
                            ->label('EOB (Explanation of Benefits) Ref #')
                            ->placeholder('e.g. EOB-99381204'),
                        Forms\Components\Select::make('status')
                            ->options([
                                'draft' => 'Draft Claim',
                                'submitted_edi' => 'Submitted via Electronic EDI',
                                'under_review' => 'Under Carrier Review / In Adjudication',
                                'approved_paid' => 'Approved & Paid in Full',
                                'partially_approved' => 'Partially Approved (Adjusted)',
                                'denied' => 'Denied / Rejected',
                                'appealed' => 'Claim Under Appeal',
                            ])
                            ->default('submitted_edi')
                            ->required(),
                        Forms\Components\DateTimePicker::make('submitted_at')
                            ->label('EDI Submission Date & Time')
                            ->default(now()),
                        Forms\Components\DateTimePicker::make('adjudicated_at')
                            ->label('Payment / Settlement Date'),
                    ])->columns(4),

                Forms\Components\Section::make('Clinical Narrative & Denial Management')
                    ->schema([
                        Forms\Components\Textarea::make('treatment_summary')
                            ->label('Treatment Description & CDT Procedure Codes')
                            ->placeholder('e.g. D2740 Porcelain/Ceramic Crown (#16), D0150 Comprehensive Oral Exam, D0210 Full Mouth X-Rays.')
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('denial_reason')
                            ->label('Carrier Denial / Reduction Reason (if applicable)')
                            ->placeholder('e.g. Frequency limitation exceeded (cleaning within 6 months), Pre-op X-rays missing.')
                            ->columnSpanFull(),
                    ])->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('claim_number')
                    ->label('Claim #')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('patient.first_name')
                    ->label('Patient')
                    ->formatStateUsing(fn (InsuranceClaim $record) => "{$record->patient?->first_name} {$record->patient?->last_name}")
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),
                Tables\Columns\TextColumn::make('insuranceProvider.name')
                    ->label('Insurance Payer')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('claim_type')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn ($state) => $state === 'pre_authorization' ? 'Pre-Auth' : 'Claim'),
                Tables\Columns\TextColumn::make('total_claimed_amount')
                    ->label('Claimed')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('actual_paid_amount')
                    ->label('Paid by Carrier')
                    ->money('USD')
                    ->color('success')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'submitted_edi' => 'info',
                        'under_review' => 'warning',
                        'approved_paid' => 'success',
                        'partially_approved' => 'primary',
                        'denied' => 'danger',
                        'appealed' => 'secondary',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('submitted_at')
                    ->label('Submitted')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'submitted_edi' => 'Submitted EDI',
                        'under_review' => 'Under Review',
                        'approved_paid' => 'Approved & Paid',
                        'denied' => 'Denied',
                    ]),
                Tables\Filters\SelectFilter::make('insurance_provider_id')
                    ->label('Carrier')
                    ->relationship('insuranceProvider', 'name'),
                Tables\Filters\SelectFilter::make('claim_type')
                    ->options([
                        'standard_claim' => 'Standard Claim',
                        'pre_authorization' => 'Pre-Authorization',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('markPaid')
                    ->label('Record EOB Payment')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (InsuranceClaim $record) => $record->status !== 'approved_paid')
                    ->action(fn (InsuranceClaim $record) => $record->update([
                        'status' => 'approved_paid',
                        'actual_paid_amount' => $record->estimated_insurance_amount > 0 ? $record->estimated_insurance_amount : $record->total_claimed_amount,
                        'adjudicated_at' => now(),
                    ])),
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
            'index' => Pages\ListInsuranceClaims::route('/'),
            'create' => Pages\CreateInsuranceClaim::route('/create'),
            'edit' => Pages\EditInsuranceClaim::route('/{record}/edit'),
        ];
    }
}
