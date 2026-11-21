<?php

namespace App\Filament\Resources\PatientResource\Pages;

use App\Filament\Resources\PatientResource;
use Filament\Resources\Pages\ViewRecord;

class ViewPatient extends ViewRecord
{
    protected static string $resource = PatientResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            // Stitch 04-patient-profile header + overview. It includes the medical alerts,
            // so the older PatientAlertWidget banner is no longer listed here (class kept).
            PatientResource\Widgets\PatientProfileHeaderWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    public function getHeading(): string
    {
        return '';
    }
}
