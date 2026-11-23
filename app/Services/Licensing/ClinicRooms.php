<?php

namespace App\Services\Licensing;

use App\Models\Branch;
use App\Models\Operatory;
use App\Models\Practice;
use Illuminate\Validation\ValidationException;

/**
 * Rooms (chairs) as seen and changed by the central app.
 *
 * The heartbeat reports the clinic's rooms (names only: no patient data), and
 * the central app can add, rename or switch rooms on/off with remote commands.
 * The chair limit from the license applies exactly as in the clinic's own
 * Rooms & chairs screen.
 */
class ClinicRooms
{
    /** The clinic of this install (on-premise: one clinic per PC). */
    public function practice(): ?Practice
    {
        return Practice::query()->orderBy('id')->first();
    }

    /** @return list<array{name: string, branch: string, active: bool}> */
    public function report(): array
    {
        $practice = $this->practice();
        if (! $practice) {
            return [];
        }

        return Operatory::query()
            ->with('branch:id,name')
            ->whereHas('branch', fn ($q) => $q->where('practice_id', $practice->id))
            ->orderBy('name')
            ->limit(100)
            ->get()
            ->map(fn (Operatory $o) => ['name' => (string) $o->name, 'branch' => (string) ($o->branch?->name ?? ''), 'active' => (bool) $o->is_active])
            ->all();
    }

    /** @return array{0: bool, 1: string} */
    public function add(array $args): array
    {
        $practice = $this->practice();
        $name = $this->cleanName($args['name'] ?? '');
        if (! $practice || $name === '') {
            return [false, 'A room name is required.'];
        }

        $branch = $this->branch($practice, (string) ($args['branch'] ?? ''));
        if (Operatory::where('branch_id', $branch->id)->where('name', $name)->exists()) {
            return [false, "A room called '{$name}' already exists in {$branch->name}."];
        }

        return $this->attempt(function () use ($branch, $name) {
            Operatory::create(['branch_id' => $branch->id, 'name' => $name, 'is_active' => true]);

            return "Room '{$name}' added to {$branch->name}.";
        });
    }

    /** @return array{0: bool, 1: string} */
    public function rename(array $args): array
    {
        $room = $this->find((string) ($args['from'] ?? ''));
        $to = $this->cleanName($args['to'] ?? '');
        if (! $room || $to === '') {
            return [false, 'Room not found, or the new name is empty.'];
        }
        if (Operatory::where('branch_id', $room->branch_id)->where('name', $to)->whereKeyNot($room->id)->exists()) {
            return [false, "A room called '{$to}' already exists."];
        }

        $from = $room->name;
        $room->update(['name' => $to]);

        return [true, "Room '{$from}' renamed to '{$to}'."];
    }

    /** @return array{0: bool, 1: string} */
    public function setActive(array $args): array
    {
        $room = $this->find((string) ($args['name'] ?? ''));
        if (! $room) {
            return [false, 'Room not found.'];
        }
        $active = filter_var($args['active'] ?? true, FILTER_VALIDATE_BOOL);

        return $this->attempt(function () use ($room, $active) {
            $room->update(['is_active' => $active]);

            return "Room '{$room->name}' switched ".($active ? 'on' : 'off').'.';
        });
    }

    private function find(string $name): ?Operatory
    {
        $practice = $this->practice();
        $name = $this->cleanName($name);

        return $practice && $name !== ''
            ? Operatory::query()->whereHas('branch', fn ($q) => $q->where('practice_id', $practice->id))->where('name', $name)->first()
            : null;
    }

    /** The named branch, else the clinic's first branch, else a new "Main" branch. */
    private function branch(Practice $practice, string $name): Branch
    {
        $name = $this->cleanName($name);
        $query = Branch::query()->where('practice_id', $practice->id);

        return ($name !== '' ? (clone $query)->where('name', $name)->first() : null)
            ?? $query->orderBy('id')->first()
            ?? Branch::create(['practice_id' => $practice->id, 'name' => $name !== '' ? $name : 'Main', 'is_active' => true]);
    }

    /** @return array{0: bool, 1: string} */
    private function attempt(callable $change): array
    {
        try {
            return [true, $change()];
        } catch (ValidationException $e) {
            return [false, (string) collect($e->errors())->flatten()->first()];
        }
    }

    private function cleanName(mixed $value): string
    {
        return mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $value) ?? ''), 0, 100);
    }
}
