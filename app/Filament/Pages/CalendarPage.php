<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Pages\Page;

class CalendarPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationGroup = 'Clinical Management';
    protected static ?string $navigationLabel = 'Calendar Grid';
    protected static ?string $title = 'Appointments Calendar';
    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.calendar-page';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openFull')
                ->label('Open in Full Window')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('primary')
                ->url('/calendar')
                ->openUrlInNewTab(),
        ];
    }
}
