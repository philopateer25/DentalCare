<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupplierResource\Pages;
use App\Filament\Resources\SupplierResource\RelationManagers\InventoryItemsRelationManager;
use App\Models\Practice;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Clinic Operations';

    protected static ?string $navigationLabel = 'Suppliers';

    protected static ?string $modelLabel = 'Supplier';

    protected static ?string $pluralModelLabel = 'Suppliers';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Supplier & Company Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Supplier / Vendor Name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('company_name')
                            ->label('Legal Business Name')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('contact_person')
                            ->label('Account Rep / Contact Person')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('tax_number')
                            ->label('Tax ID / VAT Number')
                            ->maxLength(100),
                        Forms\Components\Select::make('practice_id')
                            ->label('Practice')
                            ->relationship('practice', 'name')
                            ->default(fn () => Practice::firstOrCreate(['name' => 'Main Clinic'])->id),
                    ])->columns(2),

                Forms\Components\Section::make('Contact Information & Logistics')
                    ->schema([
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->maxLength(100),
                        Forms\Components\TextInput::make('website')
                            ->url()
                            ->prefix('https://')
                            ->maxLength(255),
                        Forms\Components\Select::make('payment_terms')
                            ->options([
                                'Net 15' => 'Net 15 Days',
                                'Net 30' => 'Net 30 Days',
                                'Net 60' => 'Net 60 Days',
                                'COD' => 'Cash On Delivery (COD)',
                                'Prepayment' => 'Prepayment / Wire',
                                'Credit Card' => 'Credit Card',
                            ])
                            ->default('Net 30'),
                        Forms\Components\TextInput::make('lead_time_days')
                            ->label('Average Delivery Lead Time (Days)')
                            ->numeric()
                            ->default(3)
                            ->suffix('days'),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active Supplier')
                            ->default(true),
                        Forms\Components\Textarea::make('address')
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('notes')
                            ->label('Internal Notes / Account Notes')
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Supplier')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('contact_person')
                    ->label('Contact')
                    ->searchable()
                    ->placeholder('N/A'),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->placeholder('N/A'),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->placeholder('N/A'),
                Tables\Columns\TextColumn::make('inventory_items_count')
                    ->label('Catalog Items')
                    ->counts('inventoryItems')
                    ->badge()
                    ->color('info')
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_terms')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('lead_time_days')
                    ->label('Lead Time')
                    ->suffix(' days')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),
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
            InventoryItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSuppliers::route('/'),
            'create' => Pages\CreateSupplier::route('/create'),
            'edit' => Pages\EditSupplier::route('/{record}/edit'),
        ];
    }
}
