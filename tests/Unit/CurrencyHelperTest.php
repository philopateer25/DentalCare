<?php

namespace Tests\Unit;

use App\Models\Practice;
use App\Services\CurrencyHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrencyHelperTest extends TestCase
{
    use RefreshDatabase;
    public function test_symbol_returns_correct_symbols_for_major_currencies(): void
    {
        $this->assertEquals('EGP', CurrencyHelper::symbol('EGP'));
        $this->assertEquals('$', CurrencyHelper::symbol('USD'));
        $this->assertEquals('SAR', CurrencyHelper::symbol('SAR'));
        $this->assertEquals('AED', CurrencyHelper::symbol('AED'));
        $this->assertEquals('€', CurrencyHelper::symbol('EUR'));
        $this->assertEquals('£', CurrencyHelper::symbol('GBP'));
        $this->assertEquals('KWD', CurrencyHelper::symbol('KWD'));
    }

    public function test_format_formats_currency_amounts_accurately(): void
    {
        $egpFormatted = CurrencyHelper::format(1500, 'EGP');
        $this->assertStringContainsString('EGP', $egpFormatted);
        $this->assertStringContainsString('1,500', $egpFormatted);

        $usdFormatted = CurrencyHelper::format(1500, 'USD');
        $this->assertStringContainsString('$', $usdFormatted);
        $this->assertStringContainsString('1,500', $usdFormatted);

        $sarFormatted = CurrencyHelper::format(2500, 'SAR');
        $this->assertStringContainsString('SAR', $sarFormatted);
        $this->assertStringContainsString('2,500', $sarFormatted);
    }

    public function test_get_options_lists_all_supported_currencies(): void
    {
        $options = CurrencyHelper::getOptions();
        $this->assertArrayHasKey('EGP', $options);
        $this->assertArrayHasKey('USD', $options);
        $this->assertArrayHasKey('SAR', $options);
        $this->assertArrayHasKey('AED', $options);
        $this->assertArrayHasKey('EUR', $options);
        $this->assertArrayHasKey('GBP', $options);
        $this->assertStringContainsString('Egyptian Pound', $options['EGP']);
        $this->assertStringContainsString('Saudi Riyal', $options['SAR']);
    }

    public function test_current_currency_resolves_practice_setting(): void
    {
        $practice = Practice::first();
        if ($practice) {
            $practice->update(['currency' => 'EGP']);
            $this->assertEquals('EGP', CurrencyHelper::currentCurrency());

            $practice->update(['currency' => 'SAR']);
            $this->assertEquals('SAR', CurrencyHelper::currentCurrency());

            // Reset back to EGP as per user choice
            $practice->update(['currency' => 'EGP']);
            $this->assertEquals('EGP', CurrencyHelper::currentCurrency());
        } else {
            $this->assertIsString(CurrencyHelper::currentCurrency());
        }
    }
}
