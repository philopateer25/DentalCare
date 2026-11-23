<?php

namespace App\Http\Controllers\Api;

use App\Filament\Resources\PracticeOptionResource;
use App\Http\Controllers\Controller;
use App\Models\PracticeOption;
use App\Services\PracticeOptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Phase 5 for the React chairside screens (3D/2D chart workspace): the clinic's
 * own values per list, most used first, and "count this use".
 */
class PracticeOptionController extends Controller
{
    public function __construct(private readonly PracticeOptions $options) {}

    /** GET ?lists=diagnosis,crown_material → { diagnosis: [...], crown_material: [...] } (clinic values only). */
    public function index(Request $request): JsonResponse
    {
        $practiceId = (int) $request->user()->practice_id;
        $lists = array_values(array_intersect(
            explode(',', (string) $request->query('lists', '')),
            array_keys(PracticeOptionResource::LIST_LABELS),
        ));

        $result = [];
        foreach ($lists as $list) {
            $result[$list] = PracticeOption::query()
                ->where('practice_id', $practiceId)
                ->where('list', $list)
                ->orderByRaw('CASE WHEN last_used_at >= ? THEN 0 ELSE 1 END', [now()->subDays(PracticeOptions::RECENT_DAYS)])
                ->orderByDesc('use_count')
                ->orderBy('value')
                ->limit(60)
                ->pluck('value');
        }

        return response()->json($result);
    }

    /** POST { list, value } — remember a value (new or picked) for this clinic. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'list' => ['required', 'string', Rule::in(array_keys(PracticeOptionResource::LIST_LABELS))],
            'value' => ['required', 'string', 'max:'.PracticeOptions::MAX_LENGTH],
        ]);

        $value = PracticeOptions::clean($data['value']);
        if ($value === '') {
            return response()->json(['message' => 'Empty value.'], 422);
        }

        $this->options->record($data['list'], $value, (int) $request->user()->practice_id);

        return response()->json(['ok' => true, 'value' => $value]);
    }
}
