<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OperatoryResource\Pages;
use App\Models\Branch;
use App\Models\Operatory;
use App\Models\Practice;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Rooms & chairs: the clinic adds, renames, moves and switches off its rooms.
 * Active rooms are limited by the chair limit from the license (central app);
 * the central app can also add / rename / switch off rooms remotely.
 */
class OperatoryResource extends Resource
{
    protected static ?string $model = Operatory::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Rooms & chairs';

    protected static ?string $modelLabel = 'room';

    protected static ?string $pluralModelLabel = 'Rooms & chairs';

    protected static ?int $navigationSort = 1;

    /** Rooms belong to a branch, not directly to the practice: scoped by hand below. */
    protected static bool $isScopedToTenant = false;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(['super_admin', 'clinic_admin', 'developer']);
    }

    public static function practice(): ?Practice
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Practice ? $tenant : auth()->user()?->practice;
    }

    public static function getEloquentQuery(): Builder
    {
        $practiceId = static::practice()?->id;

        return parent::getEloquentQuery()->whereHas('branch', fn ($q) => $q->where('practice_id', $practiceId));
    }

    /** "3 of 5 chairs in use" / "3 chairs in use (no limit)". */
    public static function usageText(): string
    {
        $practice = static::practice();
        if (! $practice) {
            return '';
        }
        $used = $practice->activeChairCount();
        $limit = $practice->effectiveChairLimit();

        return $limit === null
            ? __(':used active rooms (no chair limit).', ['used' => $used])
            : __(':used of :limit chairs in use. Switched-off rooms do not count. To get more chairs, ask DOXamigo.', ['used' => $used, 'limit' => $limit]);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label(__('Room / chair name'))
                ->placeholder(__('e.g. Room 1, Surgery, Ortho chair'))
                ->required()
                ->maxLength(100)
                ->unique(
                    ignoreRecord: true,
                    modifyRuleUsing: fn ($rule, Forms\Get $get) => $rule->where('branch_id', $get('branch_id')),
                ),
            Forms\Components\Select::make('branch_id')
                ->label(__('Branch'))
                ->options(fn () => Branch::query()->where('practice_id', static::practice()?->id)->orderBy('name')->pluck('name', 'id'))
                ->default(fn () => Branch::query()->where('practice_id', static::practice()?->id)->orderBy('id')->value('id'))
                ->required()
                ->live()
                ->createOptionForm([
                    Forms\Components\TextInput::make('name')->label(__('Branch name'))->required()->maxLength(100),
                    Forms\Components\TextInput::make('address')->maxLength(255),
                    Forms\Components\TextInput::make('phone')->tel()->maxLength(30),
                ])
                ->createOptionUsing(fn (array $data) => Branch::create($data + ['practice_id' => static::practice()?->id, 'is_active' => true])->id)
                ->rule(fn () => function (string $attribute, $value, \Closure $fail) {
                    // Never accept another clinic's branch.
                    if (! Branch::whereKey($value)->where('practice_id', static::practice()?->id)->exists()) {
                        $fail(__('Choose one of your branches.'));
                    }
                }),
            Forms\Components\TextInput::make('code')
                ->label(__('Short code (optional)'))
                ->maxLength(20),
            Forms\Components\Toggle::make('is_active')
                ->label(__('In use'))
                ->helperText(__('Switch off a room you no longer use: its past appointments are kept and it frees a chair.'))
                ->default(true),
            Forms\Components\Textarea::make('notes')->columnSpanFull()->maxLength(1000),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description(fn () => static::usageText())
            ->defaultSort('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')->label(__('Room'))->searchable()->sortable()->weight('bold'),
                Tables\Columns\TextColumn::make('branch.name')->label(__('Branch'))->sortable(),
                Tables\Columns\TextColumn::make('code')->label(__('Code'))->toggleable(),
                Tables\Columns\IconColumn::make('is_active')->label(__('In use'))->boolean(),
                Tables\Columns\TextColumn::make('upcoming_count')
                    ->label(__('Upcoming appointments'))
                    ->state(fn (Operatory $record) => $record->appointments()->where('start_time', '>=', now())->count()),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label(__('In use')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('toggle')
                    ->label(fn (Operatory $record) => $record->is_active ? __('Switch off') : __('Switch on'))
                    ->icon(fn (Operatory $record) => $record->is_active ? 'heroicon-o-pause-circle' : 'heroicon-o-play-circle')
                    ->color('gray')
                    ->requiresConfirmation(fn (Operatory $record) => $record->is_active)
                    ->modalDescription(fn (Operatory $record) => __('Upcoming appointments in :room stay as they are; move them to another room if needed.', ['room' => $record->name]))
                    ->action(function (Operatory $record) {
                        try {
                            $record->update(['is_active' => ! $record->is_active]);
                        } catch (ValidationException $e) {
                            Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();
                        }
                    }),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (Operatory $record) => ! $record->appointments()->exists())
                    ->modalDescription(__('Only rooms without any appointments can be deleted. Rooms with history can be switched off instead.')),
            ])
            ->emptyStateHeading(__('No rooms yet'))
            ->emptyStateDescription(__('Add the rooms / chairs patients are seen in. Appointments are booked into them.'));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOperatories::route('/'),
            'create' => Pages\CreateOperatory::route('/create'),
            'edit' => Pages\EditOperatory::route('/{record}/edit'),
        ];
    }
}
