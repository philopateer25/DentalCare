<?php

namespace App\Filament\Resources\MaintenanceResource\Pages;

use App\Filament\Resources\MaintenanceResource;
use App\Models\MaintenanceRecord;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMaintenanceRecord extends EditRecord
{
    protected static string $resource = MaintenanceResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['status'] ?? null) === MaintenanceRecord::STATUS_COMPLETED && ! $this->record->completed_at) {
            $data['completed_at'] = now();
        }

        return $data;
    }

    protected function afterSave(): void
    {
        MaintenanceResource::notifyAffected($this->record->refresh());
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
