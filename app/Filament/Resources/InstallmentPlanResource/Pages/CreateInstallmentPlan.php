<?php

namespace App\Filament\Resources\InstallmentPlanResource\Pages;

use App\Filament\Resources\InstallmentPlanResource;
use Filament\Resources\Pages\CreateRecord;

class CreateInstallmentPlan extends CreateRecord
{
    protected static string $resource = InstallmentPlanResource::class;

    protected function afterCreate(): void
    {
        $this->record->generateSchedules();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
