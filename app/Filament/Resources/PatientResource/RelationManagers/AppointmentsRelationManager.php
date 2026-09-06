<?php

namespace App\Filament\Resources\PatientResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AppointmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'appointments';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Appointment Details')
                    ->schema([
                        Forms\Components\Select::make('type')
                            ->options([
                                'new_visit' => 'New Visit',
                                'follow_up' => 'Follow Up',
                            ])
                            ->default('new_visit')
                            ->required()
                            ->live(),

                        Forms\Components\Select::make('parent_appointment_id')
                            ->relationship('parentAppointment', 'start_time', fn (Builder $query, \Filament\Resources\RelationManagers\RelationManager $livewire) => $query->where('patient_id', $livewire->ownerRecord->id))
                            ->getOptionLabelFromRecordUsing(fn (\App\Models\Appointment $record) => $record->start_time->format('d M Y') . ' - ' . $record->chief_complaint)
                            ->label('Link to Previous Appointment')
                            ->visible(fn (Forms\Get $get) => $get('type') === 'follow_up')
                            ->required(fn (Forms\Get $get) => $get('type') === 'follow_up'),
                            
                        Forms\Components\Select::make('practice_id')
                            ->relationship('practice', 'name')
                            ->default(fn () => \App\Models\Practice::first()?->id)
                            ->required()
                            ->hidden(),
                            
                        // Forms\Components\Select::make('branch_id')
                        //     ->relationship('branch', 'name')
                        //     ->default(fn () => \App\Models\Branch::first()?->id)
                        //     ->required()
                        //     ->label('Branch'),

                        Forms\Components\Select::make('operatory_id')
                            ->relationship('operatory', 'name')
                            ->required()
                            ->label('Room (Operatory)'),

                        Forms\Components\Select::make('doctor_id')
                            ->relationship('doctor', 'name')
                            ->required()
                            ->label('Doctor'),
                            
                        Forms\Components\DateTimePicker::make('start_time')
                            ->required()
                            ->seconds(false)
                            ->minutesStep(15),
                            
                        Forms\Components\DateTimePicker::make('end_time')
                            ->required()
                            ->seconds(false)
                            ->minutesStep(15)
                            ->after('start_time'),

                        Forms\Components\Select::make('status')
                            ->options([
                                'booked' => 'Booked',
                                'arrived' => 'Arrived',
                                'in_chair' => 'In Chair',
                                'completed' => 'Completed',
                                'no_show' => 'No Show',
                                'cancelled' => 'Cancelled',
                            ])
                            ->default('booked')
                            ->required(),

                        Forms\Components\TextInput::make('chief_complaint')
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('notes')
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('start_time')
            ->columns([
                Tables\Columns\TextColumn::make('start_time')
                    ->dateTime('d M Y, h:i A')
                    ->sortable()
                    ->label('Date & Time'),
                Tables\Columns\TextColumn::make('doctor.name')
                    ->label('Doctor')
                    ->searchable(),
                Tables\Columns\TextColumn::make('operatory.name')
                    ->label('Room')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'booked' => 'info',
                        'arrived' => 'warning',
                        'in_chair' => 'primary',
                        'completed' => 'success',
                        'no_show' => 'danger',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'new_visit' => 'success',
                        'follow_up' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'new_visit' => 'New',
                        'follow_up' => 'Follow Up',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('chief_complaint')
                    ->limit(30)
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        if (!isset($data['practice_id'])) {
                            $data['practice_id'] = \App\Models\Practice::first()?->id;
                        }
                        if (!isset($data['branch_id'])) {
                            $data['branch_id'] = \App\Models\Branch::first()?->id;
                        }
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('whatsapp_reminder')
                    ->label('Send Reminder')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Send WhatsApp Reminder')
                    ->modalDescription('Are you sure you want to send a reminder to the patient via WhatsApp?')
                    ->action(function (\App\Models\Appointment $record) {
                        $phone = $record->patient->whatsapp_number ?? $record->patient->phone;
                        $time = $record->start_time->format('h:i A on d M Y');
                        $message = "Hello {$record->patient->first_name}, this is a friendly reminder for your dental appointment at {$time}. Reply to confirm or cancel.";
                        
                        if (\App\Services\WhatsAppService::sendMessage($phone, $message)) {
                            \Filament\Notifications\Notification::make()->title('Reminder Sent!')->success()->send();
                        } else {
                            \Filament\Notifications\Notification::make()->title('Failed to send WhatsApp')->danger()->send();
                        }
                    }),
                    
                Tables\Actions\Action::make('whatsapp_instructions')
                    ->label('Post-Op Instructions')
                    ->icon('heroicon-o-document-text')
                    ->color('warning')
                    ->form([
                        Forms\Components\Textarea::make('custom_message')
                            ->label('Message Content')
                            ->default("Hello, please remember to avoid eating hot foods for the next 2 hours. Rinse with warm salt water tomorrow.")
                            ->required()
                    ])
                    ->action(function (\App\Models\Appointment $record, array $data) {
                        $phone = $record->patient->whatsapp_number ?? $record->patient->phone;
                        
                        if (\App\Services\WhatsAppService::sendMessage($phone, $data['custom_message'])) {
                            \Filament\Notifications\Notification::make()->title('Instructions Sent!')->success()->send();
                        } else {
                            \Filament\Notifications\Notification::make()->title('Failed to send WhatsApp')->danger()->send();
                        }
                    }),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('start_time', 'desc');
    }
}
