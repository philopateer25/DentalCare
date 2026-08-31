<?php

namespace App\Filament\Resources\PatientResource\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;

class PatientAlertWidget extends Widget
{
    protected static string $view = 'filament.resources.patient-resource.widgets.patient-alert-widget';

    public ?Model $record = null;
    
    protected int | string | array $columnSpan = 'full';
}
