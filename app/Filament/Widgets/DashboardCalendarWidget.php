<?php

namespace App\Filament\Widgets;

use App\Models\Appointment;
use App\Models\Operatory;
use App\Models\Patient;
use App\Models\User;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class DashboardCalendarWidget extends BaseWidget
{
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['clinic_admin', 'super_admin', 'developer', 'secretary', 'doctor']) ?? false;
    }

    public function getTableHeading(): string
    {
        $user = auth()->user();

        if ($user && $user->hasRole('doctor') && !$user->hasAnyRole(['clinic_admin', 'super_admin', 'developer', 'secretary'])) {
            return 'My Appointments Calendar';
        }

        if ($user && $user->hasRole('secretary') && !$user->hasAnyRole(['clinic_admin', 'super_admin', 'developer'])) {
            return 'Reception & Clinic Calendar';
        }

        return 'Clinic Appointments Calendar';
    }

    public function table(Table $table): Table
    {
        $practiceId = \Filament\Facades\Filament::getTenant()?->id ?? auth()->user()?->practice_id;
        $user = auth()->user();
        $isDoctorOnly = $user && $user->hasRole('doctor') && !$user->hasAnyRole(['clinic_admin', 'super_admin', 'developer', 'secretary']);

        return $table
            ->query(
                Appointment::query()
                    ->where('practice_id', $practiceId)
                    ->when($isDoctorOnly, fn (Builder $q) => $q->where('doctor_id', $user->id))
                    ->with(['patient', 'doctor', 'operatory'])
                    ->orderBy('start_time', 'asc')
            )
            ->columns([
                Tables\Columns\TextColumn::make('start_time')
                    ->label('Date & Time')
                    ->formatStateUsing(function (Appointment $record) {
                        $start = $record->start_time;
                        $end = $record->end_time;
                        $dateStr = $start->isToday() ? 'Today' : ($start->isTomorrow() ? 'Tomorrow' : $start->format('d M'));
                        $timeStr = $start->format('h:i A') . ($end ? ' - ' . $end->format('h:i A') : '');
                        return "{$dateStr}, {$timeStr}";
                    })
                    ->badge()
                    ->color(fn (Appointment $record) => $record->start_time->isToday() ? 'info' : 'gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('patient.full_name')
                    ->label('Patient')
                    ->description(fn (Appointment $record) => $record->patient?->file_number ? "File: {$record->patient->file_number}" : ($record->patient?->phone ?? ''))
                    ->searchable(['first_name', 'last_name', 'file_number'])
                    ->url(fn (Appointment $record): string => \App\Filament\Resources\PatientResource::getUrl('view', ['record' => $record->patient_id]))
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('doctor.name')
                    ->label('Doctor')
                    ->searchable()
                    ->badge()
                    ->color('primary')
                    ->visible(!$isDoctorOnly),

                Tables\Columns\TextColumn::make('operatory.name')
                    ->label('Chair / Room')
                    ->badge()
                    ->color('gray')
                    ->placeholder('Unassigned'),

                Tables\Columns\TextColumn::make('chief_complaint')
                    ->label('Procedure / Reason')
                    ->limit(35)
                    ->placeholder('General Consultation'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'booked' => 'primary',
                        'arrived' => 'warning',
                        'in_chair' => 'danger',
                        'completed' => 'success',
                        'cancelled' => 'gray',
                        'no_show' => 'gray',
                        default => 'secondary',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'booked' => 'Booked',
                        'arrived' => 'Arrived (Waiting)',
                        'in_chair' => 'In Chair (Active)',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                        'no_show' => 'No Show',
                        default => ucfirst(str_replace('_', ' ', $state)),
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('time_frame')
                    ->label('Calendar Period')
                    ->options([
                        'today' => 'Today (' . Carbon::today()->format('d M') . ')',
                        'tomorrow' => 'Tomorrow (' . Carbon::tomorrow()->format('d M') . ')',
                        'this_week' => 'This Week',
                        'upcoming' => 'All Upcoming',
                        'past' => 'Past Appointments',
                        'all' => 'All Records',
                    ])
                    ->default('today')
                    ->query(function (Builder $query, array $data) {
                        $value = $data['value'] ?? 'today';
                        return match ($value) {
                            'today' => $query->whereDate('start_time', Carbon::today()),
                            'tomorrow' => $query->whereDate('start_time', Carbon::tomorrow()),
                            'this_week' => $query->whereBetween('start_time', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]),
                            'upcoming' => $query->where('start_time', '>=', Carbon::now()),
                            'past' => $query->where('start_time', '<', Carbon::now()),
                            'all' => $query,
                            default => $query->whereDate('start_time', Carbon::today()),
                        };
                    }),

                Tables\Filters\SelectFilter::make('doctor_id')
                    ->label('Doctor')
                    ->options(fn () => User::where('practice_id', $practiceId)
                        ->whereHas('roles', fn ($rq) => $rq->where('name', 'doctor'))
                        ->pluck('name', 'id')
                        ->toArray()
                    )
                    ->searchable()
                    ->preload()
                    ->visible(!$isDoctorOnly),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'booked' => 'Booked',
                        'arrived' => 'Arrived (Waiting)',
                        'in_chair' => 'In Chair (Active)',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                        'no_show' => 'No Show',
                    ]),
            ])
            ->actions([
                // Quick transition: Booked -> Arrived
                Tables\Actions\Action::make('mark_arrived')
                    ->label('Patient Arrived')
                    ->icon('heroicon-o-check-badge')
                    ->color('warning')
                    ->visible(fn (Appointment $record) => $record->status === 'booked')
                    ->action(function (Appointment $record) {
                        $record->update(['status' => 'arrived']);
                        Notification::make()->title('Patient marked as arrived in waiting room')->success()->send();
                    }),

                // Quick transition: Arrived -> In Chair
                Tables\Actions\Action::make('seat_in_chair')
                    ->label('Seat In Chair')
                    ->icon('heroicon-o-play')
                    ->color('danger')
                    ->visible(fn (Appointment $record) => $record->status === 'arrived')
                    ->action(function (Appointment $record) {
                        $record->update(['status' => 'in_chair']);
                        Notification::make()->title('Patient seated in chair for treatment')->success()->send();
                    }),

                // Quick transition: In Chair -> Completed
                Tables\Actions\Action::make('mark_completed')
                    ->label('Complete')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Appointment $record) => $record->status === 'in_chair')
                    ->action(function (Appointment $record) {
                        $record->update(['status' => 'completed']);
                        Notification::make()->title('Appointment marked as completed')->success()->send();
                    }),

                Tables\Actions\EditAction::make()
                    ->url(fn (Appointment $record): string => \App\Filament\Resources\AppointmentResource::getUrl('edit', ['record' => $record])),
            ])
            ->headerActions([
                Tables\Actions\Action::make('quick_book')
                    ->label('Book Appointment')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->form([
                        Forms\Components\Select::make('patient_id')
                            ->label('Patient')
                            ->options(fn () => Patient::where('practice_id', $practiceId)
                                ->get()
                                ->mapWithKeys(fn ($p) => [$p->id => "{$p->first_name} {$p->last_name} ({$p->file_number})"])
                                ->toArray()
                            )
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\Select::make('doctor_id')
                            ->label('Doctor')
                            ->options(fn () => User::where('practice_id', $practiceId)
                                ->whereHas('roles', fn ($rq) => $rq->where('name', 'doctor'))
                                ->pluck('name', 'id')
                                ->toArray()
                            )
                            ->default(fn () => $isDoctorOnly ? $user->id : null)
                            ->disabled($isDoctorOnly)
                            ->dehydrated()
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\Select::make('operatory_id')
                            ->label('Room / Operatory Chair')
                            ->options(fn () => Operatory::whereHas('branch', fn ($bq) => $bq->where('practice_id', $practiceId))
                                ->pluck('name', 'id')
                                ->toArray()
                            )
                            ->preload()
                            ->required(),

                        Forms\Components\DateTimePicker::make('start_time')
                            ->label('Start Date & Time')
                            ->default(now()->setMinute(0)->setSecond(0))
                            ->minutesStep(15)
                            ->required(),

                        Forms\Components\Select::make('duration_minutes')
                            ->label('Visit Duration')
                            ->options([
                                '15' => '15 minutes',
                                '30' => '30 minutes (Standard)',
                                '45' => '45 minutes',
                                '60' => '1 hour',
                                '90' => '1.5 hours',
                            ])
                            ->default('30')
                            ->required(),

                        Forms\Components\TextInput::make('chief_complaint')
                            ->label('Reason / Chief Complaint')
                            ->placeholder('e.g. Toothache, Scaling, Routine Checkup')
                            ->maxLength(255),
                    ])
                    ->action(function (array $data) use ($practiceId, $user, $isDoctorOnly): void {
                        $startTime = Carbon::parse($data['start_time']);
                        $duration = (int) ($data['duration_minutes'] ?? 30);
                        $endTime = (clone $startTime)->addMinutes($duration);

                        $operatory = Operatory::find($data['operatory_id']);
                        $branchId = $operatory?->branch_id ?? \App\Models\Branch::where('practice_id', $practiceId)->value('id');

                        Appointment::create([
                            'practice_id' => $practiceId,
                            'branch_id' => $branchId,
                            'patient_id' => $data['patient_id'],
                            'doctor_id' => $isDoctorOnly ? $user->id : $data['doctor_id'],
                            'operatory_id' => $data['operatory_id'],
                            'start_time' => $startTime,
                            'end_time' => $endTime,
                            'chief_complaint' => $data['chief_complaint'] ?? null,
                            'status' => 'booked',
                        ]);

                        Notification::make()
                            ->title('Appointment Booked Successfully')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('view_all_appointments')
                    ->label('All Appointments')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(\App\Filament\Resources\AppointmentResource::getUrl('index')),
            ])
            ->emptyStateHeading('No Appointments Found')
            ->emptyStateDescription('There are no appointments scheduled for this period. Use the period filter above or click "Book Appointment" to add one.')
            ->emptyStateIcon('heroicon-o-calendar-days')
            ->poll('30s')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10);
    }
}
