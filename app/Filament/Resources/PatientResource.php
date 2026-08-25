<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PatientResource\Pages;
use App\Filament\Resources\PatientResource\RelationManagers;
use App\Models\Patient;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use App\Jobs\SendWhatsAppMessage;
use Filament\Notifications\Notification;

class PatientResource extends Resource
{
    protected static ?string $model = Patient::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Patient Management';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Demographics')
                    ->schema([
                        Forms\Components\TextInput::make('file_number')
                            ->label('File Number')
                            ->default(fn () => 'PAT-' . strtoupper(Str::random(6)))
                            ->readOnly()
                            ->required(),
                        Forms\Components\TextInput::make('full_name')
                            ->label('Full Name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('national_id')
                            ->label('National ID (14 Digits)')
                            ->numeric()
                            ->length(14)
                            ->nullable(),
                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('secondary_phone')
                            ->tel()
                            ->maxLength(255),
                        Forms\Components\DatePicker::make('dob')
                            ->label('Date of Birth')
                            ->maxDate(now()),
                        Forms\Components\Select::make('gender')
                            ->options([
                                'male' => 'Male',
                                'female' => 'Female',
                                'other' => 'Other',
                            ])
                            ->default('male')
                            ->required(),
                        Forms\Components\Select::make('blood_type')
                            ->options([
                                'A+' => 'A+',
                                'A-' => 'A-',
                                'B+' => 'B+',
                                'B-' => 'B-',
                                'AB+' => 'AB+',
                                'AB-' => 'AB-',
                                'O+' => 'O+',
                                'O-' => 'O-',
                            ]),
                        Forms\Components\Select::make('referral_source')
                            ->options([
                                'Social Media' => 'Social Media',
                                'Walk-in' => 'Walk-in',
                                'Patient Referral' => 'Patient Referral',
                                'Doctor Referral' => 'Doctor Referral',
                                'Other' => 'Other',
                            ]),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Medical Alerts & Warnings')
                    ->schema([
                        Forms\Components\CheckboxList::make('medical_alerts')
                            ->label('Medical Alerts')
                            ->options([
                                'penicillin_allergy' => 'Penicillin Allergy',
                                'latex_allergy' => 'Latex Allergy',
                                'bleeding_disorder' => 'Bleeding Disorder',
                                'cardiac_condition' => 'Cardiac Risk',
                                'hypertension' => 'Hypertension',
                                'diabetic' => 'Diabetic',
                                'hepatitis' => 'Hepatitis',
                                'pregnant' => 'Pregnant',
                            ])
                            ->columns(4),
                        Forms\Components\Textarea::make('medical_notes')
                            ->label('Medical Notes')
                            ->rows(3),
                    ]),

                Forms\Components\Section::make('Emergency & Address')
                    ->collapsed()
                    ->schema([
                        Forms\Components\TextInput::make('emergency_contact_name')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('emergency_contact_phone')
                            ->tel()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('address')
                            ->rows(2),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('file_number')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('full_name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable(),
                Tables\Columns\TagsColumn::make('medical_alerts')
                    ->label('Medical Alerts')
                    ->color(fn (string $state): string => match ($state) {
                        'penicillin_allergy', 'bleeding_disorder', 'hepatitis' => 'danger',
                        'latex_allergy', 'cardiac_condition', 'hypertension', 'diabetic', 'pregnant' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('sendInstructions')
                    ->label('Send Instructions')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->form([
                        Forms\Components\Select::make('template')
                            ->label('Instruction Template')
                            ->options([
                                'post_extraction' => 'Post-Extraction Care',
                                'post_implant' => 'Post-Implant Care',
                                'general_hygiene' => 'General Oral Hygiene',
                            ])
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function (callable $set, $state) {
                                $texts = [
                                    'post_extraction' => "Please bite on the gauze for 30 minutes. Do not rinse, spit, or drink through a straw for 24 hours.",
                                    'post_implant' => "Take prescribed medications as directed. Avoid hard foods on the implant side for 2 weeks.",
                                    'general_hygiene' => "Brush twice a day for 2 minutes and floss daily.",
                                ];
                                $set('message', $texts[$state] ?? '');
                            }),
                        Forms\Components\Textarea::make('message')
                            ->label('Message Content')
                            ->required()
                            ->rows(4),
                    ])
                    ->action(function (Patient $record, array $data) {
                        SendWhatsAppMessage::dispatch($record->phone, $data['message']);
                        Notification::make()->title('Message queued for delivery!')->success()->send();
                    }),
                Tables\Actions\Action::make('sendCheckUp')
                    ->label('Send Check-up')
                    ->icon('heroicon-o-heart')
                    ->color('success')
                    ->form([
                        Forms\Components\Textarea::make('message')
                            ->label('Check-up Message')
                            ->default("Hello! This is a quick check-up from the clinic. How are you feeling today following your recent visit? Please let us know if you have any concerns.")
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (Patient $record, array $data) {
                        SendWhatsAppMessage::dispatch($record->phone, $data['message']);
                        Notification::make()->title('Check-up message queued!')->success()->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Patient Financial Summary')
                    ->schema([
                        Infolists\Components\TextEntry::make('total_invoiced')
                            ->label('Total Invoiced (EGP)')
                            ->state(fn (Patient $record): string => number_format((float) $record->invoices()->sum('total_amount'), 2) . ' EGP')
                            ->badge()
                            ->color('info'),
                        Infolists\Components\TextEntry::make('total_paid')
                            ->label('Total Paid (EGP)')
                            ->state(fn (Patient $record): string => number_format((float) $record->payments()->sum('amount'), 2) . ' EGP')
                            ->badge()
                            ->color('success'),
                        Infolists\Components\TextEntry::make('outstanding_debt')
                            ->label('Outstanding Debt (EGP)')
                            ->state(fn (Patient $record): string => number_format((float) $record->invoices()->sum('remaining_balance'), 2) . ' EGP')
                            ->badge()
                            ->color(fn (Patient $record): string => $record->invoices()->sum('remaining_balance') > 0 ? 'danger' : 'gray'),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Demographic Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('file_number'),
                        Infolists\Components\TextEntry::make('full_name'),
                        Infolists\Components\TextEntry::make('national_id'),
                        Infolists\Components\TextEntry::make('phone'),
                        Infolists\Components\TextEntry::make('secondary_phone'),
                        Infolists\Components\TextEntry::make('dob')->date(),
                        Infolists\Components\TextEntry::make('gender'),
                        Infolists\Components\TextEntry::make('blood_type'),
                        Infolists\Components\TextEntry::make('referral_source'),
                    ])
                    ->columns(3),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\InvoicesRelationManager::class,
            RelationManagers\PaymentsRelationManager::class,
            RelationManagers\InstallmentPlansRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPatients::route('/'),
            'create' => Pages\CreatePatient::route('/create'),
            'view' => Pages\ViewPatient::route('/{record}'),
            'edit' => Pages\EditPatient::route('/{record}/edit'),
        ];
    }
}
