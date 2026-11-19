<?php

namespace App\Filament\Resources\PatientResource\Tables;

use App\Models\Patient;
use App\Services\CurrencyHelper;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Patients directory (design/stitch/02-patients-list).
 * Aggregates come from withMax/withMin/withSum in query(), so there is no N+1.
 */
class PatientTable
{
    /** Adds last visit, next appointment and balance aggregates to the list query. */
    public static function query(Builder $query): Builder
    {
        return $query
            ->withMax(['appointments as last_visit_at' => fn ($q) => $q->where('status', 'completed')], 'start_time')
            ->withMin(['appointments as next_appointment_at' => fn ($q) => $q
                ->where('start_time', '>=', now())
                ->whereNotIn('status', ['cancelled', 'no_show', 'completed'])], 'start_time')
            ->withSum(['invoices as balance_total' => fn ($q) => $q->whereNotIn('status', ['cancelled', 'void', 'draft'])], 'balance_due');
    }

    public static function columns(): array
    {
        return [
            ViewColumn::make('patient')
                ->label('Patient')
                ->view('filament.tables.columns.patient-identity')
                ->searchable(['first_name', 'last_name', 'national_id'])
                ->sortable(['first_name', 'last_name']),
            TextColumn::make('file_number')
                ->label('File #')
                ->badge()
                ->color('gray')
                ->copyable()
                ->searchable()
                ->sortable(),
            TextColumn::make('phone')
                ->label('Contact')
                ->icon('heroicon-o-chat-bubble-left-ellipsis')
                ->iconColor('primary')
                ->description(fn (Patient $record): ?string => $record->email)
                ->searchable(['phone', 'email', 'whatsapp_number']),
            TextColumn::make('last_visit_at')
                ->label('Last visit')
                ->date('M j, Y')
                ->placeholder('No visits yet')
                ->sortable(),
            TextColumn::make('next_appointment_at')
                ->label('Next appointment')
                ->formatStateUsing(fn ($state): string => self::relativeDay($state))
                ->color('primary')
                ->weight('semibold')
                ->placeholder('None scheduled')
                ->sortable(),
            TextColumn::make('balance_total')
                ->label('Balance due')
                ->alignEnd()
                ->formatStateUsing(fn ($state): string => self::money((float) $state))
                ->description(fn (Patient $record): string => (float) $record->balance_total > 0 ? 'Outstanding' : 'Settled')
                ->color(fn ($state): string => (float) $state > 0 ? 'danger' : 'gray')
                ->weight(fn ($state): string => (float) $state > 0 ? 'bold' : 'medium')
                ->default(0)
                ->sortable(),
            TextColumn::make('national_id')
                ->label('National ID')
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('status')
                ->badge()
                ->formatStateUsing(fn (?string $state): string => ucfirst((string) $state))
                ->color(fn (?string $state): string => match ($state) {
                    'active' => 'success',
                    'inactive' => 'warning',
                    'archived' => 'danger',
                    default => 'primary',
                }),
        ];
    }

    public static function filters(): array
    {
        return [
            SelectFilter::make('status')
                ->options([
                    'active' => 'Active',
                    'inactive' => 'Inactive',
                    'archived' => 'Archived',
                ]),
            SelectFilter::make('gender')
                ->options([
                    'male' => 'Male',
                    'female' => 'Female',
                ]),
            Filter::make('has_balance')
                ->label('Has outstanding balance')
                ->toggle()
                ->query(fn (Builder $query): Builder => $query->whereHas('invoices', fn ($q) => $q->where('balance_due', '>', 0))),
            Filter::make('has_upcoming')
                ->label('Has upcoming appointment')
                ->toggle()
                ->query(fn (Builder $query): Builder => $query->whereHas('appointments', fn ($q) => $q
                    ->where('start_time', '>=', now())
                    ->whereNotIn('status', ['cancelled', 'no_show', 'completed']))),
        ];
    }

    public static function actions(): array
    {
        return [
            Tables\Actions\ViewAction::make()->iconButton()->tooltip('Open patient file'),
            Tables\Actions\EditAction::make()->iconButton()->tooltip('Edit'),
            Tables\Actions\ActionGroup::make([
                Tables\Actions\DeleteAction::make(),
            ]),
        ];
    }

    protected static function relativeDay($state): string
    {
        if (! $state) {
            return '';
        }

        $date = Carbon::parse($state);
        $time = $date->format('h:i A');

        return match (true) {
            $date->isToday() => "Today, {$time}",
            $date->isTomorrow() => "Tomorrow, {$time}",
            default => $date->format('M j, Y').", {$time}",
        };
    }

    protected static function money(float $amount): string
    {
        return CurrencyHelper::format($amount);
    }
}
