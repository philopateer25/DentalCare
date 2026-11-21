@php
    $patient = $getRecord();
    $tenant = \Filament\Facades\Filament::getTenant();
    $config = [
        'preview' => true,
        'patientId' => $patient->id,
        'dataUrl' => url("/api/patients/{$patient->id}/chart-workspace"),
        'chartUrl' => \App\Filament\Resources\PatientResource::getUrl('chart', ['record' => $patient]),
        'has3d' => (bool) ($tenant?->hasFeature('3d_model') ?? false),
        'modelUrl' => '/models/Dentition_Permanent_Full_V2.1.glb',
    ];
@endphp

{{--
    3D mouth chart tab: a read-only React preview mounted directly in the page (no iframe).
    It loads the 3D engine only once the tab is visible. Editing happens in Patients → Chart.
--}}
<div wire:ignore data-chart-workspace data-config='@json($config)' class="dxw-host">
    <div class="dxw-skeleton">{{ __('Loading dental chart…') }}</div>
</div>
