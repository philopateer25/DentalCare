<?php

namespace App\Filament\Resources\InventoryResource\RelationManagers;

use App\Models\InventoryBatch;
use App\Models\Supplier;
use App\Services\CurrencyHelper;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class BatchesRelationManager extends RelationManager
{
    /** Number of days before expiry to flag as "Expiring Soon". */
    private const EXPIRY_WARNING_DAYS = 60;

    protected static string $relationship = 'batches';

    protected static ?string $recordTitleAttribute = 'batch_number';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('batch_number')
                    ->label('Batch / Lot Number')
                    ->default(fn () => self::generateBatchNumber())
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Auto-generated (e.g. BAT-20260901-0001)'),
                Forms\Components\Select::make('supplier_id')
                    ->label('Supplier')
                    ->options(fn () => Supplier::query()
                        ->where('practice_id', \Filament\Facades\Filament::getTenant()?->id ?? auth()->user()?->practice_id)
                        ->pluck('name', 'id'))
                    ->searchable()
                    ->placeholder('Select Supplier'),
                Forms\Components\DatePicker::make('received_date')
                    ->label('Date Received')
                    ->default(now())
                    ->native(false),
                Forms\Components\DatePicker::make('expiry_date')
                    ->label('Expiration Date')
                    ->native(false),
                Forms\Components\TextInput::make('unit_cost')
                    ->label('Unit Cost')
                    ->numeric()
                    ->prefix(fn () => CurrencyHelper::symbol())
                    ->default(0.00)
                    ->required(),
                Forms\Components\TextInput::make('quantity_received')
                    ->label('Quantity Received')
                    ->numeric()
                    ->default(1)
                    ->required(),
                Forms\Components\TextInput::make('quantity_remaining')
                    ->label('Quantity Remaining')
                    ->numeric()
                    ->default(1)
                    ->required(),
                Forms\Components\TextInput::make('notes')
                    ->label('Batch Notes')
                    ->columnSpanFull(),
            ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('batch_number')
                    ->label('Batch / Lot #')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->placeholder('N/A')
                    ->sortable(),
                Tables\Columns\TextColumn::make('received_date')
                    ->label('Received')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('expiry_date')
                    ->label('Expiry Date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('expiry_status')
                    ->label('Expiry Alert')
                    ->badge()
                    ->state(fn ($record): string => self::resolveExpiryStatus($record->expiry_date))
                    ->color(fn (string $state): string => match ($state) {
                        'Expired' => 'danger',
                        'Expiring Soon' => 'warning',
                        'Active' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('unit_cost')
                    ->label('Unit Cost')
                    ->money(fn () => CurrencyHelper::currentCurrency())
                    ->sortable(),
                Tables\Columns\TextColumn::make('quantity_received')
                    ->label('Received')
                    ->sortable(),
                Tables\Columns\TextColumn::make('quantity_remaining')
                    ->label('Remaining')
                    ->sortable()
                    ->weight('bold'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
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

    // ──────────────────────────────────────────────
    //  Private helpers
    // ──────────────────────────────────────────────

    /**
     * Generate a default batch number (BAT-YYYYMMDD-XXXX).
     */
    private static function generateBatchNumber(): string
    {
        $nextId = (InventoryBatch::max('id') ?? 0) + 1;

        return sprintf('BAT-%s-%s', date('Ymd'), str_pad((string) $nextId, 4, '0', STR_PAD_LEFT));
    }

    /**
     * Resolve human-readable expiry status from an optional expiry date.
     */
    private static function resolveExpiryStatus(?Carbon $expiryDate): string
    {
        if (!$expiryDate) {
            return 'No Expiry';
        }

        if ($expiryDate->isPast()) {
            return 'Expired';
        }

        if ($expiryDate->diffInDays(now()) <= self::EXPIRY_WARNING_DAYS) {
            return 'Expiring Soon';
        }

        return 'Active';
    }
}
