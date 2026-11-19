@php
    $name = trim($patient->first_name.' '.$patient->last_name);
    $initials = mb_strtoupper(mb_substr($patient->first_name ?? '', 0, 1).mb_substr($patient->last_name ?? '', 0, 1));
    $meta = collect([
        $patient->gender ? ucfirst($patient->gender) : null,
        $patient->dob ? $patient->dob->age.' yrs (DOB: '.$patient->dob->format('j M Y').')' : null,
    ])->filter()->implode(', ');
@endphp

<div class="dx-snapshot">
    <div class="dx-snapshot-head">
        <span class="dx-avatar dx-avatar-lg">{{ $initials ?: '?' }}</span>
        <div class="dx-snapshot-id">
            <div class="dx-snapshot-name-row">
                <span class="dx-snapshot-name">{{ $name }}</span>
                @if ($patient->file_number)
                    <span class="dx-chip dx-chip-primary">#{{ $patient->file_number }}</span>
                @endif
                @if ($patient->national_id)
                    <span class="dx-chip">{{ __('National ID') }}: {{ $patient->national_id }}</span>
                @endif
            </div>
            <div class="dx-snapshot-meta">
                @if ($patient->phone)
                    <span>{{ $patient->phone }}</span>
                @endif
                @if ($meta)
                    <span class="dx-dot">&bull;</span><span>{{ $meta }}</span>
                @endif
                @if ($bloodType)
                    <span class="dx-dot">&bull;</span><span>{{ __('Blood') }} {{ $bloodType }}</span>
                @endif
            </div>
        </div>
    </div>

    <div class="dx-snapshot-tiles">
        <div class="dx-tile">
            <span class="dx-tile-label">{{ __('Last visit') }}</span>
            <span class="dx-tile-value">{{ $lastVisit?->start_time?->format('M j, Y') ?? __('No visits yet') }}</span>
            <span class="dx-tile-sub">{{ $lastVisit?->procedure_name ?? '—' }}</span>
        </div>
        <div class="dx-tile">
            <span class="dx-tile-label">{{ __('Account ledger') }}</span>
            <span class="dx-tile-value {{ $balance > 0 ? 'dx-text-danger' : 'dx-text-success' }}">
                {{ $balance > 0 ? $balanceText.' '.__('outstanding') : __('Settled') }}
            </span>
            <span class="dx-tile-sub">{{ $balance > 0 ? __('Collect at checkout') : __('No open balance') }}</span>
        </div>
        <div class="dx-tile {{ $alerts->contains('tone', 'danger') ? 'dx-tile-danger' : '' }}">
            <span class="dx-tile-label">{{ __('Medical alerts') }}</span>
            @if ($alerts->isEmpty())
                <span class="dx-tile-value">{{ __('None recorded') }}</span>
                <span class="dx-tile-sub">{{ __('Check medical history before treatment') }}</span>
            @else
                <span class="dx-alert-list">
                    @foreach ($alerts as $alert)
                        <span class="dx-chip dx-chip-{{ $alert['tone'] }}">{{ __($alert['label']) }}</span>
                    @endforeach
                </span>
            @endif
        </div>
    </div>
</div>
