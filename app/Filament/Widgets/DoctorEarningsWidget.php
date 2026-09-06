<?php

namespace App\Filament\Widgets;

use App\Models\Appointment;
use App\Models\DoctorCommission;
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
                ->url(route('filament.admin.resources.appointments.index', ['tableFilters[start_time][created_from]' => $today->format('Y-m-d'), 'tableFilters[doctor_id][value]' => $userId])),

            Stat::make('Monthly Earnings', number_format($monthlyEarnings, 2) . ' EGP')
                ->description('Total commission earned this month')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success')
                ->url(route('filament.admin.resources.invoices.index') ?? '/admin'), // If commissions have a separate page, we can link it later

            Stat::make('Unsettled Balance', number_format($unsettledCommission, 2) . ' EGP')
                ->description('Earnings pending settlement from clinic')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning')
                ->url(route('filament.admin.resources.invoices.index') ?? '/admin'),
        ];
    }
}
