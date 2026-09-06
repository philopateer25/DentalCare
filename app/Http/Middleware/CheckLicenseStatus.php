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
        $tenant = \Filament\Facades\Filament::getTenant();

        if ($tenant && $tenant->license_status === 'suspended') {
            abort(403, 'Your clinic\'s license is currently suspended due to pending payments. Please contact system support.');
        }

        return $next($request);
    }
}
