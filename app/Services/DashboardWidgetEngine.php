<?php

namespace App\Services;

use App\Models\User;
use App\Services\Widgets\FinanceWidgetService;
use App\Services\Widgets\InventoryWidgetService;
use InvalidArgumentException;

class DashboardWidgetEngine
{
    public const DEFAULT_WIDGETS = [
        'gross_production' => ['enabled' => true, 'order' => 1, 'category' => 'finance'],
        'collections_this_month' => ['enabled' => true, 'order' => 2, 'category' => 'finance'],
        'outstanding_balance' => ['enabled' => true, 'order' => 3, 'category' => 'finance'],
        'doctor_commissions' => ['enabled' => true, 'order' => 4, 'category' => 'finance'],
        'low_stock' => ['enabled' => true, 'order' => 5, 'category' => 'inventory'],
        'expiring_soon' => ['enabled' => true, 'order' => 6, 'category' => 'inventory'],
        'consumption_rate' => ['enabled' => true, 'order' => 7, 'category' => 'inventory'],
        'inventory_valuation' => ['enabled' => true, 'order' => 8, 'category' => 'inventory'],
    ];

    public function __construct(
        protected FinanceWidgetService $financeWidgetService,
        protected InventoryWidgetService $inventoryWidgetService
    ) {}

    /**
     * Get registered widget definitions.
     */
    public function getRegisteredWidgets(): array
    {
        return self::DEFAULT_WIDGETS;
    }

    /**
     * Get ordered, filtered active widgets for a user with fallback and feature flag enforcement.
     */
    public function getWidgetsForUser(?User $user): array
    {
        $userPrefs = $user?->dashboard_widgets;

        if (! $this->isValidPreferenceArray($userPrefs)) {
            $prefs = self::DEFAULT_WIDGETS;
        } else {
            $prefs = array_merge(self::DEFAULT_WIDGETS, $userPrefs);
        }

        // Sort by order ascending
        uasort($prefs, fn ($a, $b) => ($a['order'] ?? 99) <=> ($b['order'] ?? 99));

        $practice = $user?->practice ?? \Filament\Facades\Filament::getTenant();

        return array_filter($prefs, function ($item) use ($practice) {
            if (empty($item['enabled'])) {
                return false;
            }

            $category = $item['category'] ?? null;
            if ($category && in_array($category, ['finance', 'inventory'], true)) {
                if (! \App\Services\FeatureManager::isEnabled($category, $practice)) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * Update user dashboard widget preferences.
     */
    public function updateUserPreferences(User $user, array $preferences): array
    {
        $sanitized = [];
        $registeredKeys = array_keys(self::DEFAULT_WIDGETS);

        foreach ($preferences as $key => $config) {
            if (! in_array($key, $registeredKeys, true)) {
                continue;
            }

            $sanitized[$key] = [
                'enabled' => (bool) ($config['enabled'] ?? true),
                'order' => (int) ($config['order'] ?? 99),
                'category' => self::DEFAULT_WIDGETS[$key]['category'],
            ];
        }

        if (empty($sanitized)) {
            $sanitized = self::DEFAULT_WIDGETS;
        }

        $user->update(['dashboard_widgets' => $sanitized]);

        return $sanitized;
    }

    /**
     * Fetch widget data payload for practice.
     */
    public function getWidgetData(string $widgetKey, int $practiceId): mixed
    {
        return match ($widgetKey) {
            'gross_production' => [
                'key' => 'gross_production',
                'title' => 'Gross Production',
                'amount' => $this->financeWidgetService->getGrossProduction($practiceId),
            ],
            'collections_this_month' => [
                'key' => 'collections_this_month',
                'title' => 'Collections This Month',
                'amount' => $this->financeWidgetService->getCollectionsThisMonth($practiceId),
                'total_collections' => $this->financeWidgetService->getTotalCollections($practiceId),
            ],
            'outstanding_balance' => [
                'key' => 'outstanding_balance',
                'title' => 'Outstanding AR Balance',
                'amount' => $this->financeWidgetService->getOutstandingBalance($practiceId),
            ],
            'doctor_commissions' => array_merge([
                'key' => 'doctor_commissions',
                'title' => 'Doctor Commissions & Top Earners',
            ], $this->financeWidgetService->getDoctorCommissions($practiceId)),

            'low_stock' => array_merge([
                'key' => 'low_stock',
                'title' => 'Low Stock Items',
            ], $this->inventoryWidgetService->getLowStock($practiceId)),

            'expiring_soon' => array_merge([
                'key' => 'expiring_soon',
                'title' => 'Expiring Soon Batches (60 Days)',
            ], $this->inventoryWidgetService->getExpiringSoon($practiceId, 60)),

            'consumption_rate' => array_merge([
                'key' => 'consumption_rate',
                'title' => 'Stock Consumption Rate (30 Days)',
            ], $this->inventoryWidgetService->getConsumptionRate($practiceId, 30)),

            'inventory_valuation' => [
                'key' => 'inventory_valuation',
                'title' => 'Total Inventory Valuation',
                'amount' => $this->inventoryWidgetService->getInventoryValuation($practiceId),
            ],

            default => throw new InvalidArgumentException("Unknown widget key: {$widgetKey}"),
        };
    }

    /**
     * Validate preference array structure.
     */
    protected function isValidPreferenceArray(mixed $prefs): bool
    {
        if (! is_array($prefs) || empty($prefs)) {
            return false;
        }

        foreach ($prefs as $key => $val) {
            if (! is_array($val) || ! isset($val['enabled'])) {
                return false;
            }
        }

        return true;
    }
}
