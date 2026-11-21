<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ in_array(app()->getLocale(), ['ar']) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Dental chart') }} – {{ trim($patient->first_name.' '.$patient->last_name) }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=dm-sans:400,500,600,700" rel="stylesheet">
    <style>
        :root { --blue:#3859a8; --teal:#009e83; --red:#d82c1e; --text:#131b2e; --muted:#5b5f6b; --line:#e3e6f0; --tint:#f2f3ff; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'DM Sans', system-ui, sans-serif; color: var(--text); font-size: 12px; background: #fff; }
        .page { max-width: 820px; margin: 0 auto; padding: 28px 32px; }
        .ribbon { height: 4px; display: flex; } .ribbon span { flex: 1; } .ribbon span:nth-child(1){background:var(--blue)} .ribbon span:nth-child(2){background:var(--teal)} .ribbon span:nth-child(3){background:var(--red)}
        header { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; padding: 16px 0; border-bottom: 1px solid var(--line); }
        .brand { font-size: 22px; font-weight: 700; } .brand .dox { color: #1f1f23; font-weight: 500; } .brand .amigo { color: var(--red); }
        .clinic { color: var(--muted); margin-top: 2px; }
        h1 { font-size: 18px; margin: 18px 0 4px; }
        h2 { font-size: 13px; text-transform: uppercase; letter-spacing: .06em; color: var(--muted); margin: 22px 0 8px; }
        .meta { display: flex; flex-wrap: wrap; gap: 6px 18px; color: var(--muted); }
        .chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
        .chip { padding: 2px 8px; border-radius: 999px; font-size: 10px; font-weight: 700; }
        .chip.danger { background: #ffdad6; color: #93000a; } .chip.warning { background: #fef3c7; color: #92400e; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: start; font-size: 10px; text-transform: uppercase; letter-spacing: .05em; color: var(--muted); background: var(--tint); padding: 6px 8px; }
        td { padding: 7px 8px; border-top: 1px solid var(--line); vertical-align: top; }
        .num { text-align: end; white-space: nowrap; }
        .status { font-weight: 700; } .status.needs_treatment { color: var(--red); } .status.watch { color: #b45309; } .status.existing { color: var(--teal); }
        .totals { display: flex; gap: 12px; margin-top: 10px; } .totals div { flex: 1; background: var(--tint); border-radius: 8px; padding: 8px 10px; } .totals strong { display: block; font-size: 14px; }
        footer { margin-top: 28px; padding-top: 10px; border-top: 1px solid var(--line); color: var(--muted); font-size: 10px; display: flex; justify-content: space-between; }
        .toolbar { position: sticky; top: 0; background: #fff; padding: 10px 32px; border-bottom: 1px solid var(--line); display: flex; gap: 8px; justify-content: flex-end; }
        .toolbar button { font: inherit; font-weight: 600; padding: 8px 14px; border-radius: 8px; border: 0; cursor: pointer; }
        .toolbar .primary { background: var(--blue); color: #fff; } .toolbar .soft { background: var(--tint); color: var(--blue); }
        @media print { .toolbar { display: none; } .page { padding: 0; } @page { margin: 14mm; } }
    </style>
</head>
<body>
<div class="toolbar">
    <button class="soft" onclick="window.close()">{{ __('Close') }}</button>
    <button class="primary" onclick="window.print()">{{ __('Save as PDF / Print') }}</button>
</div>
<div class="ribbon"><span></span><span></span><span></span></div>
<div class="page">
    <header>
        <div>
            <div class="brand"><span class="dox">DOX</span><span class="amigo">amigo</span></div>
            <div class="clinic">{{ $practice?->name }}</div>
        </div>
        <div class="meta" style="text-align:end;display:block">
            <div>{{ __('Printed') }}: {{ now()->format('j M Y, H:i') }}</div>
            @if ($exam)<div>{{ __('Exam') }}: {{ ucfirst($exam->type) }} · {{ optional($exam->examined_at ?? $exam->created_at)->format('j M Y') }}</div>@endif
        </div>
    </header>

    <h1>{{ trim($patient->first_name.' '.$patient->last_name) }}</h1>
    <div class="meta">
        @if ($patient->file_number)<span>{{ __('File') }} #{{ $patient->file_number }}</span>@endif
        @if ($patient->gender)<span>{{ ucfirst($patient->gender) }}</span>@endif
        @if ($patient->dob)<span>{{ $patient->dob->age }} {{ __('yrs') }}</span>@endif
        @if ($patient->medicalHistory?->blood_type)<span>{{ __('Blood') }} {{ $patient->medicalHistory->blood_type }}</span>@endif
    </div>
    @if ($alerts->isNotEmpty())
        <div class="chips">@foreach ($alerts as $a)<span class="chip {{ $a['tone'] }}">{{ __($a['label']) }}</span>@endforeach</div>
    @endif

    <h2>{{ __('Charted teeth') }}</h2>
    @if ($records->isEmpty())
        <p>{{ __('No findings charted. All teeth recorded as healthy.') }}</p>
    @else
        <table>
            <thead><tr><th>{{ __('Tooth') }}</th><th>{{ __('Condition') }}</th><th>{{ __('Status') }}</th><th>{{ __('Surfaces') }}</th><th>{{ __('Diagnosis / notes') }}</th></tr></thead>
            <tbody>
            @foreach ($records as $tooth => $r)
                @php
                    $surfaces = collect((array) $r['surfaces'])->reject(fn ($v) => $v === 'healthy')->map(fn ($v, $k) => strtoupper(substr($k, 0, 1)).': '.str_replace('_', ' ', $v))->implode(', ');
                    $d = (array) $r['clinical_details'];
                    $dx = collect([$d['diagnosis'] ?? null, $d['severity'] ?? null, $d['urgency'] ?? null])->filter()->implode(' · ');
                @endphp
                <tr>
                    <td><strong>#{{ $tooth }}</strong></td>
                    <td>{{ ucfirst(str_replace('_', ' ', $r['condition'])) }}</td>
                    <td class="status {{ $r['status'] }}">{{ ['needs_treatment' => __('Needs treatment'), 'watch' => __('Watch'), 'existing' => __('Existing')][$r['status']] ?? $r['status'] }}</td>
                    <td>{{ $surfaces ?: '—' }}</td>
                    <td>{{ collect([$dx, $r['notes']])->filter()->implode(' — ') ?: '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    @if ($plan)
        @php
            $procs = $plan->phases->flatMap->procedures;
            $total = $procs->sum(fn ($p) => (float) ($p->net_amount ?? $p->fee));
            $done = $procs->where('status', 'completed')->sum(fn ($p) => (float) ($p->net_amount ?? $p->fee));
        @endphp
        <h2>{{ __('Treatment plan') }}: {{ $plan->title }}</h2>
        <table>
            <thead><tr><th>{{ __('Phase') }}</th><th>{{ __('Tooth') }}</th><th>{{ __('Procedure') }}</th><th>{{ __('Status') }}</th><th class="num">{{ __('Fee') }}</th></tr></thead>
            <tbody>
            @foreach ($plan->phases as $phase)
                @foreach ($phase->procedures as $p)
                    <tr>
                        <td>{{ $phase->name }}</td>
                        <td>{{ $p->tooth_number_fdi ? '#'.$p->tooth_number_fdi : '—' }}</td>
                        <td>{{ $p->procedureCode?->code }} {{ $p->procedureCode?->title }}</td>
                        <td>{{ $p->status === 'completed' ? __('Done') : __('Planned') }}</td>
                        <td class="num">{{ $money($p->net_amount ?? $p->fee) }}</td>
                    </tr>
                @endforeach
            @endforeach
            </tbody>
        </table>
        <div class="totals">
            <div>{{ __('Total') }}<strong>{{ $money($total) }}</strong></div>
            <div>{{ __('Completed') }}<strong>{{ $money($done) }}</strong></div>
            <div>{{ __('Remaining') }}<strong>{{ $money($total - $done) }}</strong></div>
        </div>
    @endif

    <footer><span>© {{ now()->year }} DOXamigo Clinical Systems</span><span>{{ __('This summary is for the patient\'s information.') }}</span></footer>
</div>
</body>
</html>
