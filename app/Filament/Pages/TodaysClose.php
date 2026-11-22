<?php

namespace App\Filament\Pages;

use App\Services\DailyCloseReport;
use App\Services\FeatureManager;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

/**
 * End-of-day owner report (Phase 3, stage 2): collected today by method,
 * expenses, cash in the drawer, what patients owe, installments due and
 * per-doctor commission.
 */
class TodaysClose extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Finance & Treasury';

    protected static ?string $navigationLabel = "Today's close";

    protected static ?string $title = "Today's close";

    protected static ?string $slug = 'todays-close';

    protected static ?int $navigationSort = 0;

    protected static string $view = 'filament.pages.todays-close';

    /** Y-m-d; bound to the date picker in the view. */
    public string $day = '';

    public static function canAccess(): bool
    {
        $tenant = Filament::getTenant();

        return $tenant !== null
            && FeatureManager::isEnabled('finance', $tenant)
            && (auth()->user()?->hasAnyRole(['super_admin', 'clinic_admin', 'accountant', 'developer']) ?? false);
    }

    public function mount(): void
    {
        $this->day = now()->timezone(config('app.timezone'))->toDateString();
    }

    /** @return array<string, mixed> */
    public function getReport(): array
    {
        try {
            $day = Carbon::createFromFormat('Y-m-d', $this->day, config('app.timezone'));
        } catch (\Throwable) {
            $day = now();
        }

        if ($day->isFuture()) {
            $day = now();
        }

        return app(DailyCloseReport::class)->build((int) Filament::getTenant()->id, $day);
    }
}
