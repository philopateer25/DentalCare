<?php

namespace App\Http\Controllers;

use App\Services\DashboardWidgetEngine;
use Illuminate\Http\Request;

class DashboardWidgetController extends Controller
{
    public function __construct(
        protected DashboardWidgetEngine $widgetEngine
    ) {}

    /**
     * Get active ordered widgets and data for the authenticated user's practice.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $practiceId = $user?->practice_id ?? \Filament\Facades\Filament::getTenant()?->id ?? 1;

        $widgets = $this->widgetEngine->getWidgetsForUser($user);
        $payload = [];

        foreach ($widgets as $key => $config) {
            $payload[] = array_merge([
                'enabled' => $config['enabled'],
                'order' => $config['order'],
                'category' => $config['category'],
            ], $this->widgetEngine->getWidgetData($key, $practiceId));
        }

        return response()->json([
            'widgets' => $payload,
            'preferences' => $user?->dashboard_widgets ?? DashboardWidgetEngine::DEFAULT_WIDGETS,
        ]);
    }

    /**
     * Update user dashboard widget preferences (show/hide/reorder).
     */
    public function updatePreferences(Request $request)
    {
        $user = auth()->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $validated = $request->validate([
            'preferences' => 'required|array',
        ]);

        $updated = $this->widgetEngine->updateUserPreferences($user, $validated['preferences']);

        return response()->json([
            'message' => 'Dashboard widget preferences updated successfully.',
            'preferences' => $updated,
        ]);
    }
}
