<?php

namespace App\Filament\Pages;

use App\Services\FeatureManager;
use App\Services\LicenseManager;
use Filament\Pages\Page;

/**
 * Phase 3 — shown instead of missing menus while a clinic is being rolled out
 * stage by stage: what is on now, what comes next, and what's later.
 */
class ComingNext extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-gift';

    protected static ?string $navigationLabel = 'Coming next';

    protected static ?string $title = 'Coming next';

    protected static ?string $slug = 'coming-next';

    protected static ?int $navigationSort = 90;

    protected static string $view = 'filament.pages.coming-next';

    public const STAGE_TITLES = [
        1 => 'Stage 1 — Reception',
        2 => 'Stage 2 — Money',
        3 => 'Stage 3 — Doctors',
        4 => 'Stage 4 — Everything else',
    ];

    /** Things that aren't plan features but still belong to a stage. */
    public const EXTRA_MODULES = [
        1 => ['Calendar & appointments', 'Patients', 'Reception queue'],
        2 => ["Today's close (end-of-day report)", 'Clinic expenses'],
    ];

    public const MODULE_NAMES = [
        'clinical' => 'Dental chart, treatment plans, prescriptions & follow-ups',
    ];

    public static function shouldRegisterNavigation(): bool
    {
        $stage = static::currentStage();

        return $stage !== null && $stage < 4;
    }

    public static function currentStage(): ?int
    {
        $license = app(LicenseManager::class);

        return $license->isEnforced() ? $license->getRolloutStage() : null;
    }

    /**
     * @return array<int, array{title: string, state: string, modules: list<array{name: string, in_plan: bool}>}>
     */
    public function getStages(): array
    {
        $license = app(LicenseManager::class);
        $current = static::currentStage() ?? 4;
        $stages = [];

        foreach (static::STAGE_TITLES as $number => $title) {
            $modules = array_map(fn ($name) => ['name' => __($name), 'in_plan' => true], static::EXTRA_MODULES[$number] ?? []);

            foreach ((array) config('license.rollout_stages', []) as $module => $stage) {
                if ((int) $stage !== $number) {
                    continue;
                }

                $isFeature = isset(FeatureManager::FEATURES[$module]);
                $modules[] = [
                    'name' => __(static::MODULE_NAMES[$module] ?? FeatureManager::FEATURES[$module]['name'] ?? $module),
                    'in_plan' => ! $isFeature || $license->allowsFeature($module),
                ];
            }

            $stages[$number] = [
                'title' => __($title),
                'state' => $number <= $current ? 'on' : ($number === $current + 1 ? 'next' : 'later'),
                'modules' => $modules,
            ];
        }

        return $stages;
    }
}
