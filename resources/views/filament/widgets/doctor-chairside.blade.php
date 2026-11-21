@php
    $statusLabel = ['booked' => 'Booked', 'confirmed' => 'Confirmed', 'arrived' => 'Waiting', 'in_chair' => 'In chair', 'completed' => 'Done'];
@endphp

<x-filament-widgets::widget>
    <div class="dx-chairside">
        {{-- Current patient --}}
        <section class="dx-card dx-chair-now">
            @if ($current && $patient)
                <div class="dx-chair-head">
                    <span class="dx-avatar dx-avatar-lg">{{ $patient['initials'] ?: '?' }}</span>
                    <div class="dx-chair-id">
                        <p class="dx-chair-kicker">
                            {{ $current->status === 'in_chair' ? __('In the chair now') : ($current->status === 'arrived' ? __('Waiting for you') : __('Next patient')) }}
                            · {{ $current->start_time->format('h:i A') }}@if ($current->operatory) · {{ $current->operatory->name }}@endif
                        </p>
                        <h2 class="dx-chair-name">{{ $patient['name'] }}</h2>
                        <p class="dx-chair-meta">{{ $patient['meta'] }}@if ($current->procedure_name || $current->chief_complaint) · {{ $current->procedure_name ?: $current->chief_complaint }}@endif</p>
                        @if ($patient['alerts']->isNotEmpty() || $patient['balance'])
                            <div class="dx-alert-list">
                                @foreach ($patient['alerts'] as $alert)
                                    <span class="dx-chip dx-chip-{{ $alert['tone'] }}">⚠ {{ __($alert['label']) }}</span>
                                @endforeach
                                @if ($patient['balance'])
                                    <span class="dx-chip dx-chip-warning">{{ __('Balance due') }}: {{ $patient['balance'] }}</span>
                                @endif
                            </div>
                        @endif
                    </div>
                    <div class="dx-chair-status">
                        @if (in_array($current->status, ['booked', 'confirmed', 'arrived']))
                            <button type="button" class="dx-btn dx-btn-primary" wire:click="setStatus({{ $current->id }}, 'in_chair')">{{ __('Seat patient') }}</button>
                        @elseif ($current->status === 'in_chair')
                            <button type="button" class="dx-btn dx-btn-teal" wire:click="setStatus({{ $current->id }}, 'completed')">{{ __('Complete visit') }}</button>
                        @endif
                    </div>
                </div>

                <div class="dx-chair-tools">
                    @foreach ($tools as $tool)
                        <a href="{{ $tool['url'] }}" @if ($tool['external'] ?? false) target="_blank" rel="noopener" @endif
                           class="dx-tool {{ ($tool['primary'] ?? false) ? 'is-primary' : '' }}">
                            <x-filament::icon :icon="$tool['icon']" class="h-6 w-6" />
                            <span>{{ $tool['label'] }}</span>
                        </a>
                    @endforeach
                    <button type="button" class="dx-tool" wire:click="mountAction('requestFollowUp', { appointment: {{ $current->id }} })">
                        <x-filament::icon icon="heroicon-o-calendar-days" class="h-6 w-6" />
                        <span>{{ __('Request follow-up') }}</span>
                    </button>
                </div>
            @else
                <div class="dx-empty-note">
                    <x-filament::icon icon="heroicon-o-sun" class="h-5 w-5" />
                    {{ __('No more patients scheduled for you today.') }}
                </div>
            @endif
        </section>

        {{-- Today's queue --}}
        <section class="dx-card dx-chair-queue">
            @if ($requests->isNotEmpty())
                {{-- Refreshes every 10s so the doctor sees when reception books it. --}}
                <div wire:poll.10s class="dx-fu-mine">
                    <p class="dx-tile-label">{{ __('My follow-up requests') }}</p>
                    @foreach ($requests as $r)
                        <div class="dx-fu-mine-row">
                            <span>{{ trim($r->patient?->first_name.' '.$r->patient?->last_name) }} · {{ __($r->timeframeLabel()) }}</span>
                            @if ($r->status === 'booked')
                                <span class="dx-chip dx-chip-success">{{ __('Booked') }} {{ $r->bookedAppointment?->start_time?->format('D j M, h:i A') }}</span>
                            @elseif ($r->status === 'cancelled')
                                <span class="dx-chip dx-chip-warning" title="{{ $r->handled_note }}">{{ __('Not booked') }}</span>
                            @else
                                <span class="dx-chip dx-chip-primary">{{ __('Waiting for reception') }}</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
            <header class="dx-card-head">
                <span class="dx-card-icon"><x-filament::icon icon="heroicon-o-queue-list" class="h-5 w-5" /></span>
                <div>
                    <h3>{{ __('My patients today') }}</h3>
                    <p>{{ trans_choice(':count appointment|:count appointments', $queue->count(), ['count' => $queue->count()]) }}</p>
                </div>
            </header>
            @if ($queue->isEmpty())
                <div class="dx-empty-note">{{ __('No appointments today.') }}</div>
            @else
                <ul class="dx-queue">
                    @foreach ($queue as $a)
                        <li class="{{ $current && $a->id === $current->id ? 'is-current' : '' }} is-{{ $a->status }}">
                            <span class="dx-queue-time">{{ $a->start_time->format('h:i') }}</span>
                            <span class="dx-queue-name">
                                {{ trim(($a->patient?->first_name ?? '').' '.($a->patient?->last_name ?? '')) }}
                                <small>{{ $statusLabel[$a->status] ?? $a->status }}@if ($a->operatory) · {{ $a->operatory->name }}@endif</small>
                            </span>
                            <span class="dx-queue-actions">
                                @if (in_array($a->status, ['booked', 'confirmed', 'arrived']))
                                    <button type="button" class="dx-btn dx-btn-soft" wire:click="setStatus({{ $a->id }}, 'in_chair')">{{ __('Seat') }}</button>
                                @endif
                                @if ($a->patient_id)
                                    <a class="dx-btn dx-btn-tint" href="{{ $chartUrl($a) }}">{{ __('Chart') }}</a>
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

    <x-filament-actions::modals />
</x-filament-widgets::widget>
