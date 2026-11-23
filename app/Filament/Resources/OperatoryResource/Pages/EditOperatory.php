<?php

namespace App\Filament\Resources\OperatoryResource\Pages;

use App\Filament\Resources\OperatoryResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditOperatory extends EditRecord
{
    protected static string $resource = OperatoryResource::class;

    /** Switching a room back on needs a free chair. */
    protected function beforeSave(): void
    {
        $practice = OperatoryResource::practice();
        $limit = $practice?->effectiveChairLimit();
        $switchingOn = ($this->data['is_active'] ?? false) && ! $this->record->is_active;

        if ($switchingOn && $limit !== null && $practice->activeChairCount() >= $limit) {
            Notification::make()
                ->title(__('Chair limit reached (:limit)', ['limit' => $limit]))
                ->body(__('Switch another room off first. To get more chairs, ask DOXamigo.'))
                ->danger()
                ->persistent()
                ->send();
            $this->halt();
        }
    }

    protected function getRedirectUrl(): string
    {
        return OperatoryResource::getUrl('index');
    }
}
