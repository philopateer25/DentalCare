@props(['warnings' => []])
<div style="display:flex;flex-direction:column;gap:0.6rem;">
    @foreach ($warnings as $w)
        <div class="dc-callout" data-tone="warning"
             style="border:1px solid rgba(var(--warning-500),0.4);background:rgba(var(--warning-500),0.08);color:rgb(var(--warning-700));border-radius:0.75rem;padding:0.75rem 0.9rem;font-size:0.875rem;text-align:start;">
            <p style="font-weight:600;">{{ $w['unavailable_items'] ?: ($w['equipment_name'] ?: $w['title']) }}</p>
            <p style="opacity:.8;margin-top:0.15rem;">
                {{ $w['title'] }}@if ($w['equipment_name']) · {{ $w['equipment_name'] }}@endif
                — {{ $w['starts_at_label'] }} → {{ $w['ends_at_label'] }}
            </p>
        </div>
    @endforeach
</div>
