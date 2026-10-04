<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckLicenseStatus
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $licenseManager = app(\App\Services\LicenseManager::class);

        if ($licenseManager->isLockedOut()) {
            \Illuminate\Support\Facades\Log::warning("License is locked out!", ['state' => $licenseManager->getLicenseState()]);
            // In a real application, you'd redirect to a specific Filament page or standard view.
            // For now, we return a 403 with a specific message.
            abort(403, 'Your clinic\'s license is currently inactive or suspended. Please contact system support.');
        }

        \Illuminate\Support\Facades\Log::info("License is active, allowing request.");
        return $next($request);
    }
}
