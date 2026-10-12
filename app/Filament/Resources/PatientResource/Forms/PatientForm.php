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
                                    Select::make('practice_id')->hidden()
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
                                    Section::make('Systemic Conditions & Vitals')
                                        ->description('Track systemic health risks, diabetes control, cardiovascular, and bleeding factors.')
                                        ->schema([
                                            Select::make('blood_type')
                                                ->options([
                                                    'A+' => 'A+', 'A-' => 'A-', 'B+' => 'B+', 'B-' => 'B-',
                                                    'AB+' => 'AB+', 'AB-' => 'AB-', 'O+' => 'O+', 'O-' => 'O-',
                                                ])
                                                ->placeholder('Select Blood Type')
                                                ->columnSpan(1),

                                            // Diabetes Mellitus
                                            Toggle::make('diabetic_status')
                                                ->label('Diabetic')
                                                ->live()
                                                ->columnSpan(1),

                                            Group::make()
                                                ->schema([
                                                    Select::make('diabetes_type')
                                                        ->label('Diabetes Classification')
                                                        ->options(\App\Services\ClinicalPresets::diabetesTypes())
                                                        ->searchable()
                                                        ->preload(),
                                                    Select::make('hba1c_level')
                                                        ->label('HbA1c Glycemic Control')
                                                        ->options(\App\Services\ClinicalPresets::hba1cLevels())
                                                        ->searchable()
                                                        ->preload(),
                                                    TextInput::make('blood_sugar_reading')
                                                        ->label('Recent Blood Sugar')
                                                        ->numeric()
                                                        ->suffix('mg/dL')
                                                        ->placeholder('e.g., 120'),
                                                    TagsInput::make('diabetes_medications')
                                                        ->label('Diabetes Medications')
                                                        ->suggestions(\App\Services\ClinicalPresets::diabetesMedications())
                                                        ->placeholder('Type medication...'),
                                                    Toggle::make('hypoglycemia_history')
                                                        ->label('History of Hypoglycemia (fainting/sweating episodes)')
                                                        ->columnSpanFull(),
                                                ])
                                                ->columns(2)
                                                ->columnSpanFull()
                                                ->visible(fn (\Filament\Forms\Get $get) => (bool) $get('diabetic_status')),

                                            // Cardiovascular & Blood Pressure
                                            Toggle::make('hypertension_status')
                                                ->label('Hypertension')
                                                ->live()
                                                ->columnSpan(1),

                                            Group::make()
                                                ->schema([
                                                    Select::make('blood_pressure_stage')
                                                        ->label('BP Stage (AHA)')
                                                        ->options(\App\Services\ClinicalPresets::bloodPressureStages())
                                                        ->searchable()
                                                        ->preload(),
                                                    TextInput::make('typical_blood_pressure')
                                                        ->label('Typical Blood Pressure')
                                                        ->placeholder('e.g., 125/80 mmHg'),
                                                ])
                                                ->columns(2)
                                                ->columnSpanFull()
                                                ->visible(fn (\Filament\Forms\Get $get) => (bool) $get('hypertension_status')),

                                            Toggle::make('cardiac_history')
                                                ->label('Cardiac History')
                                                ->live()
                                                ->columnSpan(1),

                                            Group::make()
                                                ->schema([
                                                    TagsInput::make('cardiac_conditions_json')
                                                        ->label('Cardiac Conditions & Implants')
                                                        ->suggestions(\App\Services\ClinicalPresets::cardiacConditions())
                                                        ->placeholder('Select or type condition...'),
                                                    Toggle::make('requires_antibiotic_prophylaxis')
                                                        ->label('AHA Antibiotic Prophylaxis Required (e.g. 2g Amoxicillin prior to treatment)'),
                                                ])
                                                ->columns(2)
                                                ->columnSpanFull()
                                                ->visible(fn (\Filament\Forms\Get $get) => (bool) $get('cardiac_history')),

                                            // Bleeding Disorders & Blood Thinners
                                            Toggle::make('bleeding_disorder')
                                                ->label('Bleeding Disorder / Anticoagulants')
                                                ->live()
                                                ->columnSpan(1),

                                            Group::make()
                                                ->schema([
                                                    Select::make('anticoagulant_type')
                                                        ->label('Anticoagulant / Blood Thinner')
                                                        ->options(\App\Services\ClinicalPresets::anticoagulants())
                                                        ->searchable()
                                                        ->preload(),
                                                    TextInput::make('inr_level')
                                                        ->label('Latest INR Level (if on Warfarin)')
                                                        ->numeric()
                                                        ->step(0.1)
                                                        ->placeholder('Safe dental range: 2.0 - 3.0'),
                                                ])
                                                ->columns(2)
                                                ->columnSpanFull()
                                                ->visible(fn (\Filament\Forms\Get $get) => (bool) $get('bleeding_disorder')),
                                        ])->columns(2),

                                    Section::make('Bone Antiresorptive Therapy (MRONJ Risk)')
                                        ->description('Bisphosphonates or Denosumab therapy poses serious risk of Medication-Related Osteonecrosis of the Jaw.')
                                        ->schema([
                                            Toggle::make('bisphosphonate_therapy')
                                                ->label('Bisphosphonates / Antiresorptive Therapy (Fosamax, Prolia, Zometa)')
                                                ->helperText('Check if patient took oral or IV bisphosphonates for osteoporosis or oncology.')
                                                ->live(),
                                            TextInput::make('bisphosphonate_details')
                                                ->label('Drug Name, Route (Oral/IV) & Duration')
                                                ->placeholder('e.g. Fosamax 70mg oral for 3 years')
                                                ->visible(fn (\Filament\Forms\Get $get) => (bool) $get('bisphosphonate_therapy')),
                                        ])->columns(2),

                                    Section::make('Dental Sensitivities & Clinical Flags')
                                        ->schema([
                                            Select::make('dental_anxiety_level')
                                                ->label('Dental Anxiety Level')
                                                ->options(\App\Services\ClinicalPresets::anxietyLevels())
                                                ->default('low'),
                                            Toggle::make('severe_gag_reflex')
                                                ->label('Severe Gag Reflex'),
                                            Select::make('pregnancy_trimester')
                                                ->label('Pregnancy Status')
                                                ->options([
                                                    'none' => 'Not Pregnant',
                                                    'first' => '1st Trimester',
                                                    'second' => '2nd Trimester (Ideal for treatment)',
                                                    'third' => '3rd Trimester',
                                                    'nursing' => 'Nursing / Lactating',
                                                ])
                                                ->default('none'),
                                        ])->columns(3),

                                    Section::make('Allergies & Adverse Reactions')
                                        ->schema([
                                            Toggle::make('latex_allergy')->label('Latex Allergy'),
                                            Toggle::make('penicillin_allergy')->label('Penicillin Allergy'),
                                            Toggle::make('local_anesthetic_allergy')->label('Local Anesthetic Allergy'),
                                        ])->columns(3),

                                    Section::make('Ophthalmology History')
                                        ->schema([
                                            Toggle::make('glaucoma_history')->label('Glaucoma'),
                                            Toggle::make('cataract_history')->label('Cataracts'),
                                            Toggle::make('dry_eye_syndrome')->label('Dry Eye Syndrome'),
                                            Toggle::make('wears_contact_lenses')->label('Wears Contact Lenses'),
                                            Toggle::make('vision_correction_history')->label('Prior Vision Correction Surgery'),
                                        ])
                                        ->columns(3)
                                        ->visible(fn () => \Filament\Facades\Filament::getTenant()?->type === 'ophthalmology'),

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
                        ->hidden(fn (?Patient $record) => $record === null || !\Filament\Facades\Filament::getTenant()->hasFeature('3d_model')),
                ])->columnSpanFull(),
        ];
    }
}
