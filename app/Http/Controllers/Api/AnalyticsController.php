<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function __construct(
        protected AnalyticsService $analyticsService
    ) {}

    /**
     * Get analytics dashboard metrics for tenant.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $practiceId = $user->practice_id;

        if (!$practiceId) {
            return response()->json(['message' => 'No practice context found.'], 400);
        }

        $startDate = $request->filled('start_date') ? Carbon::parse($request->input('start_date'))->startOfDay() : now()->subMonths(5)->startOfMonth();
        $endDate = $request->filled('end_date') ? Carbon::parse($request->input('end_date'))->endOfDay() : now()->endOfDay();
        $branchId = $request->filled('branch_id') ? (int) $request->input('branch_id') : null;

        $metrics = $this->analyticsService->getMetrics($practiceId, $startDate, $endDate, $branchId);

        return response()->json([
            'practice_id' => $practiceId,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'branch_id' => $branchId,
            'metrics' => $metrics,
        ]);
    }
}
