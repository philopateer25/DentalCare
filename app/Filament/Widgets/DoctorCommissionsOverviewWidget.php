<?php

namespace App\Filament\Widgets;

use App\Services\CurrencyHelper;
use App\Services\Widgets\FinanceWidgetService;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DoctorCommissionsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $practiceId = Filament::getTenant()?->id ?? auth()->user()?->practice_id ?? 1;
        $service = app(FinanceWidgetService::class);

        $commissionsData = $service->getDoctorCommissions($practiceId);
        $totalCommissions = $commissionsData['total_commissions'];
        $topEarners = $commissionsData['top_earners'];

        $topDoctorName = !empty($topEarners) ? $topEarners[0]['doctor_name'] : 'N/A';
        $topDoctorAmount = !empty($topEarners) ? CurrencyHelper::format($topEarners[0]['total_earned']) : '$0.00';

        return [
            Stat::make('Total Doctor Commissions', CurrencyHelper::format($totalCommissions))
                ->description('Accrued & settled provider splits')
                ->icon('heroicon-o-user-group')
                ->color('info'),

            Stat::make('Top Commission Earner', $topDoctorName)
                ->description("Earned {$topDoctorAmount}")
                ->icon('heroicon-o-trophy')
                ->color('success'),
        ];
    }
}
