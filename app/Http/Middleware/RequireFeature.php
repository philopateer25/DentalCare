<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Filament\Facades\Filament;
use App\Services\FeatureManager;

class RequireFeature
{
    /**
     * Handle an incoming request and ensure feature is enabled for current tenant.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $tenant = Filament::getTenant() ?? $request->user()?->practice;

        if ($tenant && !FeatureManager::isEnabled($feature, $tenant)) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => "Feature '{$feature}' is disabled for your clinic.",
                ], 403);
            }

            abort(403, "Feature '{$feature}' is disabled for your clinic.");
        }

        return $next($request);
    }
}
