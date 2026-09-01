<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StaffMemberResource\Pages;
use App\Models\Practice;
use App\Models\StaffMember;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class StaffMemberResource extends Resource
{
    protected static ?string $model = StaffMember::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Staff & HR Management';

    protected static ?string $navigationLabel = 'Staff & Employees';

    protected static ?string $modelLabel = 'Staff Member';

    protected static ?string $pluralModelLabel = 'Staff & Employees Directory';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Employee Identity & Clinic Role')
                    ->schema([
                        Forms\Components\TextInput::make('employee_id')
                            ->label('Employee ID #')
                            ->default(fn () => 'EMP-' . str_pad((string) (StaffMember::max('id') + 1), 4, '0', STR_PAD_LEFT))
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->placeholder('Auto-generated (e.g. EMP-0001)'),
                        Forms\Components\TextInput::make('first_name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('last_name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('role')
                            ->label('Clinical / Administrative Role')
                            ->options([
                                'Lead Dentist' => 'Lead Dentist / Clinical Director',
                                'Associate Dentist' => 'Associate General Dentist',
                                'Orthodontist Specialist' => 'Orthodontist Specialist',
                                'Endodontist Specialist' => 'Endodontist Specialist',
                                'Periodontist / Surgeon' => 'Periodontist / Implant Surgeon',
                                'Dental Hygienist (RDH)' => 'Registered Dental Hygienist (RDH)',
                                'Dental Assistant (CDA)' => 'Certified Dental Assistant (CDA)',
                                'Front Desk / Patient Coordinator' => 'Front Desk / Patient Coordinator',
                                'Practice Manager' => 'Practice / Clinic Manager',
                                'Sterilization Technician' => 'Sterilization & Infection Control Tech',
                                'Billing & Insurance Specialist' => 'Billing & Insurance Specialist',
                            ])
                            ->required()
                            ->searchable(),
                        Forms\Components\Select::make('employment_type')
                            ->options([
                                'Full-Time' => 'Full-Time Employee',
                                'Part-Time' => 'Part-Time Employee',
                                'Contractor / Locum' => 'Contractor / Locum Tenens',
                            ])
                            ->default('Full-Time')
                            ->required(),
                        Forms\Components\DatePicker::make('hire_date')
                            ->label('Date of Joining')
                            ->default(now()),
                        Forms\Components\Select::make('user_id')
                            ->label('Linked System Login Account')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->placeholder('None (No system login)'),
                        Forms\Components\Select::make('practice_id')
                            ->relationship('practice', 'name')
                            ->default(fn () => Practice::firstOrCreate(['name' => 'Main Clinic'])->id)
                            ->required(),
                    ])->columns(3),

                Forms\Components\Section::make('Contact & Identification')
                    ->schema([
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->maxLength(50),
                        Forms\Components\TextInput::make('national_id')
                            ->label('National ID / SSN / License #')
                            ->placeholder('e.g. DENT-LIC-99412'),
                        Forms\Components\TextInput::make('emergency_contact')
                            ->label('Emergency Contact & Phone')
                            ->placeholder('e.g. John Smith (Spouse) +1 555-0012'),
                    ])->columns(2),

                Forms\Components\Section::make('Compensation & Direct Deposit Payroll')
                    ->schema([
                        Forms\Components\TextInput::make('base_salary')
                            ->label('Monthly Base Salary ($)')
                            ->numeric()
                            ->prefix('$')
                            ->default(0.00),
                        Forms\Components\TextInput::make('hourly_rate')
                            ->label('Hourly Wage Rate ($/hr)')
                            ->numeric()
                            ->prefix('$')
                            ->default(0.00),
                        Forms\Components\TextInput::make('bank_name')
                            ->label('Primary Bank Name')
                            ->placeholder('e.g. Chase Bank, Bank of America'),
                        Forms\Components\TextInput::make('bank_account_number')
                            ->label('Bank Account / IBAN #')
                            ->placeholder('e.g. US88-CHAS-00192837'),
                        Forms\Components\TextInput::make('tax_id')
                            ->label('Tax ID / W-4 Exemption #'),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active Staff Member')
                            ->default(true),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee_id')
                    ->label('Emp #')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Staff Name')
                    ->searchable(['first_name', 'last_name'])
                    ->weight('medium'),
                Tables\Columns\TextColumn::make('role')
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('employment_type')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('base_salary')
                    ->label('Monthly Salary')
                    ->money('USD')
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->options([
                        'Lead Dentist' => 'Lead Dentist',
                        'Associate Dentist' => 'Associate Dentist',
                        'Dental Hygienist (RDH)' => 'Dental Hygienist (RDH)',
                        'Dental Assistant (CDA)' => 'Dental Assistant (CDA)',
                        'Front Desk / Patient Coordinator' => 'Front Desk',
                        'Practice Manager' => 'Practice Manager',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStaffMembers::route('/'),
            'create' => Pages\CreateStaffMember::route('/create'),
            'edit' => Pages\EditStaffMember::route('/{record}/edit'),
        ];
    }
}
