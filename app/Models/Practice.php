<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Practice extends Model
{
    use HasFactory;

    public const TYPES = [
        'dental' => 'Dental Clinic',
        'ophthalmology' => 'Ophthalmology (Eyes)',
        'plastic_surgery' => 'Plastic Surgery',
        'general' => 'General Medical',
    ];

    /** Default onboarding step for auto-completed practices. */
    private const DEFAULT_ONBOARDING_STEP = 6;

    protected $fillable = [
        'name',
        'type',
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
        'onboarding_completed_at',
        'onboarding_step',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'features' => 'array',
        'onboarding_completed_at' => 'datetime',
        'onboarding_step' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Practice $practice) {
            if ($practice->onboarding_completed_at === null && $practice->onboarding_step === null) {
                $practice->onboarding_completed_at = now();
                $practice->onboarding_step = self::DEFAULT_ONBOARDING_STEP;
            }
        });
    }

    // ──────────────────────────────────────────────
    //  Feature helpers
    // ──────────────────────────────────────────────

    /**
     * Check whether a feature is enabled for this practice.
     *
     * Supports both the legacy associative format `['feature' => true]`
     * and the current flat-array format `['feature']`.
     */
    public function hasFeature(string $feature): bool
    {
        $features = $this->features;

        if (is_null($features)) {
            return $this->featureDefault($feature);
        }

        if (!is_array($features)) {
            return false;
        }

        // Legacy associative format: ['feature_key' => true/false]
        if (!array_is_list($features)) {
            return !empty($features[$feature]);
        }

        // Current flat-array format: ['feature_key', ...]
        return in_array($feature, $features, true);
    }

    /**
     * Resolve the global default for a feature flag.
     */
    private function featureDefault(string $feature): bool
    {
        return \App\Services\FeatureManager::FEATURES[$feature]['default'] ?? true;
    }

    // ──────────────────────────────────────────────
    //  Status helpers
    // ──────────────────────────────────────────────

    public function isOnboarded(): bool
    {
        return $this->onboarding_completed_at !== null;
    }

    public function isLicenseActive(): bool
    {
        return $this->license_status === 'active';
    }

    // ──────────────────────────────────────────────
    //  Relationships
    // ──────────────────────────────────────────────

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

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function clinicExpenses(): HasMany
    {
        return $this->hasMany(ClinicExpense::class);
    }

    public function insuranceClaims(): HasMany
    {
        return $this->hasMany(InsuranceClaim::class);
    }

    public function insuranceProviders(): HasMany
    {
        return $this->hasMany(InsuranceProvider::class);
    }

    public function labOrders(): HasMany
    {
        return $this->hasMany(LabOrder::class);
    }

    public function patientInsurancePolicies(): HasMany
    {
        return $this->hasMany(PatientInsurancePolicy::class);
    }

    public function payrollSlips(): HasMany
    {
        return $this->hasMany(PayrollSlip::class);
    }

    public function staffMembers(): HasMany
    {
        return $this->hasMany(StaffMember::class);
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function communicationLogs(): HasMany
    {
        return $this->hasMany(CommunicationLog::class);
    }

    public function whatsAppAppointmentRequests(): HasMany
    {
        return $this->hasMany(WhatsAppAppointmentRequest::class);
    }
}
