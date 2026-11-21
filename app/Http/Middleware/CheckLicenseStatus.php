<?php

namespace App\Http\Middleware;

use App\Filament\Pages\LicensePage;
use App\Services\LicenseManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the license mode to every web and Filament request.
 *
 *  - full:      nothing happens.
 *  - read_only: pages open, but form posts are refused (model writes are also
 *               blocked by ReadOnlyGuard for Livewire/Filament actions).
 *  - locked:    everything redirects to the License page, which still allows
 *               syncing, entering an unlock code and exporting data.
 *
 * Login, logout, the License page and data export always work.
 */
class CheckLicenseStatus
{
    public function __construct(
        private readonly LicenseManager $licenseManager,
    ) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $mode = $this->licenseManager->getMode();

        if ($mode === LicenseManager::MODE_FULL || $this->isAlwaysAllowed($request)) {
            return $next($request);
        }

        // Guests: let the route's own auth middleware send them to login.
        if (! $request->user()) {
            return $next($request);
        }

        $reason = $this->licenseManager->getModeReason() ?? 'The license is not active.';

        if ($mode === LicenseManager::MODE_READ_ONLY && $request->isMethodSafe()) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => $reason, 'license_mode' => $mode], 423);
        }

        if ($mode === LicenseManager::MODE_READ_ONLY) {
            return redirect()->back()->with('error', $reason);
        }

        $practice = $request->user()->practice;

        if ($practice) {
            return redirect()->to(LicensePage::getUrl(tenant: $practice));
        }

        abort(423, $reason);
    }

    private function isAlwaysAllowed(Request $request): bool
    {
        $patterns = (array) config('license.always_allowed_routes', []);

        return $patterns !== [] && $request->route() !== null && $request->routeIs(...$patterns);
    }
}
