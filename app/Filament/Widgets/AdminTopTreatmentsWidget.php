<?php

namespace App\Filament\Widgets;

use App\Models\InvoiceItem;
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
        return auth()->user()->hasAnyRole(['clinic_admin', 'super_admin']);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                InvoiceItem::query()
                    ->select('invoiceable_id', DB::raw('MAX(id) as id'), DB::raw('COUNT(id) as times_performed'), DB::raw('SUM(total) as generated_revenue'))
                    ->where('invoiceable_type', 'App\Models\TreatmentProcedure')
                    ->whereMonth('created_at', now()->month)
                    ->groupBy('invoiceable_id')
                    ->orderBy('generated_revenue', 'desc')
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('invoiceable.name')
                    ->label('Treatment Procedure'),
                Tables\Columns\TextColumn::make('times_performed')
                    ->label('Performed')
                    ->badge()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('generated_revenue')
                    ->label('Revenue')
                    ->money('EGP')
                    ->color('success')
                    ->weight('bold'),
            ])
            ->paginated(false);
    }
}
