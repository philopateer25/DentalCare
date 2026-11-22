@php
    $r = $this->getReport();
    $money = fn ($v) => \App\Services\CurrencyHelper::format($v);
    $labels = \App\Services\DailyCloseReport::METHOD_LABELS;
    $grid = 'display:grid;gap:1rem;grid-template-columns:repeat(auto-fit,minmax(16rem,1fr))';
    $row = 'flex justify-between gap-4 py-1 text-sm text-gray-950 dark:text-white';
    $muted = 'text-sm text-gray-500 dark:text-gray-400';
@endphp

{{-- Only Filament components / Filament's prebuilt classes here: the admin theme has no Tailwind build (docs/decisions.md #13). --}}
<x-filament-panels::page>
    <div class="flex flex-wrap items-center gap-3">
        <span class="{{ $muted }}">{{ __('Day') }}</span>
        <div style="width:12rem">
            <x-filament::input.wrapper>
                <x-filament::input type="date" wire:model.live="day" :max="now()->timezone(config('app.timezone'))->toDateString()" />
            </x-filament::input.wrapper>
        </div>
        <x-filament::button color="gray" icon="heroicon-o-printer" size="sm" onclick="window.print()">{{ __('Print') }}</x-filament::button>
    </div>

    <div style="{{ $grid }}">
        <x-filament::section>
            <p class="{{ $muted }}">{{ __('Collected') }}</p>
            <p class="text-2xl font-bold text-gray-950 dark:text-white">{{ $money($r['collected_total']) }}</p>
            <p class="{{ $muted }}">{{ trans_choice(':count payment|:count payments', $r['payments_count'], ['count' => $r['payments_count']]) }}</p>
        </x-filament::section>
        <x-filament::section>
            <p class="{{ $muted }}">{{ __('Cash in the drawer') }}</p>
            <p class="text-2xl font-bold text-gray-950 dark:text-white">{{ $money($r['net_cash']) }}</p>
            <p class="{{ $muted }}">{{ __('Cash collected minus cash expenses') }}</p>
        </x-filament::section>
        <x-filament::section>
            <p class="{{ $muted }}">{{ __('Patients owe (all time)') }}</p>
            <p class="text-2xl font-bold text-gray-950 dark:text-white">{{ $money($r['outstanding_total']) }}</p>
            <p class="{{ $muted }}">{{ trans_choice(':count open invoice|:count open invoices', $r['outstanding_count'], ['count' => $r['outstanding_count']]) }}</p>
        </x-filament::section>
    </div>

    <div style="{{ $grid }}">
        <x-filament::section :heading="__('Collected by method')">
            @forelse ($r['collected'] as $method => $amount)
                <div class="{{ $row }}"><span>{{ __($labels[$method] ?? $method) }}</span><span class="font-semibold">{{ $money($amount) }}</span></div>
            @empty
                <p class="{{ $muted }}">{{ __('No payments on this day.') }}</p>
            @endforelse
        </x-filament::section>

        <x-filament::section :heading="__('Expenses')">
            @forelse ($r['expenses'] as $method => $amount)
                <div class="{{ $row }}"><span>{{ __($labels[$method] ?? ucfirst(str_replace('_', ' ', (string) $method))) }}</span><span class="font-semibold">{{ $money($amount) }}</span></div>
            @empty
                <p class="{{ $muted }}">{{ __('No expenses on this day.') }}</p>
            @endforelse
            @if ($r['expenses_total'] > 0)
                <div class="{{ $row }} font-semibold"><span>{{ __('Total') }}</span><span>{{ $money($r['expenses_total']) }}</span></div>
            @endif
        </x-filament::section>

        <x-filament::section :heading="__('Installments')">
            <div class="{{ $row }}"><span>{{ __('Due on this day') }}</span><span class="font-semibold">{{ $money($r['installments_due_today']) }}</span></div>
            <div class="{{ $row }}">
                <span>{{ __('Overdue from earlier days') }}</span>
                @if ($r['installments_overdue'] > 0)
                    <x-filament::badge color="danger">{{ $money($r['installments_overdue']) }}</x-filament::badge>
                @else
                    <span class="font-semibold">{{ $money(0) }}</span>
                @endif
            </div>
            @if (\App\Filament\Resources\InstallmentPlanResource::canAccess())
                <x-filament::link :href="\App\Filament\Resources\InstallmentPlanResource::getUrl('index')" size="sm" class="mt-2">{{ __('Open installment plans') }} →</x-filament::link>
            @endif
        </x-filament::section>

        <x-filament::section :heading="__('Doctor commission earned')">
            @forelse ($r['commissions'] as $c)
                <div class="{{ $row }}"><span>{{ $c['doctor'] }}</span><span class="font-semibold">{{ $money($c['amount']) }}</span></div>
            @empty
                <p class="{{ $muted }}">{{ __('No commission on this day.') }}</p>
            @endforelse
        </x-filament::section>
    </div>
</x-filament-panels::page>
