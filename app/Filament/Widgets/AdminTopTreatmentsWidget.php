<?php

namespace App\Filament\Widgets;

use App\Models\InvoiceItem;
use App\Services\CurrencyHelper;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class AdminTopTreatmentsWidget extends BaseWidget
{
    protected static ?int $sort = 4;
    protected int | string | array $columnSpan = 1;
    protected static ?string $heading = 'Top Treatments (This Month)';

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['clinic_admin', 'super_admin', 'developer']) ?? false;
    }

    public function table(Table $table): Table
    {
        $practiceId = \Filament\Facades\Filament::getTenant()?->id ?? auth()->user()?->practice_id;

        return $table
            ->query(
                InvoiceItem::query()
                    ->select(
                        'description',
                        DB::raw('MAX(id) as id'),
                        DB::raw('COUNT(id) as times_performed'),
                        DB::raw('SUM(total_price) as generated_revenue')
                    )
                    ->whereHas('invoice', fn($q) => $q->where('practice_id', $practiceId))
                    ->where('description', '!=', '')
                    ->whereNotNull('description')
                    ->whereMonth('created_at', now()->month)
                    ->groupBy('description')
                    ->orderBy('generated_revenue', 'desc')
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('description')
                    ->label('Treatment Procedure')
                    ->wrap()
                    ->limit(40),
                Tables\Columns\TextColumn::make('times_performed')
                    ->label('Performed')
                    ->badge()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('generated_revenue')
                    ->label('Revenue')
                    ->money(fn () => CurrencyHelper::currentCurrency())
                    ->color('success')
                    ->weight('bold'),
            ])
            ->paginated(false);
    }
}
