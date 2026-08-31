<?php

namespace App\Filament\Resources\InvoiceResource\RelationManagers;

use App\Models\Payment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $recordTitleAttribute = 'transaction_reference';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('amount')
                    ->label('Payment Amount')
                    ->numeric()
                    ->prefix('$')
                    ->required(),
                Forms\Components\Select::make('payment_method')
                    ->label('Payment Method')
                    ->options([
                        'cash' => 'Cash',
                        'card_pos' => 'Credit / Debit Card (POS)',
                        'instapay' => 'InstaPay / Instant Bank Wire',
                        'bank_transfer' => 'Bank Transfer / ACH',
                        'insurance_tpa' => 'Insurance / TPA Direct Reimbursement',
                    ])
                    ->default('cash')
                    ->required(),
                Forms\Components\TextInput::make('transaction_reference')
                    ->label('Reference / POS Auth Code / Receipt #')
                    ->placeholder('e.g. POS-88491, IP-339219'),
                Forms\Components\DateTimePicker::make('paid_at')
                    ->label('Payment Date & Time')
                    ->default(now())
                    ->required(),
                Forms\Components\Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Payment Date')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount Received')
                    ->money('USD')
                    ->weight('bold')
                    ->color('success'),
                Tables\Columns\TextColumn::make('payment_method')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('transaction_reference')
                    ->label('Ref / Auth Code')
                    ->placeholder('N/A'),
                Tables\Columns\TextColumn::make('loggedBy.name')
                    ->label('Cashier / Staff')
                    ->placeholder('System'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data, $livewire): array {
                        $data['practice_id'] = $livewire->ownerRecord->practice_id;
                        $data['patient_id'] = $livewire->ownerRecord->patient_id;
                        $data['logged_by_user_id'] = auth()->id();

                        return $data;
                    })
                    ->after(fn ($livewire) => $livewire->ownerRecord->recalculateTotals()),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->after(fn ($livewire) => $livewire->ownerRecord->recalculateTotals()),
                Tables\Actions\DeleteAction::make()
                    ->after(fn ($livewire) => $livewire->ownerRecord->recalculateTotals()),
            ]);
    }
}
