<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\PerioCharting;
use App\Services\Dental\OdontogramRules;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * 6-site periodontal chart (gum chart) for the Chart workspace.
 *
 * Sites per tooth, in this order: DB, B, MB (buccal) then DL, L, ML (lingual/palatal).
 * GM is the gingival margin relative to the CEJ: negative = recession, positive = overgrowth.
 * CAL (clinical attachment level) = PD − GM.
 */
class PerioChartController extends Controller
{
    public const SITES = ['DB', 'B', 'MB', 'DL', 'L', 'ML'];

    protected function authorizePatient(Patient $patient): void
    {
        abort_unless($patient->practice_id === auth()->user()->practice_id, 403, 'Unauthorized cross-tenant patient access.');
    }

    public function index(Patient $patient)
    {
        $this->authorizePatient($patient);

        $charts = $patient->perioChartings()->with('doctor:id,name')->orderByDesc('exam_date')->orderByDesc('id')->get();

        return response()->json($charts->map(fn ($c) => $this->present($c)));
    }

    /** Start a new perio chart (empty; the previous chart stays for comparison). */
    public function store(Patient $patient)
    {
        $this->authorizePatient($patient);

        $chart = $patient->perioChartings()->create([
            'exam_date' => now()->toDateString(),
            'examined_by_doctor_id' => auth()->id(),
            'dental_examination_id' => $patient->examinations()->max('id'),
            'probe_depths_json' => (object) [],
        ]);

        return response()->json($this->present($chart->fresh('doctor')), 201);
    }

    /** Save the whole chart (the workspace debounces edits into one request). */
    public function update(Request $request, PerioCharting $chart)
    {
        abort_unless($chart->patient?->practice_id === auth()->user()->practice_id, 403, 'Unauthorized cross-tenant access.');

        $data = $request->validate([
            'probe_depths' => 'nullable|array',
            'gingival_margins' => 'nullable|array',
            'bleeding' => 'nullable|array',
            'suppuration' => 'nullable|array',
            'plaque' => 'nullable|array',
            'mobility' => 'nullable|array',
            'furcation' => 'nullable|array',
            'notes' => 'nullable|string|max:5000',
        ]);

        $chart->update([
            'probe_depths_json' => (object) $this->sites($data['probe_depths'] ?? [], 'probe_depths', fn ($v) => $this->int($v, 0, 15)),
            'gingival_margins_json' => (object) $this->sites($data['gingival_margins'] ?? [], 'gingival_margins', fn ($v) => $this->int($v, -15, 10)),
            'bleeding_on_probing_json' => (object) $this->sites($data['bleeding'] ?? [], 'bleeding', fn ($v) => (bool) $v),
            'suppuration_json' => (object) $this->sites($data['suppuration'] ?? [], 'suppuration', fn ($v) => (bool) $v),
            'plaque_index_json' => (object) $this->sites($data['plaque'] ?? [], 'plaque', fn ($v) => (bool) $v),
            'mobility_json' => (object) $this->perTooth($data['mobility'] ?? [], 'mobility', 0, 3),
            'furcation_json' => (object) $this->perTooth($data['furcation'] ?? [], 'furcation', 0, 4),
            'notes' => $data['notes'] ?? $chart->notes,
        ]);

        return response()->json($this->present($chart->fresh('doctor')));
    }

    /** @return array<string, array<int, mixed>> */
    protected function sites(array $map, string $field, callable $cast): array
    {
        $out = [];
        foreach ($map as $tooth => $values) {
            $this->tooth($tooth, $field);
            if (! is_array($values) || count($values) !== 6) {
                throw ValidationException::withMessages([$field => "Tooth {$tooth} needs exactly 6 site values."]);
            }
            $out[(string) $tooth] = array_map(fn ($v) => $v === null || $v === '' ? null : $cast($v), array_values($values));
        }

        return $out;
    }

    /** @return array<string, int> */
    protected function perTooth(array $map, string $field, int $min, int $max): array
    {
        $out = [];
        foreach ($map as $tooth => $value) {
            $this->tooth($tooth, $field);
            if ($value === null || $value === '') {
                continue;
            }
            $out[(string) $tooth] = $this->int($value, $min, $max, $field);
        }

        return $out;
    }

    protected function tooth(string|int $tooth, string $field): void
    {
        if (! OdontogramRules::isValidFdi($tooth)) {
            throw ValidationException::withMessages([$field => "Invalid FDI tooth number: {$tooth}."]);
        }
    }

    protected function int(mixed $v, int $min, int $max, string $field = 'value'): int
    {
        if (! is_numeric($v) || (int) $v != $v || $v < $min || $v > $max) {
            throw ValidationException::withMessages([$field => "Values must be whole numbers from {$min} to {$max}."]);
        }

        return (int) $v;
    }

    protected function present(PerioCharting $c): array
    {
        $pd = (array) ($c->probe_depths_json ?? []);
        $gm = (array) ($c->gingival_margins_json ?? []);
        $bop = (array) ($c->bleeding_on_probing_json ?? []);
        $pi = (array) ($c->plaque_index_json ?? []);

        return [
            'id' => $c->id,
            'exam_date' => $c->exam_date?->toDateString(),
            'doctor' => $c->doctor?->name,
            'dental_examination_id' => $c->dental_examination_id,
            'probe_depths' => (object) $pd,
            'gingival_margins' => (object) $gm,
            'bleeding' => (object) $bop,
            'suppuration' => (object) ($c->suppuration_json ?? []),
            'plaque' => (object) $pi,
            'mobility' => (object) ($c->mobility_json ?? []),
            'furcation' => (object) ($c->furcation_json ?? []),
            'notes' => $c->notes,
            'summary' => self::summary($pd, $gm, $bop, $pi),
        ];
    }

    /** Headline numbers: bleeding %, plaque %, mean PD, deep sites, worst CAL. */
    public static function summary(array $pd, array $gm, array $bop, array $pi): array
    {
        $depths = [];
        $cal = [];
        foreach ($pd as $tooth => $sites) {
            foreach ((array) $sites as $i => $d) {
                if ($d === null) {
                    continue;
                }
                $depths[] = (int) $d;
                $cal[] = (int) $d - (int) (((array) ($gm[$tooth] ?? []))[$i] ?? 0);
            }
        }
        $flags = fn (array $map) => collect($map)->flatMap(fn ($s) => (array) $s)->filter(fn ($v) => $v !== null);
        $bopSites = $flags($bop);
        $piSites = $flags($pi);

        return [
            'sites_recorded' => count($depths),
            'mean_pd' => $depths ? round(array_sum($depths) / count($depths), 1) : null,
            'sites_4_5' => count(array_filter($depths, fn ($d) => $d >= 4 && $d <= 5)),
            'sites_6_plus' => count(array_filter($depths, fn ($d) => $d >= 6)),
            'max_cal' => $cal ? max($cal) : null,
            'bop_percent' => $bopSites->count() ? (int) round($bopSites->filter()->count() / $bopSites->count() * 100) : null,
            'plaque_percent' => $piSites->count() ? (int) round($piSites->filter()->count() / $piSites->count() * 100) : null,
        ];
    }
}
