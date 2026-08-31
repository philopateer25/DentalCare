<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DentalLabResource\Pages;
use App\Filament\Resources\DentalLabResource\RelationManagers\LabOrdersRelationManager;
use App\Models\DentalLab;
use App\Models\Practice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DentalLabResource extends Resource
{
    protected static ?string $model = DentalLab::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'Lab & Prosthetics';

    protected static ?string $navigationLabel = 'Lab Partners';

    protected static ?string $modelLabel = 'Dental Lab';

    protected static ?string $pluralModelLabel = 'Dental Labs & Milling Centers';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Laboratory Profile & Account')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Dental Lab / Center Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g. Glidewell Dental Lab, MicroDental Laboratories'),
                        Forms\Components\Select::make('lab_type')
                            ->options([
                                'Commercial Lab' => 'Full-Service Commercial Lab',
                                'In-House CAD/CAM' => 'In-House CAD/CAM Milling Center',
                                'Crown & Bridge Specialist' => 'Crown & Bridge Specialist',
                                'Clear Aligners & Ortho' => 'Clear Aligners & Orthodontic Lab',
                                'Removable Prosthetics' => 'Dentures & Removable Prosthetics Lab',
                                'Implant & Surgical Center' => 'Implant Bar & Surgical Guide Center',
                            ])
                            ->default('Commercial Lab')
                            ->required(),
                        Forms\Components\TextInput::make('account_number')
                            ->label('Clinic Account # with Lab')
                            ->placeholder('e.g. ACC-884920'),
                        Forms\Components\TextInput::make('contact_person')
                            ->label('Primary Lab Tech / Account Rep')
                            ->maxLength(255),
                        Forms\Components\Select::make('practice_id')
                            ->relationship('practice', 'name')
                            ->default(fn () => Practice::firstOrCreate(['name' => 'Main Clinic'])->id)
                            ->required(),
                    ])->columns(2),

                Forms\Components\Section::make('Contact Information & Digital Portal')
                    ->schema([
                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->maxLength(100),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('portal_url')
                            ->label('Lab Digital Portal / Cloud Inbox URL')
                            ->url()
                            ->prefix('https://')
                            ->placeholder('portal.glidewelldental.com'),
                        Forms\Components\TextInput::make('courier_service')
                            ->label('Dedicated Courier / Pickup Service')
                            ->placeholder('e.g. Lab Courier (Daily 2PM), FedEx Priority Overnight, DHL'),
                        Forms\Components\Textarea::make('address')
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Logistics, Performance & Pricing')
                    ->schema([
                        Forms\Components\TextInput::make('standard_turnaround_days')
                            ->label('Standard Turnaround Time (Days)')
                            ->numeric()
                            ->default(7)
                            ->suffix('days')
                            ->required(),
                        Forms\Components\Select::make('pricing_tier')
                            ->options([
                                'Economy' => 'Economy / High Volume',
                                'Standard' => 'Standard Commercial',
                                'Premium' => 'Premium Aesthetic / Master Ceramist',
                            ])
                            ->default('Standard'),
                        Forms\Components\TextInput::make('rating')
                            ->label('Quality Rating (1.0 - 5.0)')
                            ->numeric()
                            ->step(0.1)
                            ->default(5.0)
                            ->prefix('★'),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active Lab Partner')
                            ->default(true),
                        Forms\Components\Textarea::make('notes')
                            ->label('Special Instructions & Preferred Materials')
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Lab Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('lab_type')
                    ->badge()
                    ->color('info')
                    ->sortable(),
                Tables\Columns\TextColumn::make('account_number')
                    ->label('Account #')
                    ->placeholder('N/A'),
                Tables\Columns\TextColumn::make('contact_person')
                    ->label('Account Rep')
                    ->searchable()
                    ->placeholder('N/A'),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable(),
                Tables\Columns\TextColumn::make('standard_turnaround_days')
                    ->label('Avg Turnaround')
                    ->suffix(' days')
                    ->sortable(),
                Tables\Columns\TextColumn::make('active_orders_count')
                    ->label('Active Cases')
                    ->counts('activeOrders')
                    ->badge()
                    ->color('warning')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_billed_amount')
                    ->label('Total Billed')
                    ->money('USD')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('pending_payable_amount')
                    ->label('Pending Payable')
                    ->money('USD')
                    ->color(fn ($state) => (float)$state > 0 ? 'danger' : 'success')
                    ->weight('bold')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('rating')
                    ->label('Rating')
                    ->formatStateUsing(fn ($state) => "★ {$state}")
                    ->color('warning')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('lab_type')
                    ->options([
                        'Commercial Lab' => 'Full-Service Commercial Lab',
                        'In-House CAD/CAM' => 'In-House CAD/CAM Milling Center',
                        'Crown & Bridge Specialist' => 'Crown & Bridge Specialist',
                        'Clear Aligners & Ortho' => 'Clear Aligners & Orthodontic Lab',
                        'Removable Prosthetics' => 'Dentures & Removable Prosthetics Lab',
                        'Implant & Surgical Center' => 'Implant Bar & Surgical Guide Center',
                    ]),
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
            LabOrdersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDentalLabs::route('/'),
            'create' => Pages\CreateDentalLab::route('/create'),
            'edit' => Pages\EditDentalLab::route('/{record}/edit'),
        ];
    }
}
