<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FeatureManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PracticeFeatureController extends Controller
{
    /**
     * Get feature flag statuses for the current user's practice.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $practice = $user->practice;

        if (!$practice) {
            return response()->json(['message' => 'No practice context found.'], 400);
        }

        $allFeatures = FeatureManager::FEATURES;
        $practiceFeatures = $practice->features ?? [];

        $featuresStatus = [];
        foreach ($allFeatures as $key => $meta) {
            $featuresStatus[$key] = [
                'key' => $key,
                'name' => $meta['name'],
                'category' => $meta['category'],
                'system_only' => $meta['system_only'],
                'enabled' => $practice->hasFeature($key),
            ];
        }

        return response()->json([
            'practice_id' => $practice->id,
            'practice_name' => $practice->name,
            'features' => $featuresStatus,
        ]);
    }

    /**
     * Update features for the current user's practice.
     * System-only features cannot be toggled by clinic admins.
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $practice = $user->practice;

        if (!$practice) {
            return response()->json(['message' => 'No practice context found.'], 400);
        }

        $request->validate([
            'features' => 'present|array',
        ]);

        $updatedFeatures = FeatureManager::updatePracticeFeatures($practice, $request->input('features'), $user);

        return response()->json([
            'message' => 'Practice features updated successfully.',
            'features' => $updatedFeatures,
        ]);
    }
}
