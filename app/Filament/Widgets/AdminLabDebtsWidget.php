<?php

namespace App\Filament\Widgets;

use App\Models\LabOrder;
use App\Services\CurrencyHelper;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class AdminLabDebtsWidget extends BaseWidget
{
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 1;
    protected static ?string $heading = 'Lab Debts Overview';

    public static function canView(): bool
    {
        return auth()->user()->hasAnyRole(['clinic_admin', 'super_admin']);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                LabOrder::query()
                    ->select('dental_lab_id', DB::raw('MAX(id) as id'), DB::raw('SUM(cost) as total_debt'), DB::raw('COUNT(id) as pending_orders'))
                    ->where('status', '!=', 'delivered')
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
            ->paginated(false);
    }
}
