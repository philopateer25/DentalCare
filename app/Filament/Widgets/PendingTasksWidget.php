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
    protected static ?string $heading = 'Pending Follow-ups (Tomorrow)';

    public static function canView(): bool
    {
        return auth()->user()->hasAnyRole(['secretary', 'clinic_admin', 'super_admin']);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Appointment::query()
                    ->where('practice_id', \Filament\Facades\Filament::getTenant()?->id)
                    ->where('type', 'follow_up')
                    ->whereDate('start_time', Carbon::tomorrow())
                    ->where('status', 'scheduled')
            )
            ->columns([
                Tables\Columns\TextColumn::make('patient.full_name')
                    ->label('Patient To Call'),
                Tables\Columns\TextColumn::make('patient.phone')
                    ->label('Phone Number')
                    ->copyable()
                    ->icon('heroicon-m-phone'),
                Tables\Columns\TextColumn::make('start_time')
                    ->label('Scheduled Time')
                    ->time('h:i A'),
            ])
            ->actions([
                Tables\Actions\Action::make('confirm')
                    ->label('Confirm')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->action(function (Appointment $record) {
                        $record->update(['status' => 'booked']);
                    }),
            ])
            ->paginated(false);
    }
}
