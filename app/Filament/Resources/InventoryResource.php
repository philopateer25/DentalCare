<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InventoryResource\Pages;
use App\Filament\Resources\InventoryResource\RelationManagers\BatchesRelationManager;
use App\Models\InventoryItem;
use App\Models\Practice;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InventoryResource extends Resource
{
    protected static ?string $model = InventoryItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationGroup = 'Clinic Operations';

    protected static ?string $navigationLabel = 'Inventory & Tools';

    protected static ?string $modelLabel = 'Inventory Item';

    protected static ?string $pluralModelLabel = 'Inventory & Dental Tools';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Inventory Details')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('General Information')
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Item / Tool Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('e.g. Gracey Curette #13/14, Filtek Z250 Composite A2'),
                                Forms\Components\TextInput::make('brand')
                                    ->label('Brand / Manufacturer')
                                    ->maxLength(150)
                                    ->placeholder('e.g. Hu-Friedy, 3M Oral Care, Dentsply, Kerr'),
                                Forms\Components\Select::make('supplier_id')
                                    ->label('Preferred Supplier')
                                    ->options(Supplier::query()->where('is_active', true)->pluck('name', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')->required()->label('Supplier Name'),
                                        Forms\Components\TextInput::make('contact_person')->label('Contact Person'),
                                        Forms\Components\TextInput::make('phone')->tel(),
                                        Forms\Components\TextInput::make('email')->email(),
                                    ]),
                                Forms\Components\Select::make('category')
                                    ->label('Clinical Category')
                                    ->options([
                                        'Diagnostic & Examination' => 'Diagnostic & Examination (Mirrors, Probes, Explorers)',
                                        'Operative & Restorative' => 'Operative & Restorative (Composites, Bonding, Amalgams)',
                                        'Endodontics' => 'Endodontics (Files, Motors, Obturation, Sealers)',
                                        'Periodontics & Hygiene' => 'Periodontics & Hygiene (Scalers, Curettes, Bone Grafts)',
                                        'Oral Surgery & Extractions' => 'Oral Surgery & Extractions (Forceps, Elevators, Sutures)',
                                        'Prosthodontics & Impression' => 'Prosthodontics & Impression (Alginate, Cements, Crowns)',
                                        'Orthodontics' => 'Orthodontics (Brackets, Archwires, Pliers, Elastomerics)',
                                        'Pediatric Dentistry' => 'Pediatric Dentistry (Stainless Steel Crowns, Fluoride)',
                                        'Dental Implantology' => 'Dental Implantology (Implants, Drivers, Abutments, Motors)',
                                        'Sterilization & Infection Control' => 'Sterilization & Infection Control (Autoclaves, Pouches, Solutions)',
                                        'Local Anesthesia & Pharma' => 'Local Anesthesia & Pharma (Articaine, Lidocaine, Needles)',
                                        'Radiology & Imaging' => 'Radiology & Imaging (Phosphor Plates, Sensors, Positioners)',
                                        'Dental Equipment & Handpieces' => 'Dental Equipment & Handpieces (High-Speed, Low-Speed, Turbines)',
                                        'Lab & CAD/CAM Supplies' => 'Lab & CAD/CAM Supplies (Zirconia Blocks, PMMA, Wax)',
                                        'Consumables & PPE' => 'Consumables & PPE (Gloves, Masks, Bibs, Cotton Rolls, Saliva Ejectors)',
                                        'Rare & Specialty Instruments' => 'Rare & Specialty Instruments (Microsurgical, Sinus Lift, Tunneling)',
                                    ])
                                    ->required()
                                    ->searchable(),
                                Forms\Components\TextInput::make('sub_category')
                                    ->label('Sub-Category / Type')
                                    ->placeholder('e.g. Rotary Files, Matrix Systems, Ultrasonic Inserts, Hemostats'),
                                Forms\Components\Select::make('practice_id')
                                    ->label('Practice / Clinic')
                                    ->relationship('practice', 'name')
                                    ->default(fn () => Practice::firstOrCreate(['name' => 'Main Clinic'])->id)
                                    ->required(),
                            ])->columns(2),

                        Forms\Components\Tabs\Tab::make('Pricing & Identification')
                            ->icon('heroicon-o-banknotes')
                            ->schema([
                                Forms\Components\TextInput::make('sku')
                                    ->label('SKU / Catalog Number')
                                    ->maxLength(50)
                                    ->unique(ignoreRecord: true)
                                    ->placeholder('e.g. HF-SG1314, 3M-1370A2'),
                                Forms\Components\TextInput::make('barcode')
                                    ->label('Barcode / UPC / GTIN')
                                    ->maxLength(100)
                                    ->placeholder('Barcode scan or GTIN'),
                                Forms\Components\Select::make('unit')
                                    ->label('Unit of Measurement')
                                    ->options([
                                        'pcs' => 'Piece (pcs)',
                                        'box' => 'Box',
                                        'set' => 'Set / Kit',
                                        'pack' => 'Pack',
                                        'syringes' => 'Syringes',
                                        'carpules' => 'Carpules / Cartridges',
                                        'bottles' => 'Bottles',
                                        'vials' => 'Vials',
                                        'rolls' => 'Rolls',
                                        'tubes' => 'Tubes',
                                        'ml' => 'Milliliters (ml)',
                                        'grams' => 'Grams (g)',
                                        'pairs' => 'Pairs',
                                    ])
                                    ->default('pcs')
                                    ->required(),
                                Forms\Components\TextInput::make('unit_price')
                                    ->label('Unit Cost Price')
                                    ->numeric()
                                    ->prefix('$')
                                    ->default(0.00)
                                    ->required(),
                                Forms\Components\TextInput::make('selling_price')
                                    ->label('Procedure / Patient Billing Price (Optional)')
                                    ->numeric()
                                    ->prefix('$')
                                    ->helperText('If billable directly on patient invoice'),
                            ])->columns(2),

                        Forms\Components\Tabs\Tab::make('Storage & Inventory Control')
                            ->icon('heroicon-o-building-storefront')
                            ->schema([
                                Forms\Components\TextInput::make('storage_location')
                                    ->label('Storage Location / Bin / Cabinet')
                                    ->placeholder('e.g. Sterilization Room Shelf 2, Operatory 1 Cabinet B, Refrigerator'),
                                Forms\Components\TextInput::make('min_reorder_level')
                                    ->label('Minimum Stock Alert Level')
                                    ->helperText('Trigger low stock warning when remaining units drop below this number')
                                    ->numeric()
                                    ->default(5)
                                    ->required(),
                                Forms\Components\TextInput::make('reorder_quantity')
                                    ->label('Standard Reorder Quantity')
                                    ->helperText('Quantity to request upon supplier purchase order')
                                    ->numeric()
                                    ->default(20)
                                    ->required(),
                                Forms\Components\Toggle::make('has_expiration')
                                    ->label('Requires Expiration Tracking')
                                    ->helperText('Enable if this item (anesthetics, composites, cement, biologicals) has expiry dates')
                                    ->default(true),
                                Forms\Components\Toggle::make('is_active')
                                    ->label('Active in Clinic Catalog')
                                    ->default(true),
                                Forms\Components\Textarea::make('description')
                                    ->label('Clinical Notes & Tool Instructions')
                                    ->placeholder('Usage guidelines, maintenance instructions, sterilization temperature, etc.')
                                    ->columnSpanFull(),
                            ])->columns(2),
                    ])->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->placeholder('N/A'),
                Tables\Columns\TextColumn::make('name')
                    ->label('Item & Tool Name')
                    ->searchable(['name', 'brand', 'sku'])
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (InventoryItem $record): ?string => $record->brand ? "Brand: {$record->brand}" : null),
                Tables\Columns\TextColumn::make('category')
                    ->label('Category')
                    ->badge()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->searchable()
                    ->placeholder('Direct / Multi')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('unit_price')
                    ->label('Unit Cost')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_stock')
                    ->label('Available Stock')
                    ->state(fn (InventoryItem $record): string => $record->total_stock . ' ' . $record->unit)
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('stock_status')
                    ->label('Stock Status')
                    ->badge()
                    ->state(fn (InventoryItem $record): string => $record->stock_status)
                    ->color(fn (string $state): string => match ($state) {
                        'Out of Stock' => 'danger',
                        'Low Stock' => 'warning',
                        'In Stock' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('earliest_expiry')
                    ->label('Earliest Expiry')
                    ->date()
                    ->placeholder('No Expiry')
                    ->sortable()
                    ->badge()
                    ->color(function ($state): string {
                        if (! $state) {
                            return 'gray';
                        }
                        $date = \Carbon\Carbon::parse($state);
                        if ($date->isPast()) {
                            return 'danger';
                        }
                        if ($date->diffInDays(now()) <= 60) {
                            return 'warning';
                        }

                        return 'success';
                    })
                    ->toggleable(),
                Tables\Columns\TextColumn::make('storage_location')
                    ->label('Location')
                    ->placeholder('Unassigned')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->options([
                        'Diagnostic & Examination' => 'Diagnostic & Examination',
                        'Operative & Restorative' => 'Operative & Restorative',
                        'Endodontics' => 'Endodontics',
                        'Periodontics & Hygiene' => 'Periodontics & Hygiene',
                        'Oral Surgery & Extractions' => 'Oral Surgery & Extractions',
                        'Prosthodontics & Impression' => 'Prosthodontics & Impression',
                        'Orthodontics' => 'Orthodontics',
                        'Pediatric Dentistry' => 'Pediatric Dentistry',
                        'Dental Implantology' => 'Dental Implantology',
                        'Sterilization & Infection Control' => 'Sterilization & Infection Control',
                        'Local Anesthesia & Pharma' => 'Local Anesthesia & Pharma',
                        'Radiology & Imaging' => 'Radiology & Imaging',
                        'Dental Equipment & Handpieces' => 'Dental Equipment & Handpieces',
                        'Lab & CAD/CAM Supplies' => 'Lab & CAD/CAM Supplies',
                        'Consumables & PPE' => 'Consumables & PPE',
                        'Rare & Specialty Instruments' => 'Rare & Specialty Instruments',
                    ]),
                Tables\Filters\SelectFilter::make('supplier_id')
                    ->label('Supplier')
                    ->relationship('supplier', 'name'),
                Tables\Filters\Filter::make('low_stock')
                    ->label('Low / Out of Stock')
                    ->query(fn (Builder $query): Builder => $query->where(function ($q) {
                        $q->whereDoesntHave('batches')
                            ->orWhereRaw('(SELECT COALESCE(SUM(quantity_remaining), 0) FROM inventory_batches WHERE inventory_batches.inventory_item_id = inventory_items.id) <= inventory_items.min_reorder_level');
                    })),
                Tables\Filters\Filter::make('expiring_soon')
                    ->label('Expiring in 60 Days / Expired')
                    ->query(fn (Builder $query): Builder => $query->whereHas('batches', function ($q) {
                        $q->whereNotNull('expiry_date')
                            ->where('quantity_remaining', '>', 0)
                            ->where('expiry_date', '<=', now()->addDays(60));
                    })),
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
            BatchesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventories::route('/'),
            'create' => Pages\CreateInventory::route('/create'),
            'edit' => Pages\EditInventory::route('/{record}/edit'),
        ];
    }
}
