<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Spatie\Permission\Traits\HasRoles;
use NotificationChannels\WebPush\HasPushSubscriptions;

class User extends Authenticatable implements FilamentUser, HasTenants
{
    use HasFactory, Notifiable, HasRoles, HasPushSubscriptions;

    protected $fillable = [
        'name',
        'email',
        'password',
        'practice_id',
        'branch_id',
        'role',
        'phone',
        'locale',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function practice(): BelongsTo
    {
        return $this->belongsTo(Practice::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function doctorProfile(): HasOne
    {
        return $this->hasOne(DoctorProfile::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'doctor_id');
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(DoctorCommission::class, 'doctor_id');
    }

    public function labOrders(): HasMany
    {
        return $this->hasMany(LabOrder::class, 'doctor_id');
    }

    public function isDoctor(): bool
    {
        return $this->hasRole('doctor');
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'system') {
            return $this->hasRole('developer');
        }

        return $this->hasAnyRole(['doctor', 'secretary', 'clinic_admin', 'super_admin']);
    }

    public function getTenants(Panel $panel): array|Collection
    {
        return $this->practice ? collect([$this->practice]) : collect();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->practice_id == $tenant->id;
    }
}
