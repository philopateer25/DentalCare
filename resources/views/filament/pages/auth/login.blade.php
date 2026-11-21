@php
    $features = [
        ['icon' => 'heroicon-o-shield-check', 'title' => __('Encrypted patient records'), 'text' => __('AES-256 encrypted backups of records, images and files')],
        ['icon' => 'heroicon-o-calendar-days', 'title' => __('Smart chair scheduling'), 'text' => __('Role-based calendar with room maintenance awareness')],
        ['icon' => 'heroicon-o-language', 'title' => __('Arabic & English'), 'text' => __('Full right-to-left support for the whole clinic team')],
    ];
@endphp

<div class="dx-login">
    {{-- Brand showcase --}}
    <section class="dx-login-showcase">
        <svg class="dx-login-ring dx-login-ring-a" viewBox="0 0 200 120" aria-hidden="true"><ellipse cx="100" cy="60" rx="90" ry="46" fill="none" stroke="#fff" stroke-width="18"/></svg>
        <svg class="dx-login-ring dx-login-ring-b" viewBox="0 0 200 120" aria-hidden="true"><ellipse cx="100" cy="60" rx="90" ry="46" fill="none" stroke="#fff" stroke-width="18"/></svg>

        <div class="dx-login-showcase-top">
            <span class="dx-login-pill">
                <x-filament::icon icon="heroicon-o-sparkles" class="h-4 w-4" />
                {{ __('Dental clinic management') }}
            </span>
            <h1 class="dx-login-title">{{ __('Cloud intelligence for modern dental clinics') }}</h1>
            <p class="dx-login-lead">{{ __('Odontograms, treatment plans, chairside billing, installments and WhatsApp reminders in one clinical workspace.') }}</p>

            <ul class="dx-login-features">
                @foreach ($features as $feature)
                    <li>
                        <span class="dx-login-feature-icon"><x-filament::icon :icon="$feature['icon']" class="h-5 w-5" /></span>
                        <span>
                            <strong>{{ $feature['title'] }}</strong>
                            <small>{{ $feature['text'] }}</small>
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- Sign-in card --}}
    <section class="dx-login-card-wrap">
        <div class="dx-login-card">
            <div class="dx-login-card-head">
                <h2>{{ $this->getHeading() }}</h2>
                <p>{{ $this->getSubheading() }}</p>
            </div>

            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE, scopes: $this->getRenderHookScopes()) }}

            <x-filament-panels::form id="form" wire:submit="authenticate">
                {{ $this->form }}

                <x-filament-panels::form.actions
                    :actions="$this->getCachedFormActions()"
                    :full-width="true"
                />
            </x-filament-panels::form>

            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, scopes: $this->getRenderHookScopes()) }}

            <div class="dx-login-card-foot">
                <x-filament::icon icon="heroicon-o-clock" class="h-4 w-4" />
                <span>{{ __('Sessions are tied to this workstation. Sign out on shared computers.') }}</span>
            </div>
        </div>
    </section>
</div>
