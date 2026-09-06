<?php

namespace App\Filament\Widgets;

use App\Models\LabOrder;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class DoctorPendingLabsWidget extends BaseWidget
{
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 1;
    protected static ?string $heading = 'My Pending Lab Orders';

    public static function canView(): bool
    {
        return auth()->user()->hasRole('doctor') || auth()->user()->isDoctor();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                LabOrder::query()
                    ->where('doctor_id', auth()->id())
                    ->where('status', '!=', 'delivered')
                    ->orderBy('expected_delivery_at', 'asc')
            )
            ->columns([
                Tables\Columns\TextColumn::make('patient.full_name')
                    ->label('Patient'),
                Tables\Columns\TextColumn::make('dentalLab.name')
                    ->label('Lab'),
                Tables\Columns\TextColumn::make('expected_delivery_at')
                    ->label('Expected')
                    ->date('M d, Y')
                    ->color(fn (LabOrder $record): string => $record->isOverdue() ? 'danger' : 'primary'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'sent' => 'warning',
                        'in_fabrication' => 'primary',
                        default => 'secondary',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst(str_replace('_', ' ', $state))),
            ])
            ->paginated(false);
    }
}
