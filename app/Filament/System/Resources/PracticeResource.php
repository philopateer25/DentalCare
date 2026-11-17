<?php

namespace App\Filament\System\Resources;

use App\Filament\System\Resources\PracticeResource\Pages;
use App\Models\Practice;
use App\Services\CurrencyHelper;
use App\Services\FeatureManager;
use App\Services\LanguageHelper;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PracticeResource extends Resource
{
    protected static ?string $model = Practice::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    
    protected static ?string $modelLabel = 'Clinic / Tenant';
    protected static ?string $navigationLabel = 'Clinics';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Clinic Information')
                    ->schema([
                        Forms\Components\Select::make('type')
                            ->label('Clinic Type')
                            ->options(Practice::TYPES)
                            ->default('dental')
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, $state) {
                                $features = $get('features') ?? [];
                                if ($state !== 'dental') {
                                    $features = array_diff($features, ['3d_model']);
                                } else {
                                    if (!in_array('3d_model', $features)) {
                                        $features[] = '3d_model';
                                    }
                                }
                                $set('features', array_values($features));
                            }),
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('tax_id')
                            ->maxLength(255),
                        Forms\Components\Select::make('currency')
                            ->options(CurrencyHelper::getOptions())
                            ->searchable()
                            ->default('EGP')
                            ->required(),
                        Forms\Components\Select::make('locale')
                            ->label('Default Language')
                            ->options(LanguageHelper::getOptions())
                            ->default('en')
                            ->required(),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Is Active')
                            ->default(true),
                    ])->columns(2),

                Forms\Components\Section::make('SaaS Licensing & Tier')
                    ->schema([
                        Forms\Components\Select::make('package_tier')
                            ->label('Subscription Tier / Package')
                            ->options(FeatureManager::getPackageOptions())
                            ->default('amigo_ultra')
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, $state) {
                                if ($state && $state !== 'custom') {
                                    $package = FeatureManager::getPackage($state);
                                    if ($package) {
                                        $set('chair_limit', $package['chair_limit']);
                                        $set('xray_limit', $package['xray_limit']);
                                        $set('features', $package['features']);
                                    }
                                }
                            }),
                        Forms\Components\Select::make('license_status')
                            ->options([
                                'active' => 'Active',
                                'suspended' => 'Suspended (Payment Due)',
                            ])
                            ->required()
                            ->default('active')
                            ->native(false),
                        Forms\Components\TextInput::make('license_key')
                            ->label('License Key')
                            ->password()
                            ->revealable()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('chair_limit')
                            ->label('Max Allowed Chairs (Operatories)')
                            ->numeric()
                            ->minValue(1)
                            ->default(5)
                            ->required()
                            ->helperText('amigo: 2 | Pro: 3 (+1/$15) | Ultra: 5 (+1/$20)'),
                        Forms\Components\TextInput::make('xray_limit')
                            ->label('Max Allowed X-Ray Workstations')
                            ->numeric()
                            ->minValue(0)
                            ->default(3)
                            ->required()
                            ->helperText('amigo: 0 | Pro: 1 | Ultra: 3 (+1/$10)'),
                        Forms\Components\CheckboxList::make('features')
                            ->label('Active Feature Flags (Overrides / Customizations)')
                            ->options(FeatureManager::getOptions())
                            ->columns(3)
                            ->columnSpanFull()
                            ->live(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('package_tier')
                    ->label('Tier')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'amigo' => 'Amigo',
                        'amigo_pro' => 'Amigo Pro',
                        'amigo_ultra' => 'Amigo Ultra',
                        default => 'Custom',
                    })
                    ->color(fn ($state) => match ($state) {
                        'amigo_ultra' => 'success',
                        'amigo_pro' => 'info',
                        'amigo' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('chair_limit')
                    ->label('Chairs')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('license_status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'suspended' => 'danger',
                        default => 'warning',
                    }),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
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
            PracticeResource\RelationManagers\UsersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPractices::route('/'),
            'create' => Pages\CreatePractice::route('/create'),
            'edit' => Pages\EditPractice::route('/{record}/edit'),
        ];
    }
}
