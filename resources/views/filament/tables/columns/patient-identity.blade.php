@php
    /** @var \App\Models\Patient $record */
    $record = $getRecord();
    $name = trim($record->first_name.' '.$record->last_name);
    $initials = mb_strtoupper(mb_substr($record->first_name ?? '', 0, 1).mb_substr($record->last_name ?? '', 0, 1));
    $tones = ['blue', 'teal', 'violet', 'amber'];
    $tone = $tones[$record->id % count($tones)];
    $meta = collect([
        $record->dob ? $record->dob->age.'y' : null,
        $record->gender ? ucfirst($record->gender) : null,
    ])->filter()->implode(' • ');
@endphp

<div class="dx-identity">
    <span class="dx-avatar" data-tone="{{ $tone }}">{{ $initials ?: '?' }}</span>
    <span class="dx-identity-text">
        <span class="dx-identity-name">{{ $name }}</span>
        @if ($meta)
            <span class="dx-identity-meta">{{ $meta }}</span>
        @endif
    </span>
</div>
