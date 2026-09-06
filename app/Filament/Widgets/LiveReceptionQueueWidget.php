<?php

namespace App\Filament\Widgets;

use App\Models\Appointment;
use Carbon\Carbon;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class LiveReceptionQueueWidget extends BaseWidget
{
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 'full';
    protected static ?string $heading = 'Live Reception Queue';

    public static function canView(): bool
    {
        return auth()->user()->hasAnyRole(['secretary', 'clinic_admin', 'super_admin']);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Appointment::query()
                    ->whereDate('start_time', Carbon::today())
                    ->whereIn('status', ['booked', 'arrived', 'in_chair', 'completed'])
                    ->orderBy('start_time', 'asc')
            )
            ->columns([
                Tables\Columns\TextColumn::make('start_time')
                    ->label('Time')
                    ->time('h:i A')
                    ->sortable(),
                Tables\Columns\TextColumn::make('patient.full_name')
                    ->label('Patient')
                    ->searchable()
                    ->url(fn (\App\Models\Appointment $record): string => \App\Filament\Resources\PatientResource::getUrl('view', ['record' => $record->patient_id])),
                Tables\Columns\TextColumn::make('doctor.name')
                    ->label('Doctor')
                    ->searchable(),
                Tables\Columns\TextColumn::make('operatory.name')
                    ->label('Room'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'booked' => 'primary',
                        'arrived' => 'warning',
                        'in_chair' => 'danger',
                        'completed' => 'success',
                        default => 'secondary',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst(str_replace('_', ' ', $state))),
            ])
            ->actions([
                Tables\Actions\Action::make('update_status')
                    ->label('Update Status')
                    ->icon('heroicon-o-arrow-path')
                    ->form([
                        \Filament\Forms\Components\Select::make('status')
                            ->options([
                                'booked' => 'Booked',
                                'arrived' => 'Arrived (Waiting Room)',
                                'in_chair' => 'In Chair',
                                'completed' => 'Completed (Checkout)',
                                'cancelled' => 'Cancelled',
                            ])
                            ->required()
                            ->default(fn (Appointment $record) => $record->status),
                    ])
                    ->action(function (Appointment $record, array $data): void {
                        $record->update(['status' => $data['status']]);
                    }),
            ])
            ->paginated(false);
    }
}
