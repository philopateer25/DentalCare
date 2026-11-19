<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->tenant(\App\Models\Practice::class)
            ->login(\App\Filament\Pages\Auth\Login::class)
            ->brandName('DOXamigo')
            ->brandLogo(fn () => view('filament.brand.logo'))
            ->brandLogoHeight('2.25rem')
            ->favicon(asset('images/icon-512x512.png'))
            ->font('DM Sans')
            // DOXamigo brand kit: blue #3859a8, teal #009e83, red #d82c1e
            ->colors([
                'primary' => Color::hex('#3859a8'),
                'success' => Color::hex('#009e83'),
                'danger' => Color::hex('#d82c1e'),
                'warning' => Color::Amber,
                'info' => Color::Sky,
                'gray' => Color::Slate,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->navigationGroups([
                'Clinical Management',
                'Finance & Treasury',
                'Lab & Prosthetics',
                'Insurance & Claims',
                'Staff & HR Management',
                'Clinic Operations',
                'Settings',
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                // Custom widgets are auto-discovered from app/Filament/Widgets
            ])
            ->renderHook(
                \Filament\View\PanelsRenderHook::USER_MENU_BEFORE,
                fn () => view('filament.components.language-switch')
            )
            ->renderHook(
                \Filament\View\PanelsRenderHook::HEAD_END,
                fn (): string => '<link rel="manifest" href="/manifest.json">'
            )
            ->renderHook(
                \Filament\View\PanelsRenderHook::HEAD_END,
                // DOXamigo theme layered over Filament's CSS (plain CSS, see docs/decisions.md #13)
                fn (): string => app(\Illuminate\Foundation\Vite::class)(['resources/css/filament-theme.css'])->toHtml()
            )
            ->renderHook(
                \Filament\View\PanelsRenderHook::HEAD_END,
                // React Chart workspace bundle (Patients -> Chart page only)
                function (): string {
                    $vite = app(\Illuminate\Foundation\Vite::class);

                    return ($vite->reactRefresh()?->toHtml() ?? '')
                        .$vite(['resources/css/chart-workspace.css', 'resources/js/filament/chart-workspace.jsx'])->toHtml();
                },
                scopes: \App\Filament\Resources\PatientResource\Pages\ChartWorkspace::class,
            )
            ->renderHook(
                \Filament\View\PanelsRenderHook::FOOTER,
                fn () => view('filament.brand.footer')
            )
            ->renderHook(
                \Filament\View\PanelsRenderHook::HEAD_END,
                // React + FullCalendar bundle for AppointmentsCalendarWidget (dashboard only)
                function (): string {
                    $vite = app(\Illuminate\Foundation\Vite::class);

                    return ($vite->reactRefresh()?->toHtml() ?? '')
                        .$vite(['resources/css/filament-calendar.css', 'resources/js/filament/appointments-calendar.jsx'])->toHtml();
                },
                scopes: \Filament\Pages\Dashboard::class,
            )
            ->renderHook(
                \Filament\View\PanelsRenderHook::HEAD_END,
                // Dark-mode text for maintenance callouts (MaintenanceBookingFields)
                fn (): string => '<style>.dark .dc-callout[data-tone=danger]{color:rgb(var(--danger-300))!important}.dark .dc-callout[data-tone=warning]{color:rgb(var(--warning-300))!important}</style>'
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                \App\Http\Middleware\SetLocale::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->tenantMiddleware([
                \App\Http\Middleware\CheckLicenseStatus::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
