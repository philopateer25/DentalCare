<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InsuranceProviderResource\Pages;
use App\Models\InsuranceProvider;
use App\Models\Practice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class InsuranceProviderResource extends Resource
{
    protected static ?string $model = InsuranceProvider::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Insurance & Claims';

    protected static ?string $navigationLabel = 'Insurance Payers & TPAs';

    protected static ?string $modelLabel = 'Insurance Provider';

    protected static ?string $pluralModelLabel = 'Insurance Payers & TPAs';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Payer Identity & Electronic Claims (EDI)')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Insurance Company / TPA Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g. Delta Dental Premier, MetLife Dental, Cigna PPO, Bupa Global'),
                        Forms\Components\TextInput::make('payer_id')
                            ->label('Electronic Claims Payer ID (EDI #)')
                            ->placeholder('e.g. DELTA-01, METLIFE-99')
                            ->required(),
                        Forms\Components\TextInput::make('contact_person')
                            ->label('Provider Relations Rep')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('standard_reimbursement_days')
                            ->label('Avg Reimbursement Turnaround (Days)')
                            ->numeric()
                            ->default(14)
                            ->suffix('days'),
                        Forms\Components\Select::make('practice_id')
                            ->relationship('practice', 'name')
                            ->default(fn () => Practice::firstOrCreate(['name' => 'Main Clinic'])->id)
                            ->required(),
                    ])->columns(3),

                Forms\Components\Section::make('Claims Submission & Direct Portal')
                    ->schema([
                        Forms\Components\TextInput::make('phone')
                            ->label('Provider Claims Phone')
                            ->tel(),
                        Forms\Components\TextInput::make('claims_email')
                            ->label('Electronic Claims / EOB Email')
                            ->email(),
                        Forms\Components\TextInput::make('portal_url')
                            ->label('Online Claims / Pre-Auth Portal URL')
                            ->url()
                            ->prefix('https://')
                            ->placeholder('provider.deltadental.com'),
                        Forms\Components\Textarea::make('claims_address')
                            ->label('Physical Paper Claims Submission Address')
                            ->columnSpanFull(),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active Insurance Carrier')
                            ->default(true),
                        Forms\Components\Textarea::make('notes')
                            ->label('Fee Schedule & Coverage Policy Notes')
                            ->columnSpanFull(),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('payer_id')
                    ->label('Payer ID')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('name')
                    ->label('Insurance Company')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('contact_person')
                    ->label('Rep')
                    ->searchable()
                    ->placeholder('N/A'),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable(),
                Tables\Columns\TextColumn::make('standard_reimbursement_days')
                    ->label('Turnaround')
                    ->suffix(' days')
                    ->sortable(),
                Tables\Columns\TextColumn::make('policies_count')
                    ->label('Active Patients')
                    ->counts('policies')
                    ->badge()
                    ->color('info'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Carriers'),
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
            'index' => Pages\ListInsuranceProviders::route('/'),
            'create' => Pages\CreateInsuranceProvider::route('/create'),
            'edit' => Pages\EditInsuranceProvider::route('/{record}/edit'),
        ];
    }
}
