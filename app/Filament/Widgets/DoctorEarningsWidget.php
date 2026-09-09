<?php

namespace App\Filament\Widgets;

use App\Models\Appointment;
use App\Models\DoctorCommission;
use App\Services\CurrencyHelper;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DoctorEarningsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return auth()->user()->hasRole('doctor') || auth()->user()->isDoctor();
    }

    protected function getStats(): array
    {
        $today = Carbon::today();
        $thisMonth = Carbon::now()->startOfMonth();
        $userId = auth()->id();

        // 1. Today's Appointments (Doctor specific)
        $todaysAppointments = Appointment::whereDate('start_time', $today)
            ->where('doctor_id', $userId)
            ->where('status', '!=', 'cancelled')
            ->count();

        // 2. Monthly Commission Earnings
        $monthlyEarnings = DoctorCommission::where('doctor_id', $userId)
            ->where('created_at', '>=', $thisMonth)
            ->sum('commission_amount');

        // 3. Unsettled Commission (Money the clinic owes the doctor)
        $unsettledCommission = DoctorCommission::where('doctor_id', $userId)
            ->where('status', 'pending')
            ->sum('commission_amount');

        return [
            Stat::make("My Today's Schedule", $todaysAppointments)
                ->description('Appointments assigned to you today')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('primary')
                ->url(\App\Filament\Resources\AppointmentResource::getUrl('index', ['tableFilters[start_time][created_from]' => $today->format('Y-m-d'), 'tableFilters[doctor_id][value]' => $userId])),

            Stat::make('Monthly Earnings', CurrencyHelper::format($monthlyEarnings))
                ->description('Total commission earned this month')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success')
                ->url(\App\Filament\Resources\InvoiceResource::getUrl('index')), // If commissions have a separate page, we can link it later

            Stat::make('Unsettled Balance', CurrencyHelper::format($unsettledCommission))
                ->description('Earnings pending settlement from clinic')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning')
                ->url(\App\Filament\Resources\InvoiceResource::getUrl('index')),
        ];
    }
}
