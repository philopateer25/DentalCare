<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboardingIsCompleted
{
    /**
     * Handle an incoming request and ensure practice onboarding is complete.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->practice_id) {
            $practice = $user->practice;

            if ($practice && !$practice->isOnboarded()) {
                // If requesting JSON or API, return 403 / redirect payload
                if ($request->expectsJson() || $request->is('api/*')) {
                    if (!$request->is('api/onboarding/*') && !$request->is('onboarding*')) {
                        return response()->json([
                            'message' => 'Clinic onboarding is incomplete. Please complete setup first.',
                            'redirect' => route('onboarding'),
                        ], 403);
                    }
                } elseif (!$request->is('onboarding*') && !$request->is('logout') && !$request->is('system*')) {
                    return redirect()->route('onboarding');
                }
            }
        }

        return $next($request);
    }
}
