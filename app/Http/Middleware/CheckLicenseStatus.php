<?php

namespace App\Http\Middleware;

use App\Services\LicenseManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class CheckLicenseStatus
{
    public function __construct(
        private readonly LicenseManager $licenseManager,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->licenseManager->isLockedOut()) {
            Log::warning('License is locked out!', ['state' => $this->licenseManager->getLicenseState()]);

            abort(403, 'Your clinic\'s license is currently inactive or suspended. Please contact system support.');
        }

        return $next($request);
    }
}
