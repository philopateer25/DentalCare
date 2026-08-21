<?php

namespace App\Filament\Resources\PatientResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class InstallmentPlansRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    protected static ?string $title = 'Installment Plans';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->has('installmentPlan'))
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('Invoice #'),
                Tables\Columns\TextColumn::make('installmentPlan.total_funded_amount')
                    ->label('Total Case Cost')
                    ->money('EGP'),
                Tables\Columns\TextColumn::make('installmentPlan.down_payment')
                    ->label('Down Payment')
                    ->money('EGP'),
                Tables\Columns\TextColumn::make('installmentPlan.number_of_installments')
                    ->label('Installments Count'),
                Tables\Columns\TextColumn::make('installmentPlan.frequency')
                    ->badge(),
                Tables\Columns\TextColumn::make('remaining_balance')
                    ->label('Remaining Balance')
                    ->money('EGP'),
                Tables\Columns\TextColumn::make('installmentPlan.status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'completed' => 'info',
                        'defaulted' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([])
            ->headerActions([])
            ->actions([]);
    }
}
