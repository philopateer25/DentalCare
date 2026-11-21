<?php

namespace App\Filament\Pages;

use App\Services\FeatureManager;
use App\Services\LicenseManager;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * License & Subscription — shows what the signed license allows and lets
 * clinic admins sync, activate, redeem an emergency unlock code and export
 * their data. Stays reachable in read-only and locked mode.
 */
class LicensePage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'License & Subscription';

    protected static ?string $title = 'License & Subscription';

    protected static ?string $slug = 'license';

    protected static ?int $navigationSort = 6;

    protected static string $view = 'filament.pages.license';

    public static function canManage(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(['super_admin', 'clinic_admin', 'developer']);
    }

    /** @return array<string, mixed> */
    public function getSummary(): array
    {
        return app(LicenseManager::class)->getSummary();
    }

    /** @return array<string, string> feature key => name */
    public function getFeatureNames(): array
    {
        return collect(FeatureManager::FEATURES)->mapWithKeys(fn ($meta, $key) => [$key => $meta['name']])->all();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync')
                ->label(__('Sync now'))
                ->icon('heroicon-o-arrow-path')
                ->visible(fn () => static::canManage())
                ->action(function () {
                    $result = app(LicenseManager::class)->sendHeartbeat();

                    Notification::make()
                        ->title($result['success'] ? __('License synced') : __('Sync failed'))
                        ->body($result['message'])
                        ->{$result['success'] ? 'success' : 'danger'}()
                        ->send();
                }),

            Action::make('activate')
                ->label(__('Enter license key'))
                ->icon('heroicon-o-key')
                ->color('gray')
                ->visible(fn () => static::canManage())
                ->form([
                    TextInput::make('key')
                        ->label(__('License key or enrollment code'))
                        ->required()
                        ->maxLength(200),
                ])
                ->action(function (array $data) {
                    $result = app(LicenseManager::class)->activate(trim($data['key']));

                    Notification::make()
                        ->title($result['success'] ? __('License activated') : __('Activation failed'))
                        ->body($result['message'])
                        ->{$result['success'] ? 'success' : 'danger'}()
                        ->send();
                }),

            Action::make('unlock')
                ->label(__('Enter unlock code'))
                ->icon('heroicon-o-lock-open')
                ->color('gray')
                ->visible(fn () => static::canManage())
                ->modalDescription(__('Only needed when the internet has been down for more than 3 days. Call support to get a code.'))
                ->form([
                    TextInput::make('code')
                        ->label(__('Unlock code'))
                        ->placeholder('XXXX-XXXX-XXXX-XXXX')
                        ->required()
                        ->maxLength(40),
                ])
                ->action(function (array $data) {
                    $result = app(LicenseManager::class)->redeemUnlockCode($data['code']);

                    Notification::make()
                        ->title($result['success'] ? __('Unlocked') : __('Code not accepted'))
                        ->body($result['message'])
                        ->{$result['success'] ? 'success' : 'danger'}()
                        ->send();
                }),

            Action::make('export')
                ->label(__('Export all data'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn () => static::canManage())
                ->url(fn () => route('license.export')),
        ];
    }
}
