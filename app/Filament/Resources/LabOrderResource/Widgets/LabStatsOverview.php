<?php

namespace App\Filament\Resources\LabOrderResource\Widgets;

use App\Models\LabOrder;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class LabStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $activeCases = LabOrder::whereIn('status', [
            'impression_sent', 'lab_acknowledged', 'in_production', 'try_in_stage', 'shipped_by_lab',
        ])->count();

        $readyToSeat = LabOrder::where('status', 'received_at_clinic')->count();

        $overdueCases = LabOrder::whereNotNull('expected_delivery_at')
            ->where('expected_delivery_at', '<', now())
            ->whereNotIn('status', ['received_at_clinic', 'seated_delivered', 'cancelled'])
            ->count();

        $redos = LabOrder::where('status', 'returned_for_redo')
            ->orWhere('redo_count', '>', 0)
            ->count();

        $totalCompleted = LabOrder::where('status', 'seated_delivered')->count();

        return [
            Stat::make('Active Cases in Production', $activeCases)
                ->description('In lab fabrication / transit')
                ->icon('heroicon-o-arrow-path')
                ->color('warning'),

            Stat::make('Arrived & Ready to Seat', $readyToSeat)
                ->description('In clinic lab box / QC passed')
                ->icon('heroicon-o-check-badge')
                ->color('success'),

            Stat::make('Overdue / Late Lab Cases', $overdueCases)
                ->description($overdueCases > 0 ? 'Requires lab follow-up' : 'All deliveries on schedule')
                ->icon('heroicon-o-clock')
                ->color($overdueCases > 0 ? 'danger' : 'success'),

            Stat::make('Redo & Remake Cases', $redos)
                ->description("{$totalCompleted} cases completed successfully")
                ->icon('heroicon-o-arrow-uturn-left')
                ->color($redos > 0 ? 'danger' : 'gray'),
        ];
    }
}
