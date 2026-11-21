@props(['warnings' => [], 'room' => null])
{{-- "Something will be missing in this room" popup body (design/stitch/05-maintenance). --}}
<div class="dx-mw">
    <div class="dx-mw-info">
        <x-filament::icon icon="heroicon-o-information-circle" class="h-5 w-5" />
        <span>
            {{ __('You are booking') }}@if ($room) {{ __('in') }} <strong class="dx-mw-room">{{ $room }}</strong>@endif
            {{ __('during an active maintenance window. The room stays open, but the items below are unavailable.') }}
        </span>
    </div>

    <p class="dx-mw-label">{{ __('Missing during this appointment') }}</p>

    @foreach ($warnings as $w)
        <div class="dx-mw-item">
            <div class="dx-mw-item-head">
                <x-filament::icon icon="heroicon-o-wrench-screwdriver" class="h-5 w-5" />
                <strong>{{ $w['equipment_name'] ?: $w['title'] }}</strong>
                <span class="dx-chip dx-chip-danger">{{ __('Out until') }} {{ $w['ends_at_label'] }}</span>
            </div>
            @if ($w['unavailable_items'])
                <p><b>{{ __('Unavailable') }}:</b> {{ $w['unavailable_items'] }}</p>
            @endif
            <p class="dx-mw-meta">{{ $w['title'] }} · {{ $w['starts_at_label'] }} → {{ $w['ends_at_label'] }}</p>
        </div>
    @endforeach

    <div class="dx-mw-note">
        <x-filament::icon icon="heroicon-o-clipboard-document-check" class="h-5 w-5" />
        <span>{{ __('Please check with the attending doctor that the planned procedure does not need the missing equipment, or pick another room.') }}</span>
    </div>
</div>
