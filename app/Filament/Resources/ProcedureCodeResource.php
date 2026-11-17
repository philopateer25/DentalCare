<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProcedureCodeResource\Pages;
use App\Models\ProcedureCategory;
use App\Models\ProcedureCode;
use App\Services\CurrencyHelper;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProcedureCodeResource extends Resource
{
    protected static ?string $model = ProcedureCode::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Clinical Management';

    protected static ?string $navigationLabel = 'Procedures & Pricing';

    protected static ?string $modelLabel = 'Procedure Code';

    protected static ?string $pluralModelLabel = 'Fee Schedule & Procedures';

    protected static ?int $navigationSort = 10;

    protected static bool $isScopedToTenant = false;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Procedure & Fee Details')
                    ->description('Configure clinical code, title, duration and default fee schedule.')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Procedure / CDT Code')
                            ->required()
                            ->maxLength(50)
                            ->placeholder('e.g. D2140 or COMP-01')
                            ->unique(ignoreRecord: true),

                        Forms\Components\Select::make('category_id')
                            ->label('Clinical Category')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(100),
                                Forms\Components\TextInput::make('code')
                                    ->required()
                                    ->maxLength(50)
                                    ->unique('procedure_categories', 'code'),
                                Forms\Components\Textarea::make('description')
                                    ->rows(2),
                            ]),

                        Forms\Components\TextInput::make('title')
                            ->label('Procedure Title / Treatment Name')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('standard_fee')
                            ->label('Standard Fee / Default Price')
                            ->numeric()
                            ->prefix(CurrencyHelper::symbol())
                            ->required()
                            ->default(0.00)
                            ->step('0.01'),

                        Forms\Components\TextInput::make('estimated_duration_minutes')
                            ->label('Est. Chairside Duration')
                            ->numeric()
                            ->suffix('minutes')
                            ->default(30)
                            ->required(),

                        Forms\Components\Textarea::make('description')
                            ->label('Clinical Description & Guidelines')
                            ->rows(3)
                            ->columnSpanFull()
                            ->placeholder('Optional clinical notes or instructions for this procedure.'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('title')
                    ->label('Procedure Title')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                Tables\Columns\TextInputColumn::make('standard_fee')
                    ->label('Price (' . CurrencyHelper::symbol() . ')')
                    ->sortable()
                    ->type('number')
                    ->step('0.50')
                    ->rules(['numeric', 'min:0']),

                Tables\Columns\TextColumn::make('estimated_duration_minutes')
                    ->label('Duration')
                    ->suffix(' min')
                    ->sortable()
                    ->alignEnd(),
            ])
            ->defaultSort('code')
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name')
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListProcedureCodes::route('/'),
            'create' => Pages\CreateProcedureCode::route('/create'),
            'edit' => Pages\EditProcedureCode::route('/{record}/edit'),
        ];
    }
}
