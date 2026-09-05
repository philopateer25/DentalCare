<?php

namespace App\Filament\Resources\OperationResource\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Tables;
use Carbon\Carbon;
use App\Models\Appointment;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Builder;

class OperationTable
{
    public static function columns(): array
    {
        return [
            TextColumn::make('start_time')
                ->label('Date & Time')
                ->dateTime()
                ->sortable(),
            TextColumn::make('patient.full_name')
                ->label('Patient')
                ->searchable()
                ->url(fn (Appointment $record): string => route('filament.admin.resources.patients.view', ['record' => $record->patient_id])),
            TextColumn::make('doctor.name')
                ->label('Doctor')
                ->searchable()
                ->sortable(),
            TextColumn::make('operatory.name')
                ->label('Operatory')
                ->sortable(),
            TextColumn::make('procedure_name')
                ->label('Procedure')
                ->searchable(),
            TextColumn::make('tooth_number')
                ->label('Tooth/Target')
                ->searchable(),
            IconColumn::make('followUps')
                ->label('Follow-up Booked')
                ->boolean()
                ->getStateUsing(fn (Appointment $record): bool => $record->followUps()->exists()),
            TextColumn::make('status')
                ->badge()
                ->color('success')
                ->formatStateUsing(fn (string $state): string => ucfirst($state)),
        ];
    }

    public static function filters(): array
    {
        return [
            SelectFilter::make('doctor_id')
                ->relationship('doctor', 'name')
                ->label('Doctor'),
            Filter::make('start_time')
                ->form([
                    DatePicker::make('created_from'),
                    DatePicker::make('created_until'),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when(
                            $data['created_from'],
                            fn (Builder $query, $date): Builder => $query->whereDate('start_time', '>=', $date),
                        )
                        ->when(
                            $data['created_until'],
                            fn (Builder $query, $date): Builder => $query->whereDate('start_time', '<=', $date),
                        );
                }),
        ];
    }

    public static function actions(): array
    {
        return [
            Tables\Actions\Action::make('book_follow_up')
                ->label('Book Follow-up')
                ->icon('heroicon-o-calendar-days')
                ->color('primary')
                ->form([
                    DateTimePicker::make('start_time')
                        ->label('Follow-up Date & Time')
                        ->required(),
                    Select::make('doctor_id')
                        ->label('Doctor')
                        ->relationship('doctor', 'name')
                        ->default(fn (Appointment $record) => $record->doctor_id)
                        ->required(),
                    Select::make('operatory_id')
                        ->label('Operatory/Room')
                        ->relationship('operatory', 'name')
                        ->default(fn (Appointment $record) => $record->operatory_id)
                        ->required(),
                    Textarea::make('notes')
                        ->label('Follow-up Notes'),
                ])
                ->action(function (array $data, Appointment $record) {
                    $startTime = Carbon::parse($data['start_time']);
                    $endTime = $startTime->copy()->addMinutes(30);

                    // Check for conflicts
                    $conflict = Appointment::where('doctor_id', $data['doctor_id'])
                        ->where('status', '!=', 'cancelled')
                        ->where(function($query) use ($startTime, $endTime) {
                            $query->where(function($q) use ($startTime, $endTime) {
                                $q->where('start_time', '<', $endTime)
                                  ->where('end_time', '>', $startTime);
                            });
                        })
                        ->exists();

                    if ($conflict) {
                        Notification::make()
                            ->danger()
                            ->title('Conflict Detected')
                            ->body('The selected doctor is already booked during this 30-minute time slot.')
                            ->send();

                        throw new Halt();
                    }

                    // Create the follow up appointment
                    Appointment::create([
                        'practice_id' => $record->practice_id,
                        'branch_id' => $record->branch_id,
                        'operatory_id' => $data['operatory_id'],
                        'patient_id' => $record->patient_id,
                        'doctor_id' => $data['doctor_id'],
                        'treatment_procedure_id' => $record->treatment_procedure_id,
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'type' => 'follow_up',
                        'parent_appointment_id' => $record->id,
                        'status' => 'scheduled',
                        'notes' => $data['notes'] ?? 'Follow-up appointment.',
                    ]);

                    Notification::make()
                        ->success()
                        ->title('Follow-up Booked successfully!')
                        ->send();
                }),
            Tables\Actions\Action::make('send_whatsapp_checkup')
                ->label('WhatsApp Check-up')
                ->icon('heroicon-o-chat-bubble-left-ellipsis')
                ->color('success')
                ->action(function (Appointment $record) {
                    \App\Jobs\SendPostOpCheckupJob::dispatch($record);
                    
                    Notification::make()
                        ->success()
                        ->title('WhatsApp check-up queued for dispatch!')
                        ->send();
                }),
            Tables\Actions\ViewAction::make(),
        ];
    }
}
