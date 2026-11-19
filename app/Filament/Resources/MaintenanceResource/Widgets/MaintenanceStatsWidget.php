<?php

namespace App\Filament\Resources\MaintenanceResource\Widgets;

use App\Models\MaintenanceRecord;
use App\Services\CurrencyHelper;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * KPI row above the maintenance table (design/stitch/05-maintenance). Tenant-scoped.
 */
class MaintenanceStatsWidget extends BaseWidget
{
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $practiceId = Filament::getTenant()?->id ?? auth()->user()?->practice_id;
        $base = fn () => MaintenanceRecord::query()->where('practice_id', $practiceId);

        $active = $base()->active()->count();
        $inProgress = $base()->where('status', MaintenanceRecord::STATUS_IN_PROGRESS)->count();

        $closingNow = $base()->active()
            ->where('impact', '!=', MaintenanceRecord::IMPACT_NO_CLOSURE)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now())
            ->count();

        $next = $base()->where('status', MaintenanceRecord::STATUS_SCHEDULED)
            ->where('starts_at', '>', now())
            ->orderBy('starts_at')
            ->first(['title', 'starts_at']);

        $monthCost = (float) $base()->where('status', MaintenanceRecord::STATUS_COMPLETED)
            ->whereBetween('completed_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('cost');

        return [
            Stat::make('Active jobs', $active)
                ->description($inProgress.' in progress')
                ->descriptionIcon('heroicon-m-wrench-screwdriver')
                ->color('primary'),
            Stat::make('Closures right now', $closingNow)
                ->description($closingNow ? 'Rooms blocked for booking' : 'All rooms bookable')
                ->descriptionIcon($closingNow ? 'heroicon-m-no-symbol' : 'heroicon-m-check-circle')
                ->color($closingNow ? 'danger' : 'success'),
            Stat::make('Next scheduled', $next ? $next->starts_at->format('D, M j H:i') : '—')
                ->description($next?->title ?? 'Nothing scheduled')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('info'),
            Stat::make('Spent this month', CurrencyHelper::format($monthCost))
                ->description('Completed jobs')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('warning'),
        ];
    }
}
