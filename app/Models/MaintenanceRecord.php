<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A maintenance job (AC repair, X-ray service, chair fix…) that can either
 * close the whole branch, close selected rooms, or leave rooms operational
 * with some equipment missing (booking shows a warning pop-up).
 */
class MaintenanceRecord extends Model
{
    public const IMPACT_CLOSES_BRANCH = 'closes_branch';

    public const IMPACT_CLOSES_ROOMS = 'closes_rooms';

    public const IMPACT_NO_CLOSURE = 'no_closure';

    public const IMPACTS = [
        self::IMPACT_CLOSES_BRANCH => 'Close ALL rooms',
        self::IMPACT_CLOSES_ROOMS => 'Close selected room(s)',
        self::IMPACT_NO_CLOSURE => 'Rooms stay operational',
    ];

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_SCHEDULED => 'Scheduled',
        self::STATUS_IN_PROGRESS => 'In Progress',
        self::STATUS_COMPLETED => 'Completed',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    public const ACTIVE_STATUSES = [self::STATUS_SCHEDULED, self::STATUS_IN_PROGRESS];

    public const CATEGORIES = [
        'ac_hvac' => 'AC / HVAC',
        'dental_chair' => 'Dental Chair / Unit',
        'handpieces' => 'Handpieces & Motors',
        'sterilization' => 'Autoclave / Sterilization',
        'imaging' => 'X-Ray / Imaging',
        'compressor_suction' => 'Compressor / Suction',
        'electrical' => 'Electrical',
        'plumbing' => 'Plumbing / Water',
        'it_network' => 'IT / Network',
        'facility' => 'Facility / Furniture',
        'other' => 'Other',
    ];

    protected $fillable = [
        'practice_id',
        'branch_id',
        'title',
        'category',
        'equipment_name',
        'description',
        'impact',
        'unavailable_items',
        'starts_at',
        'ends_at',
        'status',
        'completed_at',
        'vendor_name',
        'vendor_phone',
        'cost',
        'created_by',
        'notes',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'completed_at' => 'datetime',
        'cost' => 'decimal:2',
    ];

    public function practice(): BelongsTo
    {
        return $this->belongsTo(Practice::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function operatories(): BelongsToMany
    {
        return $this->belongsToMany(Operatory::class, 'maintenance_record_operatory');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES);
    }

    public function scopeOverlapping(Builder $query, CarbonInterface $start, CarbonInterface $end): Builder
    {
        return $query->where('starts_at', '<', $end)->where('ends_at', '>', $start);
    }

    /**
     * Records that touch the given room: branch-wide records of the room's
     * branch, or records that explicitly list the room. A no-closure record
     * with no rooms selected applies to every room in its branch.
     */
    public function scopeAffectingOperatory(Builder $query, Operatory $operatory): Builder
    {
        return $query
            ->where('branch_id', $operatory->branch_id)
            ->where(function (Builder $q) use ($operatory) {
                $q->where('impact', self::IMPACT_CLOSES_BRANCH)
                    ->orWhereHas('operatories', fn (Builder $oq) => $oq->where('operatories.id', $operatory->id))
                    ->orWhere(fn (Builder $nq) => $nq
                        ->where('impact', self::IMPACT_NO_CLOSURE)
                        ->whereDoesntHave('operatories'));
            });
    }

    public function closesRoom(): bool
    {
        return in_array($this->impact, [self::IMPACT_CLOSES_BRANCH, self::IMPACT_CLOSES_ROOMS], true);
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }

    /** IDs of rooms this record affects (all branch rooms when branch-wide). */
    public function affectedOperatoryIds(): array
    {
        if ($this->impact === self::IMPACT_CLOSES_BRANCH
            || ($this->impact === self::IMPACT_NO_CLOSURE && $this->operatories()->doesntExist())) {
            return Operatory::where('branch_id', $this->branch_id)->pluck('id')->all();
        }

        return $this->operatories()->pluck('operatories.id')->all();
    }
}
