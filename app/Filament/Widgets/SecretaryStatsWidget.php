<?php

namespace App\Filament\Widgets;

use App\Models\LabOrder;
use App\Models\Payment;
use App\Services\CurrencyHelper;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SecretaryStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return auth()->user()->hasAnyRole(['secretary', 'clinic_admin', 'super_admin']);
    }

    protected function getStats(): array
    {
        $today = Carbon::today();
        $practiceId = \Filament\Facades\Filament::getTenant()?->id;

        // 1. Today's Cash Safe
        $cashCollected = Payment::where('practice_id', $practiceId)
            ->whereDate('paid_at', $today)
            ->where('payment_method', 'cash')
            ->sum('amount');

        // 2. Arriving Lab Orders
        $arrivingLabs = LabOrder::where('practice_id', $practiceId)
            ->whereDate('expected_delivery_at', $today)
            ->where('status', '!=', 'delivered')
            ->count();

        return [
            Stat::make("Today's Cash Safe", CurrencyHelper::format($cashCollected))
                ->description('Total cash collected today')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success')
                ->url(\App\Filament\Resources\PatientResource::getUrl('index')), // Optional: link to a payments resource if available

            Stat::make('Arriving Lab Orders', $arrivingLabs)
                ->description('Prosthetics expected today')
                ->descriptionIcon('heroicon-m-truck')
                ->color('warning')
                ->url(\App\Filament\Resources\LabOrderResource::getUrl('index', ['tableFilters[expected_delivery_at][created_from]' => $today->format('Y-m-d')])),
        ];
    }
}
