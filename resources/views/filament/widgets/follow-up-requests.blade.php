<x-filament-widgets::widget>
    {{-- Polls every 5 s: requests from doctors appear without refreshing the page. --}}
    <section class="dx-card dx-fu" wire:poll.5s>
        <header class="dx-card-head">
            <span class="dx-card-icon"><x-filament::icon icon="heroicon-o-inbox-arrow-down" class="h-5 w-5" /></span>
            <div>
                <h3>{{ __('Follow-up requests from doctors') }}</h3>
                <p>{{ __('Doctors send these from the chair. Book them here.') }}</p>
            </div>
            <span class="dx-push dx-chip {{ $pending->isNotEmpty() ? 'dx-chip-danger' : '' }}">{{ $pending->count() }} {{ __('waiting') }}</span>
        </header>

        @if ($pending->isEmpty())
            <div class="dx-empty-note">
                <x-filament::icon icon="heroicon-o-check-circle" class="h-5 w-5" />
                {{ __('No follow-ups waiting. New requests appear here automatically.') }}
            </div>
        @else
            <ul class="dx-fu-list">
                @foreach ($pending as $r)
                    <li wire:key="fu-{{ $r->id }}" class="{{ $r->urgent ? 'is-urgent' : '' }} {{ $r->created_at->gt(now()->subMinutes(2)) ? 'is-new' : '' }}">
                        <div class="dx-fu-main">
                            <p class="dx-fu-title">
                                @if ($r->urgent)<span class="dx-chip dx-chip-danger">{{ __('Urgent') }}</span>@endif
                                @if ($r->created_at->gt(now()->subMinutes(2)))<span class="dx-chip dx-chip-primary">{{ __('New') }}</span>@endif
                                <a href="{{ $patientUrl($r) }}" class="dx-fu-patient">{{ trim($r->patient?->first_name.' '.$r->patient?->last_name) }}</a>
                                <span class="dx-fu-when">{{ __($r->timeframeLabel()) }} · {{ $r->duration_minutes }}m</span>
                            </p>
                            <p class="dx-fu-meta">
                                {{ $r->doctor?->name }} · {{ $r->created_at->diffForHumans() }}
                                @if ($r->procedure_name) · <strong>{{ $r->procedure_name }}</strong>@endif
                                @if ($r->patient?->phone) · {{ $r->patient->phone }}@endif
                            </p>
                            @if ($r->notes)<p class="dx-fu-note">“{{ $r->notes }}”</p>@endif
                        </div>
                        <div class="dx-fu-actions">
                            @if ($wa = $whatsapp($r))
                                <a href="{{ $wa }}" target="_blank" rel="noopener" class="dx-btn dx-btn-soft">WhatsApp</a>
                            @endif
                            <button type="button" class="dx-btn dx-btn-tint" wire:click="mountAction('dismiss', { request: {{ $r->id }} })">{{ __("Can't book") }}</button>
                            <button type="button" class="dx-btn dx-btn-primary" wire:click="mountAction('book', { request: {{ $r->id }} })">{{ __('Book') }}</button>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($recent->isNotEmpty())
            <div class="dx-fu-recent">
                <p class="dx-tile-label">{{ __('Recently handled') }}</p>
                @foreach ($recent as $r)
                    <p>
                        {{ trim($r->patient?->first_name.' '.$r->patient?->last_name) }} ·
                        @if ($r->status === 'booked')
                            <span class="dx-text-success">{{ __('Booked') }} {{ $r->bookedAppointment?->start_time?->format('D j M, h:i A') }}</span>
                        @else
                            <span>{{ __('Not booked') }}@if ($r->handled_note): {{ $r->handled_note }}@endif</span>
                        @endif
                        · {{ $r->handler?->name }}
                    </p>
                @endforeach
            </div>
        @endif
    </section>

    <x-filament-actions::modals />
</x-filament-widgets::widget>
