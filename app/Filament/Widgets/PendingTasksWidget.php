<?php

namespace App\Filament\Widgets;

use App\Models\Appointment;
use Carbon\Carbon;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class PendingTasksWidget extends BaseWidget
{
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 1;
    protected static ?string $heading = "Tomorrow's Patient Confirmation Calls";

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['secretary', 'clinic_admin', 'super_admin', 'developer']) ?? false;
    }

    public function table(Table $table): Table
    {
        $practiceId = \Filament\Facades\Filament::getTenant()?->id ?? auth()->user()?->practice_id;

        return $table
            ->query(
                Appointment::query()
                    ->where('practice_id', $practiceId)
                    ->whereDate('start_time', Carbon::tomorrow())
                    ->whereIn('status', ['scheduled', 'booked'])
                    ->orderBy('start_time', 'asc')
            )
            ->columns([
                Tables\Columns\TextColumn::make('patient.full_name')
                    ->label('Patient To Call')
                    ->searchable(),
                Tables\Columns\TextColumn::make('patient.phone')
                    ->label('Phone Number')
                    ->copyable()
                    ->icon('heroicon-m-phone'),
                Tables\Columns\TextColumn::make('start_time')
                    ->label('Scheduled Time')
                    ->time('h:i A'),
                Tables\Columns\TextColumn::make('chief_complaint')
                    ->label('Reason')
                    ->limit(25)
                    ->placeholder('General Visit'),
            ])
            ->actions([
                Tables\Actions\Action::make('confirm')
                    ->label('Confirmed')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->action(function (Appointment $record) {
                        $record->update(['status' => 'booked']);
                        \Filament\Notifications\Notification::make()
                            ->title('Appointment Confirmed')
                            ->success()
                            ->send();
                    }),
            ])
            ->recordUrl(
                fn (Appointment $record): string => \App\Filament\Resources\AppointmentResource::getUrl('edit', ['record' => $record])
            )
            ->headerActions([
                Tables\Actions\Action::make('view_all')
                    ->label('All Appointments')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url(\App\Filament\Resources\AppointmentResource::getUrl('index')),
            ])
            ->paginated(false);
    }
}
