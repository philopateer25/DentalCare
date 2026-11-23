<?php

namespace App\Filament\Resources\OperatoryResource\Pages;

use App\Filament\Resources\OperatoryResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateOperatory extends CreateRecord
{
    protected static string $resource = OperatoryResource::class;

    public function getSubheading(): ?string
    {
        return OperatoryResource::usageText();
    }

    /** Stop before saving, with a clear message, when the clinic has no free chair. */
    protected function beforeCreate(): void
    {
        $practice = OperatoryResource::practice();
        $limit = $practice?->effectiveChairLimit();

        if (($this->data['is_active'] ?? true) && $limit !== null && $practice->activeChairCount() >= $limit) {
            Notification::make()
                ->title(__('Chair limit reached (:limit)', ['limit' => $limit]))
                ->body(__('Switch another room off first, or save this room as not in use. To get more chairs, ask DOXamigo.'))
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
