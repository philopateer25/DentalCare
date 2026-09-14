<?php

namespace App\Filament\Widgets;

use App\Models\Invoice;
use App\Services\CurrencyHelper;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class ActionRequiredWidget extends BaseWidget
{
    protected static ?int $sort = 4;
    protected int | string | array $columnSpan = 1;
    protected static ?string $heading = 'Action Required: Unpaid Invoices';

    public static function canView(): bool
    {
        $user = auth()->user();
        return !($user->isDoctor() || $user->hasRole('doctor'));
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Invoice::query()
                    ->where('practice_id', \Filament\Facades\Filament::getTenant()?->id)
                    ->whereIn('status', ['unpaid', 'partially_paid', 'overdue'])
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('Invoice')
                    ->searchable(),
                Tables\Columns\TextColumn::make('patient.full_name')
                    ->label('Patient'),
                Tables\Columns\TextColumn::make('remaining_balance')
                    ->label('Amount Due')
                    ->money(fn () => CurrencyHelper::currentCurrency())
                    ->color('danger')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'unpaid' => 'danger',
                        'partially_paid' => 'warning',
                        'overdue' => 'danger',
                        default => 'primary',
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('view_invoice')
                    ->label('Collect')
                    ->icon('heroicon-o-banknotes')
                    ->url(fn (\App\Models\Invoice $record): string => \App\Filament\Resources\PatientResource::getUrl('view', ['record' => $record->patient_id]) . '?activeRelationManager=4')
                    ->openUrlInNewTab(),
            ])
            ->paginated(false);
    }
}
