<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LabOrderResource\Pages;
use App\Models\Appointment;
use App\Models\DentalLab;
use App\Models\LabOrder;
use App\Models\Patient;
use App\Models\Practice;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LabOrderResource extends Resource
{
    protected static ?string $model = LabOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Lab & Prosthetics';

    protected static ?string $navigationLabel = 'Lab Prescriptions & Cases';

    protected static ?string $modelLabel = 'Lab Case';

    protected static ?string $pluralModelLabel = 'Dental Lab Cases & Orders';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Case Identification & Clinic Links')
                    ->schema([
                        Forms\Components\TextInput::make('tracking_number')
                            ->label('Case Tracking #')
                            ->default(fn () => 'LAB-' . date('Y') . '-' . str_pad((string) (LabOrder::max('id') + 1), 5, '0', STR_PAD_LEFT))
                            ->disabled()
                            ->dehydrated()
                            ->placeholder('Auto-generated (e.g. LAB-2026-00001)'),
                        Forms\Components\Select::make('patient_id')
                            ->label('Patient')
                            ->relationship('patient', 'first_name')
                            ->getOptionLabelFromRecordUsing(fn (Patient $record) => "{$record->first_name} {$record->last_name} ({$record->file_number})")
                            ->searchable(['first_name', 'last_name', 'file_number'])
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('doctor_id')
                            ->label('Prescribing Doctor')
                            ->relationship('doctor', 'name')
                            ->default(fn () => User::first()?->id)
                            ->searchable()
                            ->required(),
                        Forms\Components\Select::make('dental_lab_id')
                            ->label('Dental Lab Partner')
                            ->relationship('dentalLab', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('practice_id')
                            ->label('Practice')
                            ->relationship('practice', 'name')
                            ->default(fn () => Practice::firstOrCreate(['name' => 'Main Clinic'])->id)
                            ->required(),
                    ])->columns(3),

                Forms\Components\Tabs::make('Lab Prescription Details')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Restoration & Tooth Specification')
                            ->icon('heroicon-o-wrench-screwdriver')
                            ->schema([
                                Forms\Components\Select::make('order_type')
                                    ->label('Restoration / Appliance Category')
                                    ->options([
                                        'Single Crown' => 'Single Crown (Posterior / Anterior)',
                                        'Bridge' => 'Fixed Dental Bridge (3+ Units)',
                                        'Veneer' => 'Porcelain / Ceramic Laminate Veneer',
                                        'Inlay / Onlay' => 'Inlay / Onlay / Overlay',
                                        'Implant Custom Abutment & Crown' => 'Implant Custom Abutment & Screw-Retained Crown',
                                        'All-on-X Full Arch Hybrid' => 'All-on-4 / All-on-6 Titanium/Zirconia Hybrid Arch',
                                        'Complete Denture' => 'Complete Upper / Lower Denture',
                                        'Cast Partial Denture' => 'Cast Partial Denture (Co-Cr / Vitallium)',
                                        'Flexible Partial Denture' => 'Flexible Valplast Partial Denture',
                                        'Clear Aligners' => 'Clear Orthodontic Aligners (Full Stage Kit)',
                                        'Nightguard / Splint' => 'Occlusal Nightguard / Bruxism Splint (Hard/Soft)',
                                        'Hawley / Essix Retainer' => 'Orthodontic Retainer (Hawley / Essix)',
                                        'Implant Surgical Guide' => '3D Printed Implant Surgical Guide (Sleeve-guided)',
                                        'Diagnostic Wax-up' => 'Digital / Physical Aesthetic Diagnostic Wax-up',
                                    ])
                                    ->required()
                                    ->searchable(),
                                Forms\Components\TextInput::make('teeth_fdi')
                                    ->label('Tooth Numbers (FDI System)')
                                    ->placeholder('e.g. 11, 21, 22 or Upper Arch')
                                    ->helperText('List all tooth positions involved in this restoration')
                                    ->required(),
                                Forms\Components\Select::make('material')
                                    ->label('Material Substrate & Brand')
                                    ->options([
                                        'High-Translucency Multilayer Zirconia (Katana/3D Pro)' => 'High-Translucency Multilayer Zirconia (Katana / 3D Pro)',
                                        'Monolithic BruxZir Zirconia (1200 MPa)' => 'Monolithic BruxZir Zirconia (High Strength)',
                                        'IPS e.max CAD Lithium Disilicate Glass-Ceramic' => 'IPS e.max CAD Lithium Disilicate Glass-Ceramic',
                                        'Porcelain Fused to Metal (PFM) Co-Cr' => 'Porcelain Fused to Metal (PFM) Co-Cr Non-Precious',
                                        'Porcelain Fused to High-Noble Gold' => 'Porcelain Fused to High-Noble Gold Alloy',
                                        'Full Cast Gold Alloy (Type III/IV)' => 'Full Cast Gold Alloy (Type III / IV)',
                                        'Titanium Milled Bar with Composite / PMMA' => 'Titanium Milled Bar with Composite / PMMA Teeth',
                                        'Lucitone 199 High-Impact Acrylic Resin' => 'Lucitone 199 High-Impact Acrylic Resin',
                                        'Valplast Flexible Polyamide Nylon' => 'Valplast Flexible Polyamide Nylon',
                                        'Hard/Soft Dual-Laminate Polyurethane' => 'Hard/Soft Dual-Laminate Polyurethane (Comfort Splint)',
                                        'Bio-Compatible 3D Print Resin Class IIa' => 'Bio-Compatible 3D Print Resin Class IIa',
                                    ])
                                    ->required()
                                    ->searchable(),
                                Forms\Components\Select::make('margin_design')
                                    ->label('Margin Preparation Design')
                                    ->options([
                                        'Shoulder 360 Porcelain Butt' => '360° Porcelain Butt Margin',
                                        'Deep Chamfer' => 'Deep Chamfer Margin',
                                        'Light Chamfer' => 'Light Chamfer Margin',
                                        'Feather-Edge' => 'Feather-Edge / Knife-Edge',
                                        'Subgingival 0.5mm' => 'Subgingival Margin (0.5mm depth)',
                                    ])
                                    ->default('Deep Chamfer'),
                                Forms\Components\Textarea::make('instructions')
                                    ->label('Detailed Clinical Lab Instructions')
                                    ->placeholder('e.g. Please leave contact points light. Provide anatomical emergence profile for implant tooth #21. Open embrasure spaces for interdental brush.')
                                    ->columnSpanFull(),
                            ])->columns(2),

                        Forms\Components\Tabs\Tab::make('Shade, Aesthetics & Characterization')
                            ->icon('heroicon-o-sparkles')
                            ->schema([
                                Forms\Components\Select::make('shade')
                                    ->label('Body / Final Shade')
                                    ->options([
                                        'A1' => 'A1 (Light Reddish-Brownish)',
                                        'A2' => 'A2 (Natural Standard)',
                                        'A3' => 'A3 (Reddish-Brownish)',
                                        'A3.5' => 'A3.5 (Dark Reddish-Brownish)',
                                        'A4' => 'A4 (Intense Reddish-Brownish)',
                                        'B1' => 'B1 (Very Light Yellowish)',
                                        'B2' => 'B2 (Yellowish)',
                                        'B3' => 'B3 (Reddish-Yellowish)',
                                        'B4' => 'B4 (Dark Reddish-Yellowish)',
                                        'C1' => 'C1 (Greyish)',
                                        'C2' => 'C2 (Medium Greyish)',
                                        'C3' => 'C3 (Reddish-Greyish)',
                                        'C4' => 'C4 (Dark Reddish-Greyish)',
                                        'D2' => 'D2 (Reddish-Grey)',
                                        'D3' => 'D3 (Medium Reddish-Grey)',
                                        'OM1' => 'OM1 (Bleach Ultra-White)',
                                        'OM2' => 'OM2 (Bleach Extra-Light)',
                                        'OM3' => 'OM3 (Bleach Light)',
                                        'Bleach White (BL1)' => 'Bleach White (BL1)',
                                    ])
                                    ->required()
                                    ->searchable(),
                                Forms\Components\Select::make('stump_shade')
                                    ->label('Stump / Prep Shade (ND Scale)')
                                    ->options([
                                        'ND1' => 'ND1 (Lightest Bleached Stump)',
                                        'ND2' => 'ND2 (Natural Light Enamel)',
                                        'ND3' => 'ND3 (Natural Medium Dentin)',
                                        'ND4' => 'ND4 (Darker Natural Dentin)',
                                        'ND5' => 'ND5 (Yellowish Discolored)',
                                        'ND6' => 'ND6 (Brownish Discolored)',
                                        'ND7' => 'ND7 (Greyish Endodontic Tooth)',
                                        'ND8' => 'ND8 (Dark Devitalized Tooth)',
                                        'ND9' => 'ND9 (Metal Core / Cast Post)',
                                    ])
                                    ->placeholder('Select Stump Shade if all-ceramic'),
                                Forms\Components\Select::make('translucency')
                                    ->options([
                                        'High Translucency (Aesthetic Anterior)' => 'High Translucency (HT) - Aesthetic Anterior',
                                        'Medium Translucency (Natural)' => 'Medium Translucency (MT) - Natural',
                                        'Low Translucency (Opaque/Masking)' => 'Low Translucency (LT) - Masking dark prep',
                                    ])
                                    ->default('Medium Translucency (Natural)'),
                                Forms\Components\Select::make('surface_texture')
                                    ->options([
                                        'High Gloss Glaze' => 'High Gloss Glaze',
                                        'Natural Satin Enamel Texture' => 'Natural Satin Enamel Texture (With Perikymata)',
                                        'Matte Finish' => 'Matte / Low Luster',
                                    ])
                                    ->default('Natural Satin Enamel Texture'),
                                Forms\Components\Select::make('occlusal_staining')
                                    ->options([
                                        'None' => 'None / Clean Fissures',
                                        'Light Brown' => 'Light Caramel / Brown Fissures',
                                        'Medium Amber' => 'Medium Amber Characterization',
                                        'Dark Anatomical' => 'Dark Anatomical Fissures',
                                    ])
                                    ->default('Light Brown'),
                            ])->columns(2),

                        Forms\Components\Tabs\Tab::make('Impressions & Digital Files')
                            ->icon('heroicon-o-computer-desktop')
                            ->schema([
                                Forms\Components\Select::make('impression_type')
                                    ->label('Impression Method')
                                    ->options([
                                        'digital_scan' => 'Digital Intraoral 3D Scan (STL / PLY / OBJ)',
                                        'physical_pvs' => 'Physical Impression: Polyvinyl Siloxane (PVS)',
                                        'physical_polyether' => 'Physical Impression: Polyether (Impregum)',
                                        'physical_alginate' => 'Physical Impression: Alginate / Study Model',
                                    ])
                                    ->default('digital_scan')
                                    ->required(),
                                Forms\Components\TextInput::make('digital_scan_url')
                                    ->label('Digital Scan Cloud Share / Portal Link')
                                    ->url()
                                    ->prefix('https://')
                                    ->placeholder('dropbox.com/s/patient_scan_11_21.stl or meditlink.com/cases/...'),
                            ])->columns(2),

                        Forms\Components\Tabs\Tab::make('Logistics, QC & Delivery Tracking')
                            ->icon('heroicon-o-truck')
                            ->schema([
                                Forms\Components\Select::make('status')
                                    ->label('Current Case Status')
                                    ->options([
                                        'draft' => 'Draft Prescription',
                                        'impression_sent' => 'Impression / Scan Sent to Lab',
                                        'lab_acknowledged' => 'Lab Acknowledged & Accepted',
                                        'in_production' => 'In CAD/CAM Production / Sintering',
                                        'try_in_stage' => 'Framework / Wax Try-In Stage',
                                        'shipped_by_lab' => 'Shipped by Lab / In Transit',
                                        'received_at_clinic' => 'Received at Clinic (In Lab Box)',
                                        'seated_delivered' => 'Seated & Cemented on Patient',
                                        'returned_for_redo' => 'Returned for Remake / Redo',
                                        'cancelled' => 'Cancelled',
                                    ])
                                    ->default('impression_sent')
                                    ->required(),
                                Forms\Components\DateTimePicker::make('sent_at')
                                    ->label('Date & Time Sent')
                                    ->default(now())
                                    ->required(),
                                Forms\Components\DateTimePicker::make('expected_delivery_at')
                                    ->label('Expected Lab Delivery Date')
                                    ->default(now()->addDays(7))
                                    ->required(),
                                Forms\Components\DateTimePicker::make('delivered_at')
                                    ->label('Actual Clinic Received Date'),
                                Forms\Components\DateTimePicker::make('fitting_date')
                                    ->label('Scheduled Patient Fitting Appointment Date')
                                    ->helperText('Warning will trigger if fitting date is before expected delivery date'),
                                Forms\Components\Toggle::make('qc_passed')
                                    ->label('Clinical QC Inspection Passed')
                                    ->helperText('Verified margins, shade, and contacts upon arrival in clinic')
                                    ->default(false),
                                Forms\Components\TextInput::make('lab_box_number')
                                    ->label('Clinic Storage Box / Shelf #')
                                    ->placeholder('e.g. Shelf B, Lab Box #14'),
                            ])->columns(2),

                        Forms\Components\Tabs\Tab::make('Financials & Warranty')
                            ->icon('heroicon-o-banknotes')
                            ->schema([
                                Forms\Components\TextInput::make('cost')
                                    ->label('Lab Invoice Cost ($)')
                                    ->numeric()
                                    ->prefix('$')
                                    ->default(0.00)
                                    ->required(),
                                Forms\Components\TextInput::make('patient_charge')
                                    ->label('Patient Procedure Fee ($)')
                                    ->numeric()
                                    ->prefix('$'),
                                Forms\Components\TextInput::make('lab_invoice_number')
                                    ->label('Lab Invoice / Statement #')
                                    ->placeholder('e.g. INV-99381'),
                                Forms\Components\Select::make('payment_status')
                                    ->options([
                                        'pending' => 'Pending Invoice',
                                        'invoiced' => 'Invoiced by Lab',
                                        'paid' => 'Settled / Paid to Lab',
                                        'warranty_covered' => 'Covered Under Warranty (No Charge)',
                                    ])
                                    ->default('pending'),
                                Forms\Components\TextInput::make('warranty_years')
                                    ->label('Lab Warranty (Years)')
                                    ->numeric()
                                    ->default(5)
                                    ->suffix('years'),
                                Forms\Components\TextInput::make('redo_reason')
                                    ->label('Redo / Remake Reason (if applicable)')
                                    ->placeholder('e.g. Shade mismatch, margin gap on distobuccal, patient requested lighter shade'),
                            ])->columns(2),
                    ])->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('tracking_number')
                    ->label('Case #')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('patient.first_name')
                    ->label('Patient')
                    ->formatStateUsing(fn (LabOrder $record) => "{$record->patient?->first_name} {$record->patient?->last_name}")
                    ->description(fn (LabOrder $record) => "File: {$record->patient?->file_number}")
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('dentalLab.name')
                    ->label('Lab')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('order_type')
                    ->label('Restoration')
                    ->description(fn (LabOrder $record) => "Teeth: {$record->teeth_fdi}")
                    ->badge()
                    ->color('info')
                    ->sortable(),
                Tables\Columns\TextColumn::make('shade')
                    ->label('Shade')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'impression_sent' => 'info',
                        'lab_acknowledged' => 'primary',
                        'in_production' => 'warning',
                        'try_in_stage' => 'secondary',
                        'shipped_by_lab' => 'info',
                        'received_at_clinic' => 'success',
                        'seated_delivered' => 'success',
                        'returned_for_redo' => 'danger',
                        'cancelled' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('expected_delivery_at')
                    ->label('Due Date')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->color(fn (LabOrder $record) => $record->isOverdue() ? 'danger' : null)
                    ->weight(fn (LabOrder $record) => $record->isOverdue() ? 'bold' : 'normal'),
                Tables\Columns\TextColumn::make('fitting_date')
                    ->label('Fitting Appt')
                    ->dateTime('M d, Y')
                    ->placeholder('Unscheduled')
                    ->color(fn (LabOrder $record) => $record->hasFittingConflict() ? 'danger' : null)
                    ->toggleable(),
                Tables\Columns\IconColumn::make('qc_passed')
                    ->label('QC')
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('lab_box_number')
                    ->label('Location')
                    ->placeholder('N/A')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('cost')
                    ->label('Lab Cost')
                    ->money('USD')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'impression_sent' => 'Sent to Lab',
                        'lab_acknowledged' => 'Lab Acknowledged',
                        'in_production' => 'In Production',
                        'try_in_stage' => 'Try-In Stage',
                        'shipped_by_lab' => 'Shipped / In Transit',
                        'received_at_clinic' => 'Received at Clinic',
                        'seated_delivered' => 'Seated & Cemented',
                        'returned_for_redo' => 'Returned for Redo',
                    ]),
                Tables\Filters\SelectFilter::make('dental_lab_id')
                    ->label('Dental Lab')
                    ->relationship('dentalLab', 'name'),
                Tables\Filters\SelectFilter::make('order_type')
                    ->options([
                        'Single Crown' => 'Single Crown',
                        'Bridge' => 'Fixed Dental Bridge',
                        'Veneer' => 'Laminate Veneer',
                        'Implant Custom Abutment & Crown' => 'Implant Crown & Abutment',
                        'All-on-X Full Arch Hybrid' => 'All-on-4 / All-on-6 Hybrid',
                        'Complete Denture' => 'Complete Denture',
                        'Cast Partial Denture' => 'Cast Partial Denture',
                        'Clear Aligners' => 'Clear Aligners',
                        'Nightguard / Splint' => 'Nightguard / Splint',
                        'Implant Surgical Guide' => 'Implant Surgical Guide',
                    ]),
                Tables\Filters\Filter::make('overdue')
                    ->label('Overdue Deliveries')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('expected_delivery_at')
                        ->where('expected_delivery_at', '<', now())
                        ->whereNotIn('status', ['received_at_clinic', 'seated_delivered', 'cancelled'])
                    ),
                Tables\Filters\Filter::make('ready_in_clinic')
                    ->label('In Clinic (Ready to Seat)')
                    ->query(fn (Builder $query): Builder => $query->where('status', 'received_at_clinic')),
                Tables\Filters\Filter::make('redos')
                    ->label('Redos & Remakes')
                    ->query(fn (Builder $query): Builder => $query->where('status', 'returned_for_redo')->orWhere('redo_count', '>', 0)),
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
            'index' => Pages\ListLabOrders::route('/'),
            'create' => Pages\CreateLabOrder::route('/create'),
            'edit' => Pages\EditLabOrder::route('/{record}/edit'),
        ];
    }
}
