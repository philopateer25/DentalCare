<?php

namespace App\Filament\Pages\Auth;

use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Auth\Login as BaseLogin;

/**
 * DOXamigo branded login (design/stitch/06-login).
 * Only the layout/view and field presentation change; authentication is Filament's own.
 */
class Login extends BaseLogin
{
    protected static string $layout = 'filament.layouts.auth';

    protected static string $view = 'filament.pages.auth.login';

    protected function getEmailFormComponent(): Component
    {
        /** @var TextInput $field */
        $field = parent::getEmailFormComponent();

        return $field
            ->label(__('Work email'))
            ->placeholder('name@clinic.com')
            ->prefixIcon('heroicon-o-identification');
    }

    protected function getPasswordFormComponent(): Component
    {
        /** @var TextInput $field */
        $field = parent::getPasswordFormComponent();

        return $field
            ->placeholder(__('Enter your password'))
            ->prefixIcon('heroicon-o-lock-closed')
            ->revealable();
    }

    protected function getRememberFormComponent(): Component
    {
        return parent::getRememberFormComponent()
            ->label(__('Remember me on this workstation'));
    }

    public function getHeading(): string
    {
        return __('Welcome back');
    }

    public function getSubheading(): ?string
    {
        return __('Sign in to access your clinic portal, schedules and chairside records.');
    }
}
