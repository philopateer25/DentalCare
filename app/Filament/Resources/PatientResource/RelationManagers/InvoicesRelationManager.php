<?php

namespace App\Filament\Resources\PatientResource\RelationManagers;

use App\Models\Invoice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class InvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('invoice_number')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('invoice_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->money('EGP')
                    ->sortable(),
                Tables\Columns\TextColumn::make('paid_amount')
                    ->money('EGP')
                    ->sortable(),
                Tables\Columns\TextColumn::make('remaining_balance')
                    ->money('EGP')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'partially_paid' => 'warning',
                        'unpaid' => 'danger',
                        'cancelled' => 'gray',
                        default => 'primary',
                    }),
            ])
            ->filters([])
            ->headerActions([])
            ->actions([
                Tables\Actions\Action::make('recordPayment')
                    ->label('Record Payment')
                    ->icon('heroicon-m-banknotes')
                    ->color('success')
                    ->visible(fn (Invoice $record): bool => $record->remaining_balance > 0)
                    ->form([
                        Forms\Components\TextInput::make('amount')
                            ->numeric()
                            ->required()
                            ->label('Payment Amount (EGP)')
                            ->default(fn (Invoice $record): float => (float) $record->remaining_balance)
                            ->rules([
                                fn (Invoice $record): \Closure => function (string $attribute, $value, \Closure $fail) use ($record) {
                                    if ((float) $value > (float) $record->remaining_balance) {
                                        $fail("Payment amount cannot exceed remaining balance of {$record->remaining_balance} EGP.");
                                    }
                                },
                            ]),
                        Forms\Components\Select::make('payment_method')
                            ->options([
                                'cash' => 'Cash',
                                'instapay' => 'InstaPay',
                                'credit_card' => 'Credit Card POS',
                                'insurance_tpa' => 'Insurance TPA',
                                'bank_transfer' => 'Bank Transfer',
                            ])
                            ->default('cash')
                            ->required()
                            ->live(),
                        Forms\Components\TextInput::make('transaction_reference')
                            ->label('Transaction Reference (Required for InstaPay / Card)')
                            ->required(fn (Forms\Get $get): bool => in_array($get('payment_method'), ['instapay', 'credit_card'])),
                        Forms\Components\DateTimePicker::make('paid_at')
                            ->default(now())
                            ->required(),
                        Forms\Components\Textarea::make('notes'),
                    ])
                    ->action(function (Invoice $record, array $data): void {
                        $record->payments()->create([
                            'practice_id' => $record->practice_id,
                            'patient_id' => $record->patient_id,
                            'amount' => $data['amount'],
                            'payment_method' => $data['payment_method'],
                            'transaction_reference' => $data['transaction_reference'] ?? null,
                            'paid_at' => $data['paid_at'],
                            'notes' => $data['notes'] ?? null,
                            'logged_by_user_id' => auth()->id(),
                        ]);

                        $record->refreshFinancials();
                    }),
                Tables\Actions\ViewAction::make(),
            ]);
    }
}
