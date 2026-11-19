<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MaintenanceResource\Pages;
use App\Filament\Support\MaintenanceBookingFields;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\MaintenanceRecord;
use App\Models\Operatory;
use App\Services\Scheduling\RoomAvailabilityService;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

class MaintenanceResource extends Resource
{
    protected static ?string $model = MaintenanceRecord::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationGroup = 'Clinic Operations';

    protected static ?string $navigationLabel = 'Maintenance';

    protected static ?string $slug = 'maintenance';

    protected static ?string $modelLabel = 'Maintenance Job';

    protected static ?string $pluralModelLabel = 'Maintenance';

    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        $tenantId = Filament::getTenant()?->id;
        if (! $tenantId) {
            return null;
        }

        $count = MaintenanceRecord::where('practice_id', $tenantId)
            ->active()
            ->where('ends_at', '>', now())
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Job Details')
                ->icon('heroicon-o-wrench')
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->label('What is being fixed?')
                        ->placeholder('e.g. AC compressor repair, X-ray sensor calibration')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    Forms\Components\Select::make('category')
                        ->options(MaintenanceRecord::CATEGORIES)
                        ->default('other')
                        ->native(false)
                        ->required(),
                    Forms\Components\TextInput::make('equipment_name')
                        ->label('Equipment / Machine')
                        ->placeholder('e.g. Carestream RVG 6200, Split AC Room 2')
                        ->maxLength(255),
                    Forms\Components\Textarea::make('description')
                        ->rows(3)
                        ->columnSpanFull(),
                ])->columns(2),

            Forms\Components\Section::make('Schedule')
                ->icon('heroicon-o-clock')
                ->schema([
                    Forms\Components\DateTimePicker::make('starts_at')
                        ->label('Starts')
                        ->native(false)
                        ->seconds(false)
                        ->minutesStep(15)
                        ->default(now()->startOfHour()->addHour())
                        ->live(onBlur: true)
                        ->required(),
                    Forms\Components\DateTimePicker::make('ends_at')
                        ->label('Ends (expected)')
                        ->native(false)
                        ->seconds(false)
                        ->minutesStep(15)
                        ->default(now()->startOfHour()->addHours(3))
                        ->after('starts_at')
                        ->live(onBlur: true)
                        ->required(),
                    Forms\Components\Select::make('status')
                        ->options(MaintenanceRecord::STATUSES)
                        ->default(MaintenanceRecord::STATUS_SCHEDULED)
                        ->native(false)
                        ->required(),
                ])->columns(3),

            Forms\Components\Section::make('Room Impact')
                ->icon('heroicon-o-building-office')
                ->description('Choose whether this job closes rooms (no bookings allowed) or rooms stay operational (staff see a warning when booking).')
                ->schema([
                    Forms\Components\Select::make('branch_id')
                        ->label('Branch')
                        ->options(fn () => Branch::where('practice_id', Filament::getTenant()?->id)->pluck('name', 'id'))
                        ->default(fn () => Branch::where('practice_id', Filament::getTenant()?->id)->whereHas('operatories')->value('id')
                            ?? Branch::where('practice_id', Filament::getTenant()?->id)->value('id'))
                        ->native(false)
                        ->live()
                        ->afterStateUpdated(fn (Forms\Set $set) => $set('operatories', []))
                        ->required(),

                    Forms\Components\Radio::make('impact')
                        ->label('How does this affect the rooms?')
                        ->options(MaintenanceRecord::IMPACTS)
                        ->descriptions([
                            MaintenanceRecord::IMPACT_CLOSES_BRANCH => 'Every room in the branch is closed — no appointments can be booked during this window.',
                            MaintenanceRecord::IMPACT_CLOSES_ROOMS => 'Only the rooms you tick below are closed. Other rooms work normally.',
                            MaintenanceRecord::IMPACT_NO_CLOSURE => 'Rooms remain bookable, but staff get a pop-up telling them what is missing.',
                        ])
                        ->default(MaintenanceRecord::IMPACT_NO_CLOSURE)
                        ->live()
                        ->required(),

                    Forms\Components\CheckboxList::make('operatories')
                        ->label(fn (Forms\Get $get) => $get('impact') === MaintenanceRecord::IMPACT_CLOSES_ROOMS
                            ? 'Rooms to close'
                            : 'Affected rooms (leave empty = all rooms in branch)')
                        ->relationship('operatories', 'name', fn ($query, Forms\Get $get) => $query
                            ->where('branch_id', $get('branch_id'))
                            ->whereHas('branch', fn ($bq) => $bq->where('practice_id', Filament::getTenant()?->id)))
                        ->columns(3)
                        ->bulkToggleable()
                        ->live()
                        ->visible(fn (Forms\Get $get) => $get('impact') !== MaintenanceRecord::IMPACT_CLOSES_BRANCH)
                        ->required(fn (Forms\Get $get) => $get('impact') === MaintenanceRecord::IMPACT_CLOSES_ROOMS),

                    Forms\Components\Textarea::make('unavailable_items')
                        ->label('What will be missing / unavailable?')
                        ->helperText('Shown in the pop-up when someone books an appointment in an affected room.')
                        ->placeholder('e.g. No X-ray available — panoramic imaging must be rescheduled. AC off, room may be warm.')
                        ->rows(2)
                        ->visible(fn (Forms\Get $get) => $get('impact') === MaintenanceRecord::IMPACT_NO_CLOSURE)
                        ->required(fn (Forms\Get $get) => $get('impact') === MaintenanceRecord::IMPACT_NO_CLOSURE),

                    Forms\Components\Placeholder::make('affected_appointments')
                        ->label('Existing appointments in this window')
                        ->content(fn (Forms\Get $get, ?MaintenanceRecord $record) => static::affectedAppointmentsHtml($get, $record)),
                ]),

            Forms\Components\Section::make('Vendor & Cost')
                ->icon('heroicon-o-banknotes')
                ->collapsible()
                ->schema([
                    Forms\Components\TextInput::make('vendor_name')
                        ->label('Technician / Company')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('vendor_phone')
                        ->label('Contact Phone')
                        ->tel()
                        ->maxLength(50),
                    Forms\Components\TextInput::make('cost')
                        ->numeric()
                        ->minValue(0)
                        ->prefix(fn () => Filament::getTenant()?->currency ?? 'EGP'),
                    Forms\Components\Textarea::make('notes')
                        ->rows(2)
                        ->columnSpanFull(),
                ])->columns(3),
        ]);
    }

    /** Builds an unsaved record from live form state and lists clashing appointments. */
    protected static function affectedAppointmentsHtml(Forms\Get $get, ?MaintenanceRecord $record): HtmlString
    {
        $appointments = static::affectedAppointmentsFromState(
            $get('branch_id'),
            $get('impact'),
            (array) ($get('operatories') ?? []),
            $get('starts_at'),
            $get('ends_at'),
        );

        if ($appointments->isEmpty()) {
            return new HtmlString('<span class="text-sm text-gray-500">No appointments are booked in the affected rooms during this window.</span>');
        }

        $closes = in_array($get('impact'), [MaintenanceRecord::IMPACT_CLOSES_BRANCH, MaintenanceRecord::IMPACT_CLOSES_ROOMS], true);
        $headline = $closes
            ? "{$appointments->count()} appointment(s) fall inside this closure and should be rescheduled:"
            : "{$appointments->count()} appointment(s) are booked in affected rooms (they will not be blocked):";

        $rows = $appointments->take(10)->map(fn ($a) => '<li>'.e($a->start_time->format('D d M, h:i A')).' — '
            .e($a->patient?->full_name ?? 'Patient').' · '.e($a->doctor?->name ?? '').' · <strong>'.e($a->operatory?->name ?? '').'</strong></li>')
            ->implode('');
        $more = $appointments->count() > 10 ? '<li>… and '.($appointments->count() - 10).' more</li>' : '';

        return MaintenanceBookingFields::callout($closes ? 'danger' : 'warning', $headline, $rows.$more);
    }

    public static function affectedAppointmentsFromState($branchId, ?string $impact, array $operatoryIds, $startsAt, $endsAt): Collection
    {
        $tenantId = Filament::getTenant()?->id;
        if (! $tenantId || ! $branchId || ! $impact || ! $startsAt || ! $endsAt) {
            return collect();
        }

        try {
            $start = Carbon::parse($startsAt);
            $end = Carbon::parse($endsAt);
        } catch (\Throwable) {
            return collect();
        }
        if ($end->lte($start)) {
            return collect();
        }

        $roomIds = ($impact === MaintenanceRecord::IMPACT_CLOSES_BRANCH || ($impact === MaintenanceRecord::IMPACT_NO_CLOSURE && empty($operatoryIds)))
            ? Operatory::where('branch_id', $branchId)->pluck('id')->all()
            : array_map('intval', $operatoryIds);

        if (empty($roomIds)) {
            return collect();
        }

        return Appointment::query()
            ->where('practice_id', $tenantId)
            ->whereIn('operatory_id', $roomIds)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->where('start_time', '<', $end)
            ->where('end_time', '>', $start)
            ->with(['patient', 'doctor', 'operatory'])
            ->orderBy('start_time')
            ->get();
    }

    /** Persistent notice after saving a closing job that overlaps bookings. */
    public static function notifyAffected(MaintenanceRecord $record): void
    {
        if (! $record->closesRoom() || ! $record->isActive()) {
            return;
        }

        $count = app(RoomAvailabilityService::class)->affectedAppointments($record)->count();
        if ($count === 0) {
            return;
        }

        Notification::make()
            ->title("{$count} existing appointment(s) fall inside this closure")
            ->body('These patients are booked in rooms that will be closed. Please reschedule them.')
            ->warning()
            ->persistent()
            ->actions([
                Action::make('view')
                    ->label('Open appointments')
                    ->url(AppointmentResource::getUrl('index')),
            ])
            ->send();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (MaintenanceRecord $r) => $r->equipment_name),
                Tables\Columns\TextColumn::make('category')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (string $state) => MaintenanceRecord::CATEGORIES[$state] ?? $state),
                Tables\Columns\TextColumn::make('impact')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => MaintenanceRecord::IMPACTS[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        MaintenanceRecord::IMPACT_CLOSES_BRANCH => 'danger',
                        MaintenanceRecord::IMPACT_CLOSES_ROOMS => 'warning',
                        default => 'info',
                    })
                    ->icon(fn (string $state) => $state === MaintenanceRecord::IMPACT_NO_CLOSURE ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-no-symbol'),
                Tables\Columns\TextColumn::make('rooms')
                    ->label('Rooms')
                    ->state(fn (MaintenanceRecord $r) => $r->impact === MaintenanceRecord::IMPACT_CLOSES_BRANCH || $r->operatories->isEmpty()
                        ? 'All rooms · '.($r->branch?->name ?? '')
                        : $r->operatories->pluck('name')->implode(', '))
                    ->wrap(),
                Tables\Columns\TextColumn::make('starts_at')
                    ->label('Window')
                    ->formatStateUsing(fn (MaintenanceRecord $r) => $r->starts_at->format('d M h:i A').' → '.($r->ends_at->isSameDay($r->starts_at) ? $r->ends_at->format('h:i A') : $r->ends_at->format('d M h:i A')))
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => MaintenanceRecord::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        MaintenanceRecord::STATUS_IN_PROGRESS => 'warning',
                        MaintenanceRecord::STATUS_COMPLETED => 'success',
                        MaintenanceRecord::STATUS_CANCELLED => 'gray',
                        default => 'primary',
                    }),
            ])
            ->modifyQueryUsing(fn ($query) => $query->with(['operatories', 'branch']))
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(MaintenanceRecord::STATUSES),
                Tables\Filters\SelectFilter::make('impact')->options(MaintenanceRecord::IMPACTS),
                Tables\Filters\SelectFilter::make('category')->options(MaintenanceRecord::CATEGORIES),
            ])
            ->actions([
                Tables\Actions\Action::make('start')
                    ->label('Start')
                    ->icon('heroicon-o-play')
                    ->color('warning')
                    ->visible(fn (MaintenanceRecord $r) => $r->status === MaintenanceRecord::STATUS_SCHEDULED && auth()->user()->can('update', $r))
                    ->action(fn (MaintenanceRecord $r) => $r->update(['status' => MaintenanceRecord::STATUS_IN_PROGRESS])),
                Tables\Actions\Action::make('complete')
                    ->label('Complete')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Marks the job as done and immediately re-opens the affected rooms for booking.')
                    ->visible(fn (MaintenanceRecord $r) => $r->isActive() && auth()->user()->can('update', $r))
                    ->action(function (MaintenanceRecord $r) {
                        $r->update(['status' => MaintenanceRecord::STATUS_COMPLETED, 'completed_at' => now()]);
                        Notification::make()->title('Maintenance completed — rooms re-opened')->success()->send();
                    }),
                Tables\Actions\Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (MaintenanceRecord $r) => $r->isActive() && auth()->user()->can('update', $r))
                    ->action(fn (MaintenanceRecord $r) => $r->update(['status' => MaintenanceRecord::STATUS_CANCELLED])),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No maintenance jobs')
            ->emptyStateDescription('Log AC repairs, machine servicing and other work here. Closures block bookings; partial outages warn staff.')
            ->emptyStateIcon('heroicon-o-wrench-screwdriver');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMaintenanceRecords::route('/'),
            'create' => Pages\CreateMaintenanceRecord::route('/create'),
            'edit' => Pages\EditMaintenanceRecord::route('/{record}/edit'),
        ];
    }
}
