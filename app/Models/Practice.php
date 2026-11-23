<?php

namespace App\Models;

use App\Services\FeatureManager;
use App\Services\LicenseManager;
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
        'package_tier',
        'chair_limit',
        'xray_limit',
        'features',
        'onboarding_completed_at',
        'onboarding_step',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'chair_limit' => 'integer',
        'xray_limit' => 'integer',
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
        // The signed license from the central app is the ceiling: the clinic
        // can switch licensed features off, never switch unlicensed ones on.
        $license = app(LicenseManager::class);

        if (! $license->allowsFeature($feature)) {
            return false;
        }

        // Rollout stage (Phase 3): licensed, but not shown to this clinic yet.
        if (! $license->stageAllows($feature)) {
            return false;
        }

        $features = $this->features;

        if (is_null($features)) {
            return $this->featureDefault($feature);
        }

        if (! is_array($features)) {
            return false;
        }

        // Legacy associative format: ['feature_key' => true/false]
        if (! array_is_list($features)) {
            return ! empty($features[$feature]);
        }

        // Current flat-array format: ['feature_key', ...]
        return in_array($feature, $features, true);
    }

    /**
     * How many active chairs (rooms) this clinic may have: the signed license
     * from the central app when there is one, otherwise the practice's own
     * setting. 0 or null = no limit.
     */
    public function effectiveChairLimit(): ?int
    {
        $limit = app(LicenseManager::class)->chairLimit() ?? $this->chair_limit;

        return $limit === null || (int) $limit <= 0 ? null : (int) $limit;
    }

    /** Active chairs (rooms) across all branches of this clinic. */
    public function activeChairCount(): int
    {
        return Operatory::query()
            ->where('is_active', true)
            ->whereHas('branch', fn ($q) => $q->where('practice_id', $this->id))
            ->count();
    }

    /**
     * Get all enabled feature keys for this practice.
     */
    public function getEnabledFeatures(): array
    {
        $allFeatures = array_keys(FeatureManager::FEATURES);

        return array_values(array_filter($allFeatures, fn ($f) => $this->hasFeature($f)));
    }

    /**
     * Resolve the global default for a feature flag.
     */
    private function featureDefault(string $feature): bool
    {
        return FeatureManager::FEATURES[$feature]['default'] ?? true;
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

    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(MaintenanceRecord::class);
    }

    public function clinicExpenses(): HasMany
    {
        return $this->hasMany(ClinicExpense::class);
    }

    /** The clinic's own dropdown values (Phase 5). */
    public function practiceOptions(): HasMany
    {
        return $this->hasMany(PracticeOption::class);
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
