<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InstallmentPlanResource\Pages;
use App\Filament\Resources\InstallmentPlanResource\RelationManagers\SchedulesRelationManager;
use App\Models\InstallmentPlan;
use App\Models\Invoice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class InstallmentPlanResource extends Resource
{
    protected static ?string $model = InstallmentPlan::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar';

    protected static ?string $navigationGroup = 'Finance & Treasury';

    protected static ?string $navigationLabel = 'Patient Financing & Credit';

    protected static ?string $modelLabel = 'Installment Plan';

    protected static ?string $pluralModelLabel = 'Patient Financing Contracts';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Financing Terms & Contract')
                    ->schema([
                        Forms\Components\Select::make('invoice_id')
                            ->label('Target Invoice')
                            ->relationship('invoice', 'invoice_number')
                            ->getOptionLabelFromRecordUsing(fn (Invoice $record) => "{$record->invoice_number} - {$record->patient?->first_name} {$record->patient?->last_name} (Total: \${$record->total_amount})")
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if ($invoice = Invoice::find($state)) {
                                    $set('total_funded_amount', $invoice->total_amount);
                                }
                            }),
                        Forms\Components\TextInput::make('total_funded_amount')
                            ->label('Total Financed Principal ($)')
                            ->numeric()
                            ->prefix('$')
                            ->required(),
                        Forms\Components\TextInput::make('down_payment')
                            ->label('Upfront Down Payment ($)')
                            ->numeric()
                            ->prefix('$')
                            ->default(0.00),
                        Forms\Components\TextInput::make('number_of_installments')
                            ->label('Tenure (# of Installments)')
                            ->numeric()
                            ->default(6)
                            ->suffix('installments')
                            ->required(),
                        Forms\Components\Select::make('frequency')
                            ->label('Repayment Frequency')
                            ->options([
                                'monthly' => 'Monthly Payments',
                                'bi_weekly' => 'Bi-Weekly (Every 2 Weeks)',
                                'weekly' => 'Weekly Payments',
                                'milestone' => 'Milestone / Phase-Based',
                            ])
                            ->default('monthly')
                            ->required(),
                        Forms\Components\Select::make('status')
                            ->label('Contract Status')
                            ->options([
                                'active' => 'Active (In Good Standing)',
                                'completed' => 'Completed / Fully Settled',
                                'defaulted' => 'Defaulted / Collections',
                            ])
                            ->default('active')
                            ->required(),
                        Forms\Components\Textarea::make('notes')
                            ->label('Financing Agreement & Guarantor Notes')
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('invoice.invoice_number')
                    ->label('Invoice #')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('invoice.patient.first_name')
                    ->label('Patient')
                    ->formatStateUsing(fn (InstallmentPlan $record) => "{$record->invoice?->patient?->first_name} {$record->invoice?->patient?->last_name}")
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_funded_amount')
                    ->label('Financed Amount')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('down_payment')
                    ->label('Down Payment')
                    ->money('USD')
                    ->color('success'),
                Tables\Columns\TextColumn::make('number_of_installments')
                    ->label('Tenure')
                    ->formatStateUsing(fn ($state, $record) => "{$state}x ({$record->frequency})"),
                Tables\Columns\TextColumn::make('progress_percentage')
                    ->label('Repayment Progress')
                    ->formatStateUsing(fn ($state) => "{$state}%")
                    ->badge()
                    ->color(fn ($state) => (float)$state >= 100 ? 'success' : ((float)$state >= 50 ? 'info' : 'warning')),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'info',
                        'completed' => 'success',
                        'defaulted' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'completed' => 'Completed',
                        'defaulted' => 'Defaulted',
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

    public static function getRelations(): array
    {
        return [
            SchedulesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInstallmentPlans::route('/'),
            'create' => Pages\CreateInstallmentPlan::route('/create'),
            'edit' => Pages\EditInstallmentPlan::route('/{record}/edit'),
        ];
    }
}
