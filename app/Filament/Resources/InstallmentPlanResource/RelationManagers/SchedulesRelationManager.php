<?php

namespace App\Filament\Resources\InstallmentPlanResource\RelationManagers;

use App\Models\InstallmentSchedule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class SchedulesRelationManager extends RelationManager
{
    protected static string $relationship = 'schedules';

    protected static ?string $recordTitleAttribute = 'schedule_number';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('schedule_number')
                    ->label('Installment #')
                    ->numeric()
                    ->required(),
                Forms\Components\DatePicker::make('due_date')
                    ->label('Due Date')
                    ->required(),
                Forms\Components\TextInput::make('amount')
                    ->label('Scheduled Amount ($)')
                    ->numeric()
                    ->prefix('$')
                    ->required(),
                Forms\Components\TextInput::make('paid_amount')
                    ->label('Paid Amount ($)')
                    ->numeric()
                    ->prefix('$')
                    ->default(0.00),
                Forms\Components\Select::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'partially_paid' => 'Partially Paid',
                        'paid' => 'Paid in Full',
                        'overdue' => 'Overdue',
                    ])
                    ->default('pending')
                    ->required(),
                Forms\Components\DatePicker::make('payment_date')
                    ->label('Settlement Date'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('schedule_number')
                    ->label('Installment #')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => "Payment #{$state}")
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('due_date')
                    ->label('Due Date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount Due')
                    ->money('USD')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('paid_amount')
                    ->label('Amount Paid')
                    ->money('USD')
                    ->color('success'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'partially_paid' => 'warning',
                        'pending' => 'info',
                        'overdue' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('payment_date')
                    ->label('Paid On')
                    ->date()
                    ->placeholder('Pending'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
