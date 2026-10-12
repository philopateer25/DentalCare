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
        $user = auth()->user();
        return $user ? ($user->hasRole('doctor') || $user->isDoctor()) : false;
    }

    public function table(Table $table): Table
    {
        $practiceId = \Filament\Facades\Filament::getTenant()?->id ?? auth()->user()?->practice_id;

        return $table
            ->query(
                LabOrder::query()
                    ->where('practice_id', $practiceId)
                    ->where('doctor_id', auth()->id())
                    ->whereNotIn('status', ['received_at_clinic', 'seated_delivered', 'cancelled'])
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
                        'impression_sent', 'sent' => 'info',
                        'lab_acknowledged' => 'primary',
                        'in_production', 'in_fabrication', 'try_in_stage' => 'warning',
                        'shipped_by_lab' => 'success',
                        'returned_for_redo' => 'danger',
                        default => 'secondary',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst(str_replace('_', ' ', $state))),
            ])
            ->paginated(false);
    }
}
