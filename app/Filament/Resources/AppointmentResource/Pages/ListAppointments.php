<?php

namespace App\Filament\Resources\AppointmentResource\Pages;

use App\Filament\Resources\AppointmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAppointments extends ListRecords
{
    protected static string $resource = AppointmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('open_calendar')
                ->label('Open Interactive Calendar')
                ->icon('heroicon-o-calendar-days')
                ->color('info')
                ->url('/calendar'),
            Actions\CreateAction::make(),
        ];
    }
}
