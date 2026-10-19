<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\LabOrderResource;
use App\Models\LabOrder;
use App\Services\CurrencyHelper;
use Filament\Facades\Filament;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\DB;

class AdminLabDebtsWidget extends BaseWidget
{
    /** Roles that are allowed to view this widget. */
    private const VIEWABLE_ROLES = ['clinic_admin', 'super_admin'];

    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 1;
    protected static ?string $heading = 'Lab Debts Overview';

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['clinic_admin', 'super_admin', 'developer']) ?? false;
    }

    public function table(Table $table): Table
    {
        $practiceId = \Filament\Facades\Filament::getTenant()?->id ?? auth()->user()?->practice_id;

        return $table
            ->query(
                LabOrder::query()
                    ->select('dental_lab_id', DB::raw('MAX(id) as id'), DB::raw('SUM(cost) as total_debt'), DB::raw('COUNT(id) as pending_orders'))
                    ->where('practice_id', $practiceId)
                    ->whereNotIn('status', ['received_at_clinic', 'seated_delivered', 'cancelled'])
                    ->groupBy('dental_lab_id')
            )
            ->columns([
                Tables\Columns\TextColumn::make('dentalLab.name')
                    ->label('Dental Lab'),
                Tables\Columns\TextColumn::make('pending_orders')
                    ->label('Pending Orders')
                    ->badge(),
                Tables\Columns\TextColumn::make('total_debt')
                    ->label('Total Owed')
                    ->money(fn () => CurrencyHelper::currentCurrency())
                    ->color('danger')
                    ->weight('bold'),
            ])
            ->paginated(false)
            ->recordUrl(
                fn (LabOrder $record): string => LabOrderResource::getUrl('index', [
                    'tableFilters[dental_lab_id][value]' => $record->dental_lab_id,
                ])
            )
            ->headerActions([
                Tables\Actions\Action::make('view_all')
                    ->label('All Lab Orders')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url(LabOrderResource::getUrl('index')),
            ]);
    }
}
