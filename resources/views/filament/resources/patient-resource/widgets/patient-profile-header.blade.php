@php
    $name = trim($patient->first_name.' '.$patient->last_name);
    $initials = mb_strtoupper(mb_substr($patient->first_name ?? '', 0, 1).mb_substr($patient->last_name ?? '', 0, 1));
    $statusTone = ['active' => 'success', 'inactive' => 'warning', 'archived' => 'danger'][$patient->status] ?? 'primary';
    $invoiceTone = fn ($status) => match ($status) {
        'paid' => 'success',
        'partially_paid' => 'warning',
        'unpaid', 'overdue' => 'danger',
        default => 'primary',
    };
@endphp

<x-filament-widgets::widget>
    {{-- Identity card --}}
    <div class="dx-profile">
        <div class="dx-profile-id">
            <div class="dx-profile-photo">
                <span>{{ $initials ?: '?' }}</span>
                <em class="dx-chip dx-chip-{{ $statusTone }}">{{ ucfirst($patient->status ?? 'active') }}</em>
            </div>
            <div class="dx-profile-main">
                <div class="dx-profile-name-row">
                    <h2 class="dx-profile-name">{{ $name }}</h2>
                    @if ($patient->file_number)
                        <span class="dx-chip dx-chip-primary">#{{ $patient->file_number }}</span>
                    @endif
                </div>
                <div class="dx-profile-facts">
                    @if ($patient->gender || $patient->dob)
                        <span>
                            <x-filament::icon icon="heroicon-o-identification" class="h-4 w-4" />
                            {{ collect([$patient->gender ? ucfirst($patient->gender) : null, $patient->dob ? $patient->dob->age.' yrs ('.$patient->dob->format('j M Y').')' : null])->filter()->implode(', ') }}
                        </span>
                    @endif
                    @if ($history?->blood_type)
                        <span><x-filament::icon icon="heroicon-o-beaker" class="h-4 w-4" /> {{ __('Blood') }}: <strong>{{ $history->blood_type }}</strong></span>
                    @endif
                    @if ($patient->national_id)
                        <span class="dx-mono">{{ __('National ID') }}: {{ $patient->national_id }}</span>
                    @endif
                </div>
                <div class="dx-profile-contact">
                    @if ($patient->phone)
                        <span><x-filament::icon icon="heroicon-o-phone" class="h-4 w-4" /> <strong class="dx-mono">{{ $patient->phone }}</strong></span>
                    @endif
                    @if ($patient->email)
                        <span><x-filament::icon icon="heroicon-o-envelope" class="h-4 w-4" /> {{ $patient->email }}</span>
                    @endif
                    @if ($patient->address)
                        <span><x-filament::icon icon="heroicon-o-map-pin" class="h-4 w-4" /> {{ \Illuminate\Support\Str::limit($patient->address, 60) }}</span>
                    @endif
                </div>
            </div>
        </div>

        <div class="dx-profile-side">
            <div class="dx-balance {{ $balance > 0 ? 'is-due' : '' }}">
                <span class="dx-balance-icon">{{ $balance > 0 ? '!' : '✓' }}</span>
                <div>
                    <span class="dx-tile-label">{{ $balance > 0 ? __('Balance due') : __('Account settled') }}</span>
                    <strong>{{ $money($balance) }}</strong>
                </div>
                <div class="dx-balance-life">
                    <span>{{ __('Lifetime billed') }}</span>
                    <strong>{{ $money($lifetime) }}</strong>
                </div>
            </div>
            <div class="dx-profile-actions">
                <a href="{{ $bookUrl }}" class="dx-btn dx-btn-primary"><x-filament::icon icon="heroicon-o-calendar-days" class="h-4 w-4" /> {{ __('Book appointment') }}</a>
                <a href="{{ $invoiceUrl }}" class="dx-btn dx-btn-tint"><x-filament::icon icon="heroicon-o-document-text" class="h-4 w-4" /> {{ __('New invoice') }}</a>
                @if ($whatsappUrl)
                    <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener" class="dx-btn dx-btn-teal"><x-filament::icon icon="heroicon-o-chat-bubble-left-right" class="h-4 w-4" /> WhatsApp</a>
                @endif
                <a href="{{ $editUrl }}" class="dx-btn dx-btn-tint" title="{{ __('Edit patient') }}"><x-filament::icon icon="heroicon-o-pencil-square" class="h-4 w-4" /></a>
            </div>
        </div>
    </div>

    {{-- Overview cards --}}
    <div class="dx-profile-grid">
        <section class="dx-card">
            <header class="dx-card-head">
                <span class="dx-card-icon dx-card-icon-danger"><x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-5 w-5" /></span>
                <div>
                    <h3>{{ __('Medical alerts & sensitivities') }}</h3>
                    <p>{{ __('Contraindications and safety checks from the medical history') }}</p>
                </div>
            </header>
            @if ($alerts->isEmpty())
                <div class="dx-empty-note">
                    <x-filament::icon icon="heroicon-o-check-circle" class="h-5 w-5" />
                    {{ $history ? __('No alerts recorded in the medical history.') : __('No medical history recorded yet.') }}
                </div>
            @else
                <div class="dx-alert-grid">
                    @foreach ($alerts as $alert)
                        <div class="dx-alert-tile" data-tone="{{ $alert['tone'] }}">
                            <span class="dx-chip dx-chip-{{ $alert['tone'] }}">{{ $alert['tone'] === 'danger' ? __('Critical') : __('Monitor') }}</span>
                            <strong>{{ __($alert['label']) }}</strong>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="dx-card">
            <header class="dx-card-head">
                <span class="dx-card-icon"><x-filament::icon icon="heroicon-o-calendar" class="h-5 w-5" /></span>
                <div>
                    <h3>{{ __('Upcoming chair visit') }}</h3>
                    <p>{{ __('Next scheduled appointment') }}</p>
                </div>
                @if ($upcoming)
                    <span class="dx-live-pill dx-push"><span></span>{{ ucfirst(str_replace('_', ' ', $upcoming->status)) }}</span>
                @endif
            </header>
            @if ($upcoming)
                <div class="dx-visit">
                    <div class="dx-visit-when">
                        <strong>{{ $upcoming->start_time->isToday() ? __('Today') : ($upcoming->start_time->isTomorrow() ? __('Tomorrow') : '') }} {{ $upcoming->start_time->format('M j, Y') }}</strong>
                        <span class="dx-mono">{{ $upcoming->start_time->format('H:i') }} – {{ $upcoming->end_time?->format('H:i') }}</span>
                    </div>
                    <ul>
                        @if ($upcoming->doctor)<li><x-filament::icon icon="heroicon-o-user" class="h-4 w-4" /> {{ $upcoming->doctor->name }}</li>@endif
                        @if ($upcoming->operatory)<li><x-filament::icon icon="heroicon-o-building-office" class="h-4 w-4" /> {{ $upcoming->operatory->name }}</li>@endif
                        @if ($upcoming->procedure_name)<li><x-filament::icon icon="heroicon-o-wrench" class="h-4 w-4" /> {{ $upcoming->procedure_name }}</li>@endif
                    </ul>
                    @if ($appointmentUrl)
                        <a href="{{ $appointmentUrl }}" class="dx-btn dx-btn-primary dx-btn-block">{{ __('Open appointment') }}</a>
                    @endif
                </div>
            @else
                <div class="dx-empty-note">
                    <x-filament::icon icon="heroicon-o-calendar" class="h-5 w-5" />
                    {{ __('No upcoming appointment.') }}
                    <a href="{{ $bookUrl }}" class="dx-link">{{ __('Book one') }}</a>
                </div>
            @endif
            @if ($previous)
                <div class="dx-visit-prev">
                    <span class="dx-check">✓</span>
                    <div>
                        <strong>{{ __('Previous') }}: {{ $previous->start_time->format('M j, Y') }}</strong>
                        <span>{{ $previous->procedure_name ?? __('Visit') }}{{ $previous->doctor ? ' · '.$previous->doctor->name : '' }}</span>
                    </div>
                </div>
            @endif
        </section>

        <section class="dx-card dx-card-wide">
            <header class="dx-card-head">
                <span class="dx-card-icon"><x-filament::icon icon="heroicon-o-banknotes" class="h-5 w-5" /></span>
                <div>
                    <h3>{{ __('Ledger & invoices') }}</h3>
                    <p>{{ __('Most recent billing for this patient') }}</p>
                </div>
                <a href="{{ $invoicesUrl }}" class="dx-link dx-push">{{ __('All invoices') }} →</a>
            </header>
            @if ($invoices->isEmpty())
                <div class="dx-empty-note">{{ __('No invoices yet.') }}</div>
            @else
                <table class="dx-mini-table">
                    <thead><tr><th>{{ __('Invoice') }}</th><th>{{ __('Date') }}</th><th class="dx-num">{{ __('Total') }}</th><th class="dx-num">{{ __('Due') }}</th><th>{{ __('Status') }}</th></tr></thead>
                    <tbody>
                        @foreach ($invoices as $invoice)
                            <tr>
                                <td class="dx-mono"><a class="dx-link" href="{{ \App\Filament\Resources\InvoiceResource::getUrl('edit', ['record' => $invoice]) }}">#{{ $invoice->invoice_number }}</a></td>
                                <td>{{ optional($invoice->issue_date)->format('M j, Y') }}</td>
                                <td class="dx-num dx-mono">{{ $money($invoice->total_amount) }}</td>
                                <td class="dx-num dx-mono {{ (float) $invoice->balance_due > 0 ? 'dx-text-danger' : '' }}">{{ $money($invoice->balance_due) }}</td>
                                <td><span class="dx-chip dx-chip-{{ $invoiceTone($invoice->status) }}">{{ strtoupper(str_replace('_', ' ', $invoice->status)) }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>
    </div>
</x-filament-widgets::widget>
