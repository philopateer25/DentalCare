<?php

namespace App\Services;

use Filament\Facades\Filament;
use Illuminate\Support\Number;

class CurrencyHelper
{
    public const CURRENCIES = [
        'EGP' => ['name' => 'Egyptian Pound', 'symbol' => 'EGP', 'locale' => 'en'],
        'USD' => ['name' => 'US Dollar', 'symbol' => '$', 'locale' => 'en'],
        'SAR' => ['name' => 'Saudi Riyal', 'symbol' => 'SAR', 'locale' => 'en'],
        'AED' => ['name' => 'UAE Dirham', 'symbol' => 'AED', 'locale' => 'en'],
        'EUR' => ['name' => 'Euro', 'symbol' => '€', 'locale' => 'de'],
        'GBP' => ['name' => 'British Pound', 'symbol' => '£', 'locale' => 'en_GB'],
        'KWD' => ['name' => 'Kuwaiti Dinar', 'symbol' => 'KWD', 'locale' => 'en'],
        'QAR' => ['name' => 'Qatari Riyal', 'symbol' => 'QAR', 'locale' => 'en'],
        'BHD' => ['name' => 'Bahraini Dinar', 'symbol' => 'BHD', 'locale' => 'en'],
        'OMR' => ['name' => 'Omani Rial', 'symbol' => 'OMR', 'locale' => 'en'],
        'JOD' => ['name' => 'Jordanian Dinar', 'symbol' => 'JOD', 'locale' => 'en'],
        'CAD' => ['name' => 'Canadian Dollar', 'symbol' => 'CA$', 'locale' => 'en_CA'],
        'AUD' => ['name' => 'Australian Dollar', 'symbol' => 'A$', 'locale' => 'en_AU'],
        'TRY' => ['name' => 'Turkish Lira', 'symbol' => '₺', 'locale' => 'tr'],
    ];

    public static function getOptions(): array
    {
        $options = [];
        foreach (self::CURRENCIES as $code => $info) {
            $options[$code] = "{$info['name']} ({$info['symbol']} - {$code})";
        }
        return $options;
    }

    public static function currentCurrency(): string
    {
        try {
            $tenant = Filament::getTenant();
            if ($tenant && !empty($tenant->currency)) {
                return strtoupper($tenant->currency);
            }
        } catch (\Throwable) {
            // Not in Filament tenant context
        }

        try {
            $practice = \App\Models\Practice::first();
            if ($practice && !empty($practice->currency)) {
                return strtoupper($practice->currency);
            }
        } catch (\Throwable) {
            // Database not ready
        }

        return 'EGP';
    }

    public static function symbol(?string $currency = null): string
    {
        $code = strtoupper($currency ?? self::currentCurrency());
        return self::CURRENCIES[$code]['symbol'] ?? $code;
    }

    public static function format(float|int|string|null $amount, ?string $currency = null): string
    {
        $val = (float) ($amount ?? 0);
        $code = strtoupper($currency ?? self::currentCurrency());
        $info = self::CURRENCIES[$code] ?? ['symbol' => $code, 'locale' => 'en'];

        try {
            return Number::currency($val, $code, $info['locale'] ?? 'en');
        } catch (\Throwable) {
            return ($info['symbol'] ?? '$') . ' ' . number_format($val, 2);
        }
    }
}
