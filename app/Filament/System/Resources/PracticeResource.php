<?php

namespace App\Filament\System\Resources;

use App\Filament\System\Resources\PracticeResource\Pages;
use App\Models\Practice;
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
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('tax_id')
                            ->maxLength(255),
                        Forms\Components\Select::make('currency')
                            ->options(\App\Services\CurrencyHelper::getOptions())
                            ->searchable()
                            ->default('EGP')
                            ->required(),
                        Forms\Components\Select::make('locale')
                            ->label('Default Language')
                            ->options(\App\Services\LanguageHelper::getOptions())
                            ->default('en')
                            ->required(),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Is Active')
                            ->default(true),
                    ])->columns(2),

                Forms\Components\Section::make('SaaS Licensing & Features')
                    ->schema([
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
                        Forms\Components\CheckboxList::make('features')
                            ->label('Enabled Features')
                            ->options(\App\Services\FeatureManager::getOptions())
                            ->columns(3)
                            ->columnSpanFull(),
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
            //
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
