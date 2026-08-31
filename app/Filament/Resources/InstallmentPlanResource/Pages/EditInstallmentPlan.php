<?php

namespace App\Filament\Resources\InstallmentPlanResource\Pages;

use App\Filament\Resources\InstallmentPlanResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInstallmentPlan extends EditRecord
{
    protected static string $resource = InstallmentPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('regenerateSchedules')
                ->label('Regenerate Schedule Matrix')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->action(fn () => $this->record->generateSchedules()),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
