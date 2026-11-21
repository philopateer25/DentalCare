<?php

namespace App\Filament\Support;

use App\Services\Scheduling\RoomAvailabilityService;
use Closure;
use Filament\Facades\Filament;
use Filament\Forms;
use Illuminate\Support\HtmlString;

/**
 * Reusable Filament form pieces that surface room maintenance while booking:
 * a live warning/closure callout, an optional "I understand" checkbox and a
 * validation rule that blocks closed rooms.
 *
 * Expects sibling fields named operatory_id, start_time and end_time.
 */
class MaintenanceBookingFields
{
    public static function assessFromState(Forms\Get $get, ?int $ignoreAppointmentId = null): ?array
    {
        $tenantId = Filament::getTenant()?->id ?? auth()->user()?->practice_id;
        $operatoryId = $get('operatory_id');
        $start = $get('start_time');
        $end = $get('end_time');

        if (! $tenantId || ! $operatoryId || ! $start || ! $end) {
            return null;
        }

        try {
            $startAt = RoomAvailabilityService::parse($start);
            $endAt = RoomAvailabilityService::parse($end);
        } catch (\Throwable) {
            return null;
        }

        if ($endAt->lte($startAt)) {
            return null;
        }

        // Only maintenance matters here — doctor/chair overlap is not enforced by these forms.
        return app(RoomAvailabilityService::class)->assess((int) $tenantId, (int) $operatoryId, $startAt, $endAt, null, $ignoreAppointmentId);
    }

    public static function notice(): Forms\Components\Placeholder
    {
        return Forms\Components\Placeholder::make('maintenance_notice')
            ->hiddenLabel()
            ->columnSpanFull()
            ->visible(function (Forms\Get $get, $record) {
                $a = static::assessFromState($get, $record?->id);

                return $a && (! empty($a['closures']) || ! empty($a['warnings']));
            })
            ->content(fn (Forms\Get $get, $record) => static::render(static::assessFromState($get, $record?->id)));
    }

    public static function acknowledgement(): Forms\Components\Checkbox
    {
        return Forms\Components\Checkbox::make('acknowledge_maintenance')
            ->label('I understand the equipment above is unavailable — book anyway')
            ->dehydrated(false)
            ->columnSpanFull()
            ->visible(function (Forms\Get $get, $record) {
                $a = static::assessFromState($get, $record?->id);

                return $a && empty($a['closures']) && ! empty($a['warnings']);
            })
            ->accepted();
    }

    /** Validation rule for operatory_id: fails when the room is closed for maintenance. */
    public static function closureRule(): Closure
    {
        return function (Forms\Get $get, $record) {
            return function (string $attribute, $value, Closure $fail) use ($get, $record) {
                $a = static::assessFromState($get, $record?->id);
                if ($a && ($message = app(RoomAvailabilityService::class)->closureMessage($a))) {
                    $fail($message);
                }
            };
        };
    }

    /** Inline-styled callout (Filament's precompiled CSS lacks arbitrary Tailwind classes). */
    public static function callout(string $tone, string $headline, string $listItems, ?string $footer = null): HtmlString
    {
        $style = "border:1px solid rgba(var(--{$tone}-500),0.4);background:rgba(var(--{$tone}-500),0.08);"
            ."color:rgb(var(--{$tone}-700));border-radius:0.75rem;padding:0.9rem 1rem;font-size:0.875rem;line-height:1.4;";
        $footerHtml = $footer ? '<p style="margin-top:0.5rem;opacity:.85">'.$footer.'</p>' : '';

        return new HtmlString(
            '<div class="dc-callout" data-tone="'.$tone.'" style="'.$style.'">'
            .'<p style="font-weight:600">'.$headline.'</p>'
            .'<ul style="margin-top:0.5rem;padding-inline-start:1.25rem;list-style:disc">'.$listItems.'</ul>'
            .$footerHtml.'</div>'
        );
    }

    public static function render(?array $assessment): HtmlString
    {
        if (! $assessment) {
            return new HtmlString('');
        }

        $room = e($assessment['operatory_name'] ?? 'This room');

        if (! empty($assessment['closures'])) {
            $items = collect($assessment['closures'])
                ->map(fn ($c) => '<li><strong>'.e($c['title']).'</strong> — '.e($c['impact_label']).' ('.e($c['starts_at_label']).' → '.e($c['ends_at_label']).')</li>')
                ->implode('');

            return static::callout('danger', "⛔ {$room} is closed for maintenance during this time", $items, 'Choose another room or time.');
        }

        $items = collect($assessment['warnings'])->map(function ($w) {
            $what = $w['unavailable_items'] ?: ($w['equipment_name'] ?: $w['title']);

            return '<li><strong>'.e($what).'</strong><span style="opacity:.75"> — '.e($w['title']).', until '.e($w['ends_at_label']).'</span></li>';
        })->implode('');

        return static::callout('warning', "⚠️ Something will be missing in {$room}", $items, "The room stays bookable, but the patient's procedure may need this equipment.");
    }
}
