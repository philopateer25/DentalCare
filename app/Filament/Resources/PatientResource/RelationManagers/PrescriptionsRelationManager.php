<?php

namespace App\Filament\Resources\PatientResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PrescriptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'prescriptions';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('medication_name')
                    ->required()
                    ->maxLength(255)
                    ->datalist([
                        'Amoxicillin', 'Clindamycin', 'Ibuprofen', 'Paracetamol', 'Chlorhexidine',
                    ])
                    ->columnSpanFull(),
                
                Forms\Components\TextInput::make('dosage')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g., 500mg'),
                    
                Forms\Components\TextInput::make('frequency')
                    ->required()
                    ->maxLength(255)
                    ->datalist(['Every 8 hours', 'Every 12 hours', 'Once daily', 'Twice daily', 'As needed'])
                    ->placeholder('e.g., Every 8 hours'),
                    
                Forms\Components\TextInput::make('duration')
                    ->required()
                    ->maxLength(255)
                    ->datalist(['3 days', '5 days', '7 days', '14 days', 'Until finished'])
                    ->placeholder('e.g., 7 days'),
                    
                Forms\Components\DatePicker::make('date_prescribed')
                    ->default(now())
                    ->required(),
                    
                Forms\Components\Select::make('status')
                    ->options([
                        'active' => 'Active',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ])
                    ->default('active')
                    ->required(),
                    
                Forms\Components\Textarea::make('instructions')
                    ->maxLength(65535)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('medication_name')
            ->columns([
                Tables\Columns\TextColumn::make('medication_name')
                    ->weight('bold')
                    ->searchable(),
                Tables\Columns\TextColumn::make('dosage'),
                Tables\Columns\TextColumn::make('frequency'),
                Tables\Columns\TextColumn::make('duration'),
                Tables\Columns\TextColumn::make('date_prescribed')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'completed' => 'gray',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('doctor.name')
                    ->label('Prescribed By')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['doctor_id'] = auth()->id();
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('print')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(fn (\App\Models\Prescription $record): string => route('prescriptions.print', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('whatsapp')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->color('success')
                    ->label('WhatsApp')
                    ->action(function (\App\Models\Prescription $record) {
                        $patientName = $record->patient->full_name;
                        $doctorName = $record->doctor->name ?? 'Doctor';
                        $clinicName = \App\Models\Practice::find(1)->name ?? 'Dental Clinic';
                        
                        $message = "🏥 *{$clinicName} - Prescription*\n";
                        $message .= "👨‍⚕️ Dr. {$doctorName}\n\n";
                        $message .= "💊 *Medication:* {$record->medication_name} {$record->dosage}\n";
                        $message .= "⏱ *Frequency:* {$record->frequency}\n";
                        $message .= "📅 *Duration:* {$record->duration}\n";
                        if ($record->instructions) {
                            $message .= "📝 *Instructions:* {$record->instructions}\n";
                        }
                        $message .= "\nGet well soon!";
                        
                        $phone = $record->patient->phone ?? '';
                        if (empty($phone)) {
                            \Filament\Notifications\Notification::make()
                                ->title('Patient has no phone number.')
                                ->danger()
                                ->send();
                            return;
                        }

                        $success = \App\Services\WhatsAppService::sendMessage($phone, $message);

                        if ($success) {
                            \Filament\Notifications\Notification::make()
                                ->title('Prescription sent via WhatsApp!')
                                ->success()
                                ->send();
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->title('Failed to send WhatsApp message.')
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
