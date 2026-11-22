<?php

namespace App\Filament\Resources\PatientResource\Pages;

use App\Filament\Resources\PatientResource;
use App\Filament\Resources\ProcedureCodeResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

/**
 * Chart workspace: the dental chart (3D/2D), findings, diagnosis, treatment and history for
 * one patient, mounted as React directly inside Filament (no iframe). Replaces the standalone
 * /patients/{id}/odontogram-details page. Data comes from /api/patients/{id}/chart-workspace.
 */
class ChartWorkspace extends Page
{
    use InteractsWithRecord;

    protected static string $resource = PatientResource::class;

    protected static string $view = 'filament.resources.patient-resource.pages.chart-workspace';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless(
            $this->record->practice_id === (Filament::getTenant()?->id ?? auth()->user()->practice_id),
            403,
            'Unauthorized cross-tenant patient access.'
        );
        abort_unless(static::canUseChart(), 403);
    }

    public static function canUseChart(): bool
    {
        return (auth()->user()?->hasAnyRole(['doctor', 'clinic_admin', 'super_admin', 'developer']) ?? false)
            && \App\Services\FeatureManager::stageAllows('clinical');
    }

    public function getTitle(): string
    {
        return __('Dental chart').' – '.trim($this->record->first_name.' '.$this->record->last_name);
    }

    public function getHeading(): string
    {
        return '';
    }

    public function getConfig(): array
    {
        $tenant = Filament::getTenant();

        return [
            'patientId' => $this->record->id,
            'dataUrl' => url("/api/patients/{$this->record->id}/chart-workspace"),
            'printUrl' => route('patient.chart.print', $this->record),
            'patientUrl' => PatientResource::getUrl('view', ['record' => $this->record]),
            'pricingUrl' => class_exists(ProcedureCodeResource::class) ? ProcedureCodeResource::getUrl('index') : null,
            'has3d' => (bool) ($tenant?->hasFeature('3d_model') ?? false),
            'modelUrl' => '/models/Dentition_Permanent_Full_V2.1.glb',
            'whatsappUrl' => ($tenant?->hasFeature('whatsapp') ?? false)
                ? \App\Services\WhatsAppLink::forPatient($this->record, 'chart_summary')
                : null,
        ];
    }
}
