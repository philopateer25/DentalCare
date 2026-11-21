<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\AppointmentResource;
use App\Models\Appointment;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

/**
 * Dashboard greeting header (design/stitch/01-dashboard): greeting, date, clinic,
 * doctors on duty today and quick actions. Read-only; all data is tenant-scoped.
 */
class DashboardGreetingWidget extends Widget
{
    protected static string $view = 'filament.widgets.dashboard-greeting';

    protected static ?int $sort = -10;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return auth()->check();
    }

    protected function getViewData(): array
    {
        $user = auth()->user();
        $tenant = Filament::getTenant();
        $hour = (int) now()->format('G');

        $greeting = match (true) {
            $hour < 12 => __('Good morning'),
            $hour < 17 => __('Good afternoon'),
            default => __('Good evening'),
        };

        $onDuty = Appointment::query()
            ->where('practice_id', $tenant?->id ?? $user?->practice_id)
            ->whereDate('start_time', Carbon::today())
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->with('doctor:id,name')
            ->get(['id', 'doctor_id'])
            ->pluck('doctor.name')
            ->filter()
            ->unique()
            ->values();

        $canBook = $user?->hasAnyRole(['clinic_admin', 'super_admin', 'developer', 'secretary', 'doctor']) ?? false;

        return [
            'greeting' => $greeting,
            // "Dr. Ahmed Hassan" -> "Dr. Ahmed", "Sarah Jenkins" -> "Sarah"
            'firstName' => (string) preg_replace('/^((?:dr\.?\s+)?\S+).*$/iu', '$1', trim($user?->name ?? '')),
            'date' => now()->translatedFormat('l, F j, Y'),
            'clinic' => $tenant?->name,
            'onDuty' => $onDuty,
            'createUrl' => $canBook ? AppointmentResource::getUrl('create') : null,
            'appointmentsUrl' => $canBook ? AppointmentResource::getUrl('index') : null,
        ];
    }
}
