<?php

namespace App\Services\Licensing;

use App\Exceptions\ReadOnlyModeException;
use App\Services\LicenseManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;

/**
 * Enforces read-only / locked mode inside the app:
 *
 *  1. A Gate::before hook denies create/update/delete abilities, so Filament
 *     hides those buttons and blocks those pages.
 *  2. A catch-all Eloquent listener refuses any model save/delete during a
 *     web request, covering custom actions and API endpoints that don't go
 *     through a policy.
 *
 * Console work (scheduler, backups, heartbeat, migrations) is never blocked.
 * Writes made with the raw query builder are not covered by (2).
 */
class ReadOnlyGuard
{
    public const WRITE_ABILITIES = [
        'create', 'update', 'delete', 'deleteAny', 'forceDelete', 'forceDeleteAny',
        'restore', 'restoreAny', 'replicate', 'reorder',
        'attach', 'attachAny', 'detach', 'detachAny',
        'associate', 'associateAny', 'dissociate', 'dissociateAny',
    ];

    /** Must be registered before any Gate::before that grants super admins everything. */
    public static function register(): void
    {
        Gate::before(function ($user, string $ability) {
            if (in_array($ability, self::WRITE_ABILITIES, true) && self::blocksWrites()) {
                return false;
            }

            return null;
        });

        Event::listen(['eloquent.saving: *', 'eloquent.deleting: *', 'eloquent.restoring: *'], function (string $event, array $payload) {
            $model = $payload[0] ?? null;

            if ($model instanceof Model && self::blocksWrites() && ! self::isExempt($model)) {
                throw new ReadOnlyModeException(app(LicenseManager::class)->getModeReason());
            }
        });
    }

    public static function blocksWrites(): bool
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return false;
        }

        $manager = app(LicenseManager::class);

        return $manager->isEnforced() && $manager->isReadOnly();
    }

    private static function isExempt(Model $model): bool
    {
        foreach ((array) config('license.read_only_exempt_models', []) as $class) {
            if ($model instanceof $class) {
                return true;
            }
        }

        return false;
    }
}
