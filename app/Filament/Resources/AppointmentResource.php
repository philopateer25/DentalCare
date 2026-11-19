<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AppointmentResource\Pages;
use App\Models\Appointment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AppointmentResource extends Resource
{
    protected static ?string $model = Appointment::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Clinical Management';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Appointment Details')
                    ->schema([
                        Forms\Components\Select::make('patient_id')
                            ->label('Patient')
                            ->relationship('patient', 'first_name', function (\Illuminate\Database\Eloquent\Builder $query) {
                                $tenantId = \Filament\Facades\Filament::getTenant()?->id ?? auth()->user()?->practice_id;
                                $query->when($tenantId, fn ($q) => $q->where('practice_id', $tenantId));
                            })
                            ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->first_name} {$record->last_name} ({$record->file_number})")
                            ->searchable(['first_name', 'last_name', 'file_number'])
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('doctor_id')
                            ->label('Doctor')
                            ->relationship('doctor', 'name', function (\Illuminate\Database\Eloquent\Builder $query) {
                                $tenantId = \Filament\Facades\Filament::getTenant()?->id ?? auth()->user()?->practice_id;
                                $query->when($tenantId, fn ($q) => $q->where('practice_id', $tenantId))
                                    ->whereHas('roles', fn ($q) => $q->where('name', 'doctor'));
                            })
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('operatory_id')
                            ->label('Room (Operatory)')
                            ->relationship('operatory', 'name', function (\Illuminate\Database\Eloquent\Builder $query) {
                                $tenantId = \Filament\Facades\Filament::getTenant()?->id ?? auth()->user()?->practice_id;
                                $query->when($tenantId, fn ($q) => $q->whereHas('branch', fn ($bq) => $bq->where('practice_id', $tenantId)));
                            })
                            ->preload()
                            ->live()
                            ->required(),
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
                    ])->columns(2),

                Forms\Components\Section::make('Timing & Clinical Notes')
                    ->schema([
                        Forms\Components\DateTimePicker::make('start_time')
                            ->label('Start Date & Time')
                            ->native(false)
                            ->seconds(false)
                            ->minutesStep(15)
                            ->default(now())
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                if (!$state) return;
                                try {
                                    $duration = (int) ($get('duration_minutes') ?? 30);
                                    $set('end_time', \Carbon\Carbon::parse($state)->addMinutes($duration)->format('Y-m-d H:i:s'));
                                } catch (\Throwable $e) {}
                            })
                            ->required(),

                        Forms\Components\Select::make('duration_minutes')
                            ->label('Visit Duration')
                            ->options([
                                '15' => '15 minutes',
                                '30' => '30 minutes (Standard)',
                                '45' => '45 minutes',
                                '60' => '1 hour',
                                '90' => '1.5 hours',
                                '120' => '2 hours',
                            ])
                            ->default('30')
                            ->dehydrated(false)
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                $start = $get('start_time');
                                if (!$start) return;
                                try {
                                    $set('end_time', \Carbon\Carbon::parse($start)->addMinutes((int)$state)->format('Y-m-d H:i:s'));
                                } catch (\Throwable $e) {}
                            }),

                        Forms\Components\DateTimePicker::make('end_time')
                            ->label('End Date & Time')
                            ->native(false)
                            ->seconds(false)
                            ->minutesStep(15)
                            ->default(now()->addMinutes(30))
                            ->live(onBlur: true)
                            ->required(),

                        // Live preview of room maintenance; the save itself is guarded by a pop-up / closure error.
                        \App\Filament\Support\MaintenanceBookingFields::notice(),

                        Forms\Components\Textarea::make('chief_complaint')
                            ->label('Chief Complaint')
                            ->placeholder('e.g. Tooth sensitivity to cold, routine checkup...')
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('notes')
                            ->label('Clinical Notes')
                            ->columnSpanFull(),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->searchDebounce('750ms')
            ->modifyQueryUsing(function (\Illuminate\Database\Eloquent\Builder $query) {
                if (auth()->user()->isDoctor() || auth()->user()->hasRole('doctor')) {
                    $query->where('doctor_id', auth()->id());
                }
            })
            ->columns([
                Tables\Columns\TextColumn::make('start_time')
                    ->label('Date & Time')
                    ->dateTime('D, d M Y h:i A')
                    ->sortable(),
                Tables\Columns\TextColumn::make('patient.first_name')
                    ->label('Patient')
                    ->formatStateUsing(fn ($state, \App\Models\Appointment $record): string => "{$record->patient?->first_name} {$record->patient?->last_name}")
                    ->description(fn (\App\Models\Appointment $record): ?string => $record->patient?->file_number ? "File #{$record->patient->file_number}" : null)
                    ->searchable(query: function (\Illuminate\Database\Eloquent\Builder $query, string $search): \Illuminate\Database\Eloquent\Builder {
                        return $query->whereHas('patient', function ($q) use ($search) {
                            $q->where('first_name', 'like', "%{$search}%")
                              ->orWhere('last_name', 'like', "%{$search}%")
                              ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"])
                              ->orWhere('file_number', 'like', "%{$search}%")
                              ->orWhere('phone', 'like', "%{$search}%");
                        });
                    })
                    ->sortable(query: fn ($query, $direction) => $query->orderBy(
                        \App\Models\Patient::select('first_name')->whereColumn('patients.id', 'appointments.patient_id'),
                        $direction
                    )),
                Tables\Columns\TextColumn::make('doctor.name')
                    ->label('Doctor')
                    ->sortable(),
                Tables\Columns\TextColumn::make('operatory.name')
                    ->label('Operatory / Room')
                    ->placeholder('None'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'booked' => 'gray',
                        'arrived' => 'info',
                        'in_chair' => 'warning',
                        'completed' => 'success',
                        'cancelled', 'no_show' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\Filter::make('appointment_date')
                    ->form([
                        Forms\Components\DatePicker::make('date')
                            ->label('Filter by Day')
                            ->native(false),
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data): \Illuminate\Database\Eloquent\Builder {
                        return $query->when(
                            $data['date'] ?? null,
                            function (\Illuminate\Database\Eloquent\Builder $q, $date) {
                                $start = \Illuminate\Support\Carbon::parse($date, 'Africa/Cairo')->startOfDay()->utc();
                                $end = \Illuminate\Support\Carbon::parse($date, 'Africa/Cairo')->endOfDay()->utc();
                                return $q->whereBetween('start_time', [$start, $end]);
                            }
                        );
                    }),
                Tables\Filters\SelectFilter::make('doctor_id')
                    ->label('Filter by Doctor')
                    ->relationship('doctor', 'name', fn (\Illuminate\Database\Eloquent\Builder $query) => 
                        $query->where('practice_id', \Filament\Facades\Filament::getTenant()?->id)
                              ->whereHas('roles', fn ($sq) => $sq->where('name', 'doctor'))
                    )
                    ->preload(),
                Tables\Filters\SelectFilter::make('operatory_id')
                    ->label('Filter by Room (Operatory)')
                    ->relationship('operatory', 'name', fn (\Illuminate\Database\Eloquent\Builder $query) => 
                        $query->whereHas('branch', fn ($bq) => $bq->where('practice_id', \Filament\Facades\Filament::getTenant()?->id))
                    )
                    ->preload(),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'booked' => 'Booked',
                        'arrived' => 'Arrived',
                        'in_chair' => 'In Chair',
                        'completed' => 'Completed',
                        'no_show' => 'No Show',
                        'cancelled' => 'Cancelled',
                    ]),
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

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAppointments::route('/'),
            'create' => Pages\CreateAppointment::route('/create'),
            'edit' => Pages\EditAppointment::route('/{record}/edit'),
        ];
    }
}
