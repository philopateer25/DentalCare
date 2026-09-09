<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Practice extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'tax_id',
        'currency',
        'timezone',
        'locale',
        'logo_url',
        'prescription_template',
        'is_active',
        'license_key',
        'license_status',
        'features',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'features' => 'array',
    ];

    public function hasFeature(string $feature): bool
    {
        $features = $this->features;
        if (!is_array($features)) {
            return false;
        }
        return in_array($feature, $features, true) || (isset($features[$feature]) && $features[$feature] === true);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function installmentPlans(): HasManyThrough
    {
        return $this->hasManyThrough(
            InstallmentPlan::class,
            Invoice::class,
            'practice_id',
            'invoice_id',
            'id',
            'id'
        );
    }

    public function doctorCommissions(): HasManyThrough
    {
        return $this->hasManyThrough(
            DoctorCommission::class,
            Payment::class,
            'practice_id',
            'payment_id',
            'id',
            'id'
        );
    }

    public function dentalLabs(): HasMany
    {
        return $this->hasMany(DentalLab::class);
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }
}
