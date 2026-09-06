<?php

namespace App\Filament\Resources\PatientResource\Forms;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use App\Models\Patient;

class PatientForm
{
    public static function schema(): array
    {
        return [
            Tabs::make('Patient Profile')
                ->tabs([
                    Tabs\Tab::make('General Info')
                        ->icon('heroicon-o-user')
                        ->schema([
                            Section::make('Demographics')
                                ->schema([
                                    Select::make('practice_id')
                                        ->relationship('practice', 'name')
                                        ->required(),
                                    TextInput::make('file_number')
                                        ->required()
                                        ->unique(ignoreRecord: true)
                                        ->maxLength(255),
                                    TextInput::make('national_id')
                                        ->unique(ignoreRecord: true)
                                        ->numeric()
                                        ->length(14)
                                        ->label('National ID'),
                                    TextInput::make('first_name')
                                        ->required()
                                        ->maxLength(255),
                                    TextInput::make('last_name')
                                        ->required()
                                        ->maxLength(255),
                                    Select::make('gender')
                                        ->options([
                                            'male' => 'Male',
                                            'female' => 'Female',
                                            'other' => 'Other',
                                        ])
                                        ->required(),
                                    DatePicker::make('dob')
                                        ->label('Date of Birth')
                                        ->maxDate(now()),
                                    TextInput::make('phone')
                                        ->required()
                                        ->tel()
                                        ->regex('/^01[0125][0-9]{8}$/')
                                        ->validationMessages([
                                            'regex' => 'The phone number must be a valid Egyptian mobile number.',
                                        ]),
                                    TextInput::make('whatsapp_number')
                                        ->tel(),
                                    TextInput::make('email')
                                        ->email()
                                        ->maxLength(255),
                                    TextInput::make('emergency_contact')
                                        ->tel()
                                        ->maxLength(255),
                                    Select::make('status')
                                        ->options([
                                            'active' => 'Active',
                                            'inactive' => 'Inactive',
                                            'archived' => 'Archived',
                                        ])
                                        ->default('active')
                                        ->required(),
                                    Textarea::make('address')
                                        ->columnSpanFull(),
                                ])->columns(2),
                        ]),

                    Tabs\Tab::make('Medical History & Alerts')
                        ->icon('heroicon-o-heart')
                        ->schema([
                            Group::make()
                                ->relationship('medicalHistory')
                                ->schema([
                                    Section::make('Systemic Conditions')
                                        ->schema([
                                            Select::make('blood_type')
                                                ->options([
                                                    'A+' => 'A+', 'A-' => 'A-', 'B+' => 'B+', 'B-' => 'B-',
                                                    'AB+' => 'AB+', 'AB-' => 'AB-', 'O+' => 'O+', 'O-' => 'O-',
                                                ]),
                                            Toggle::make('diabetic_status')->label('Diabetic'),
                                            Toggle::make('cardiac_history')->label('Cardiac History'),
                                            Toggle::make('hypertension_status')->label('Hypertension'),
                                            Toggle::make('bleeding_disorder')->label('Bleeding Disorder'),
                                        ])->columns(2),

                                    Section::make('Allergies')
                                        ->schema([
                                            Toggle::make('latex_allergy')->label('Latex Allergy'),
                                            Toggle::make('penicillin_allergy')->label('Penicillin Allergy'),
                                            Toggle::make('local_anesthetic_allergy')->label('Local Anesthetic Allergy'),
                                        ])->columns(3),

                                    Section::make('Additional Information')
                                        ->schema([
                                            TagsInput::make('medical_conditions_json')
                                                ->label('Other Medical Conditions')
                                                ->placeholder('Type condition and press Enter')
                                                ->separator(','),
                                            TagsInput::make('active_medications_json')
                                                ->label('Active Medications')
                                                ->placeholder('Type medication and press Enter')
                                                ->separator(','),
                                            Textarea::make('notes')
                                                ->label('Health Notes')
                                                ->columnSpanFull(),
                                        ])->columns(2),
                                ])
                        ]),

                    Tabs\Tab::make('3D Mouth Chart')
                        ->icon('heroicon-o-sparkles')
                        ->schema([
                            \Filament\Forms\Components\View::make('filament.resources.patient-resource.tabs.odontogram')
                        ])
                        ->hidden(fn (?Patient $record) => $record === null),
                ])->columnSpanFull(),
        ];
    }
}
