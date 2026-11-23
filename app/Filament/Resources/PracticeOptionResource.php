<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PracticeOptionResource\Pages;
use App\Models\PracticeOption;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Phase 5 — the clinic's own dropdown values. Values are added by simply typing
 * them in a form; here admins can see what's most used and delete typos.
 */
class PracticeOptionResource extends Resource
{
    protected static ?string $model = PracticeOption::class;

    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Dropdown lists';

    protected static ?string $modelLabel = 'list value';

    protected static ?string $pluralModelLabel = 'Dropdown lists';

    protected static ?int $navigationSort = 4;

    public const LIST_LABELS = [
        'medication' => 'Prescription — medication',
        'rx_dosage' => 'Prescription — dosage',
        'rx_frequency' => 'Prescription — frequency',
        'rx_duration' => 'Prescription — duration',
        'procedure_note' => 'Procedure note phrases',
        'tool' => 'Procedure — tools',
        'material' => 'Procedure — materials',
        'medical_condition' => 'Medical conditions',
        'active_medication' => 'Patient medications',
        'cardiac_condition' => 'Cardiac conditions',
        'diabetes_medication' => 'Diabetes medications',
        'expense_category' => 'Expense categories',
        // Chart workspace (3D / 2D diagnosis page)
        'diagnosis' => 'Chart — clinical diagnosis',
        'crown_material' => 'Chart — crown material',
        'implant_brand' => 'Chart — implant system',
        'extraction_reason' => 'Chart — reason missing',
        'tooth_note' => 'Chart — tooth note phrases',
    ];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(['super_admin', 'clinic_admin', 'developer']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('practice_id', Filament::getTenant()?->id ?? auth()->user()?->practice_id);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description(__('Anything staff type in these fields is remembered here and offered first next time. Delete typos or values you no longer use.'))
            ->defaultSort('use_count', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('list')
                    ->label(__('List'))
                    ->formatStateUsing(fn (string $state) => __(self::LIST_LABELS[$state] ?? $state))
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('value')->label(__('Value'))->searchable()->wrap(),
                Tables\Columns\TextColumn::make('use_count')->label(__('Times used'))->sortable()->numeric(),
                Tables\Columns\TextColumn::make('last_used_at')->label(__('Last used'))->since()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('list')
                    ->label(__('List'))
                    ->options(collect(self::LIST_LABELS)->map(fn ($l) => __($l))->all()),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()->label(__('Remove')),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->emptyStateHeading(__('Nothing typed yet'))
            ->emptyStateDescription(__('New medications, phrases and categories appear here once staff use them.'));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPracticeOptions::route('/'),
        ];
    }
}
