<?php

namespace App\Filament\Widgets;

use App\Models\WhatsAppAppointmentRequest;
use App\Models\Appointment;
use App\Models\Operatory;
use App\Models\Branch;
use App\Models\StaffMember;
use App\Jobs\SendWhatsAppMessageJob;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class WhatsAppPendingRequestsWidget extends BaseWidget
{
    protected static ?int $sort = 1;
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                WhatsAppAppointmentRequest::query()
                    ->where('practice_id', filament()->getTenant()->id)
                    ->where('status', 'pending')
                    ->latest()
            )
            ->columns([
                Tables\Columns\TextColumn::make('patient.full_name')
                    ->label('Patient')
                    ->description(fn (WhatsAppAppointmentRequest $record): string => $record->patient->phone ?? '')
                    ->searchable(),
                Tables\Columns\TextColumn::make('requested_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('requested_time')
                    ->time('h:i A')
                    ->sortable(),
                Tables\Columns\TextColumn::make('notes')
                    ->wrap()
                    ->limit(50),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Requested At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Action::make('approve')
                    ->label('Approve')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->requiresConfirmation()
                    ->action(function (WhatsAppAppointmentRequest $record) {
                        try {
                            $practiceId = $record->practice_id;
                            $branchId = Branch::where('practice_id', $practiceId)->first()->id ?? 1;
                            $operatoryId = Operatory::where('branch_id', $branchId)->first()->id ?? 1;
                            $doctorId = StaffMember::where('practice_id', $practiceId)->where('role', 'doctor')->first()->id ?? 1;

                            $startTime = Carbon::parse($record->requested_date . ' ' . $record->requested_time);
                            $endTime = $startTime->copy()->addMinutes(30);

                            Appointment::create([
                                'practice_id' => $practiceId,
                                'branch_id' => $branchId,
                                'operatory_id' => $operatoryId,
                                'doctor_id' => $doctorId,
                                'patient_id' => $record->patient_id,
                                'status' => 'booked',
                                'start_time' => $startTime,
                                'end_time' => $endTime,
                                'chief_complaint' => "PATIENT REQUESTED via WhatsApp. Notes: " . $record->notes,
                                'procedure_name' => 'General Checkup',
                            ]);

                            $record->update(['status' => 'approved']);

                            $formattedTime = $startTime->format('h:i A');
                            $formattedDate = $startTime->format('Y-m-d');
                            $msg = "Great news! Your appointment for $formattedDate at $formattedTime has been approved. See you then!";
                            SendWhatsAppMessageJob::dispatch('clinic_' . $practiceId, $record->patient->phone, $msg);

                        } catch (\Exception $e) {
                            Log::error('Approval failed', ['error' => $e->getMessage()]);
                            \Filament\Notifications\Notification::make()
                                ->title('Error Approving')
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('propose_time')
                    ->label('Reschedule')
                    ->color('warning')
                    ->icon('heroicon-o-clock')
                    ->form([
                        DateTimePicker::make('new_time')
                            ->label('New Proposed Date & Time')
                            ->required()
                            ->default(fn (WhatsAppAppointmentRequest $record) => Carbon::parse($record->requested_date . ' ' . $record->requested_time)),
                        Textarea::make('message')
                            ->label('Message to Patient')
                            ->default("Hello, we are fully booked at your requested time. Would you be available at [New Time] instead? Reply with Yes or propose another time.")
                            ->required(),
                    ])
                    ->action(function (WhatsAppAppointmentRequest $record, array $data) {
                        $newTime = Carbon::parse($data['new_time']);
                        $msg = str_replace('[New Time]', $newTime->format('Y-m-d h:i A'), $data['message']);
                        
                        $record->update([
                            'requested_date' => $newTime->format('Y-m-d'),
                            'requested_time' => $newTime->format('H:i:s'),
                        ]);

                        $record->patient->update([
                            'bot_state' => 'rescheduling',
                            'bot_step' => (string) $record->id,
                        ]);

                        SendWhatsAppMessageJob::dispatch('clinic_' . $record->practice_id, $record->patient->phone, $msg);
                    }),

                Action::make('deny')
                    ->label('Deny')
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->requiresConfirmation()
                    ->action(function (WhatsAppAppointmentRequest $record) {
                        $record->update(['status' => 'denied']);
                        $msg = "We're sorry, but we cannot accommodate your appointment request at this time. Please contact us for other options.";
                        SendWhatsAppMessageJob::dispatch('clinic_' . $record->practice_id, $record->patient->phone, $msg);
                    }),
            ])
            ->emptyStateHeading('No pending requests')
            ->emptyStateDescription('All caught up!');
    }
}
