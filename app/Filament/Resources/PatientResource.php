<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PatientResource\Pages;
use App\Filament\Resources\PatientResource\RelationManagers;
use App\Models\Patient;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\PatientResource\Forms\PatientForm;
use App\Filament\Resources\PatientResource\Tables\PatientTable;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;

class PatientResource extends Resource
{
    protected static ?string $model = Patient::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema(PatientForm::schema());
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                \Filament\Infolists\Components\Tabs::make('Patient Profile')
                    ->tabs([
                        \Filament\Infolists\Components\Tabs\Tab::make('General Info')
                            ->icon('heroicon-o-user')
                            ->schema([
                                Section::make('Demographics')
                                    ->schema([
                                        TextEntry::make('file_number')->label('File #'),
                                        TextEntry::make('first_name')->label('First Name'),
                                        TextEntry::make('last_name')->label('Last Name'),
                                        TextEntry::make('national_id')->label('National ID'),
                                        TextEntry::make('gender')->badge(),
                                        TextEntry::make('dob')->label('Date of Birth')->date(),
                                        TextEntry::make('phone'),
                                        TextEntry::make('whatsapp_number'),
                                        TextEntry::make('email'),
                                        TextEntry::make('emergency_contact'),
                                        TextEntry::make('status')->badge()->color(fn (string $state): string => match ($state) {
                                            'active' => 'success',
                                            'inactive' => 'warning',
                                            'archived' => 'danger',
                                            default => 'primary',
                                        }),
                                        TextEntry::make('address')->columnSpanFull(),
                                    ])->columns(3),
                            ]),

                        \Filament\Infolists\Components\Tabs\Tab::make('Medical History & Alerts')
                            ->icon('heroicon-o-heart')
                            ->visible(fn () => auth()->user()->hasAnyRole(['doctor', 'clinic_admin', 'super_admin']))
                            ->schema([
                                Section::make('Systemic Conditions')
                                    ->schema([
                                        TextEntry::make('medicalHistory.blood_type')->label('Blood Type')->badge()->color('danger'),
                                        \Filament\Infolists\Components\IconEntry::make('medicalHistory.diabetic_status')->label('Diabetic')->boolean(),
                                        \Filament\Infolists\Components\IconEntry::make('medicalHistory.cardiac_history')->label('Cardiac History')->boolean(),
                                        \Filament\Infolists\Components\IconEntry::make('medicalHistory.hypertension_status')->label('Hypertension')->boolean(),
                                        \Filament\Infolists\Components\IconEntry::make('medicalHistory.bleeding_disorder')->label('Bleeding Disorder')->boolean(),
                                    ])->columns(5),

                                Section::make('Allergies')
                                    ->schema([
                                        \Filament\Infolists\Components\IconEntry::make('medicalHistory.latex_allergy')->label('Latex Allergy')->boolean(),
                                        \Filament\Infolists\Components\IconEntry::make('medicalHistory.penicillin_allergy')->label('Penicillin Allergy')->boolean(),
                                        \Filament\Infolists\Components\IconEntry::make('medicalHistory.local_anesthetic_allergy')->label('Local Anesthetic Allergy')->boolean(),
                                    ])->columns(3),

                                Section::make('Additional Information')
                                    ->schema([
                                        TextEntry::make('medicalHistory.medical_conditions_json')->label('Other Conditions')->badge(),
                                        TextEntry::make('medicalHistory.active_medications_json')->label('Active Medications')->badge(),
                                        TextEntry::make('medicalHistory.notes')->label('Health Notes')->columnSpanFull(),
                                    ])->columns(2),
                            ]),

                        \Filament\Infolists\Components\Tabs\Tab::make('3D Mouth Chart')
                            ->icon('heroicon-o-sparkles')
                            ->visible(fn () => auth()->user()->hasAnyRole(['doctor', 'clinic_admin', 'super_admin']))
                            ->schema([
                                \Filament\Infolists\Components\View::make('filament.resources.patient-resource.tabs.odontogram')
                            ]),

                        \Filament\Infolists\Components\Tabs\Tab::make('Finance & Billing')
                            ->icon('heroicon-o-currency-dollar')
                            ->schema([
                                \Filament\Infolists\Components\View::make('filament.resources.patient-resource.tabs.finance')
                            ]),
                            
                        \Filament\Infolists\Components\Tabs\Tab::make('Lab Orders')
                            ->icon('heroicon-o-beaker')
                            ->schema([
                                \Filament\Infolists\Components\View::make('filament.resources.patient-resource.tabs.lab-orders')
                            ]),
                    ])->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns(PatientTable::columns())
            ->filters(PatientTable::filters())
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\AppointmentsRelationManager::class,
            RelationManagers\TreatmentPlansRelationManager::class,
            RelationManagers\FilesRelationManager::class,
            RelationManagers\PrescriptionsRelationManager::class,
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
