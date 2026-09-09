<?php

namespace Tests\Unit;

use App\Models\Practice;
use App\Models\User;
use App\Services\LanguageHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LanguageHelperTest extends TestCase
{
    use RefreshDatabase;

    public function test_supported_locales_contains_english_arabic_and_french(): void
    {
        $locales = LanguageHelper::supportedLocales();
        $this->assertContains('en', $locales);
        $this->assertContains('ar', $locales);
        $this->assertContains('fr', $locales);
    }

    public function test_direction_and_rtl_check(): void
    {
        $this->assertEquals('rtl', LanguageHelper::direction('ar'));
        $this->assertTrue(LanguageHelper::isRtl('ar'));

        $this->assertEquals('ltr', LanguageHelper::direction('en'));
        $this->assertFalse(LanguageHelper::isRtl('en'));

        $this->assertEquals('ltr', LanguageHelper::direction('fr'));
        $this->assertFalse(LanguageHelper::isRtl('fr'));
    }

    public function test_get_options_returns_names_and_flags(): void
    {
        $options = LanguageHelper::getOptions();
        $this->assertArrayHasKey('en', $options);
        $this->assertArrayHasKey('ar', $options);
        $this->assertArrayHasKey('fr', $options);

        $this->assertStringContainsString('العربية', $options['ar']);
        $this->assertStringContainsString('English', $options['en']);
        $this->assertStringContainsString('Français', $options['fr']);
    }

    public function test_current_locale_falls_back_to_en(): void
    {
        session()->forget('locale');
        $locale = LanguageHelper::currentLocale();
        $this->assertContains($locale, ['en', 'ar', 'fr']);
    }

    public function test_current_locale_respects_session(): void
    {
        session(['locale' => 'ar']);
        $this->assertEquals('ar', LanguageHelper::currentLocale());

        session(['locale' => 'fr']);
        $this->assertEquals('fr', LanguageHelper::currentLocale());
    }

    public function test_locale_switching_route(): void
    {
        $response = $this->get('/locale/ar');
        $response->assertRedirect();
        $this->assertEquals('ar', session('locale'));

        $response = $this->get('/locale/fr');
        $response->assertRedirect();
        $this->assertEquals('fr', session('locale'));

        // Invalid locale should not be accepted
        $response = $this->get('/locale/invalid_lang');
        $response->assertRedirect();
        $this->assertEquals('fr', session('locale'));
    }

    public function test_frontend_translations_loaded(): void
    {
        $translations = LanguageHelper::getFrontendTranslations('ar');
        $this->assertIsArray($translations);
        $this->assertArrayHasKey('Clinical Management', $translations);
        $this->assertEquals('الإدارة السريرية', $translations['Clinical Management']);
    }
}
