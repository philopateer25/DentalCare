<?php

namespace App\Filament\Widgets;

use App\Models\Appointment;
use Carbon\Carbon;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class DoctorScheduleWidget extends BaseWidget
{
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 'full';
    protected static ?string $heading = 'My Schedule Today';

    public static function canView(): bool
    {
        return auth()->user()->hasRole('doctor') || auth()->user()->isDoctor();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Appointment::query()
                    ->whereDate('start_time', Carbon::today())
                    ->where('doctor_id', auth()->id())
                    ->orderBy('start_time', 'asc')
            )
            ->columns([
                Tables\Columns\TextColumn::make('start_time')
                    ->label('Time')
                    ->time('h:i A')
                    ->sortable(),
                Tables\Columns\TextColumn::make('patient.full_name')
                    ->label('Patient')
                    ->searchable()
                    ->url(fn (Appointment $record): string => route('filament.admin.resources.patients.view', ['record' => $record->patient_id])),
                Tables\Columns\TextColumn::make('operatory.name')
                    ->label('Room'),
                Tables\Columns\TextColumn::make('chief_complaint')
                    ->label('Reason')
                    ->limit(40),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'booked' => 'primary',
                        'arrived' => 'warning',
                        'in_chair' => 'danger',
                        'completed' => 'success',
                        default => 'secondary',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst(str_replace('_', ' ', $state))),
            ])
            ->actions([
                Tables\Actions\Action::make('start_treatment')
                    ->label('Start Treatment')
                    ->icon('heroicon-o-play')
                    ->color('danger')
                    ->url(fn (Appointment $record): string => route('filament.admin.resources.patients.view', ['record' => $record->patient_id]) . '?activeRelationManager=0') // Navigates to Odontogram
                    ->openUrlInNewTab()
                    ->visible(fn (Appointment $record) => in_array($record->status, ['arrived', 'in_chair'])),
            ])
            ->paginated(false);
    }
}
