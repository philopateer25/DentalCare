<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Practice;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Finance & Treasury';

    protected static ?string $navigationLabel = 'Payments & Cash Register';

    protected static ?string $modelLabel = 'Payment';

    protected static ?string $pluralModelLabel = 'Treasury & Collections';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Payment Collection Details')
                    ->schema([
                        Forms\Components\Select::make('invoice_id')
                            ->label('Target Invoice')
                            ->relationship('invoice', 'invoice_number')
                            ->getOptionLabelFromRecordUsing(fn (Invoice $record) => "{$record->invoice_number} - {$record->patient?->first_name} {$record->patient?->last_name} (Balance Due: \${$record->balance_due})")
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if ($invoice = Invoice::find($state)) {
                                    $set('patient_id', $invoice->patient_id);
                                    $set('practice_id', $invoice->practice_id);
                                    $set('amount', $invoice->balance_due);
                                }
                            }),
                        Forms\Components\Select::make('patient_id')
                            ->label('Patient')
                            ->relationship('patient', 'first_name')
                            ->getOptionLabelFromRecordUsing(fn (Patient $record) => "{$record->first_name} {$record->last_name} ({$record->file_number})")
                            ->searchable()
                            ->required(),
                        Forms\Components\TextInput::make('amount')
                            ->label('Collected Amount ($)')
                            ->numeric()
                            ->prefix('$')
                            ->required(),
                        Forms\Components\Select::make('payment_method')
                            ->label('Payment Method')
                            ->options([
                                'cash' => 'Cash',
                                'card_pos' => 'Credit / Debit Card (POS Machine)',
                                'instapay' => 'InstaPay / Instant Bank Wire',
                                'bank_transfer' => 'Bank Transfer / ACH',
                                'insurance_tpa' => 'Insurance / TPA Direct Reimbursement',
                            ])
                            ->default('cash')
                            ->required(),
                        Forms\Components\TextInput::make('transaction_reference')
                            ->label('POS Auth Code / Transaction Ref / Check #')
                            ->placeholder('e.g. POS-991823, IP-00291'),
                        Forms\Components\DateTimePicker::make('paid_at')
                            ->label('Payment Received Date & Time')
                            ->default(now())
                            ->required(),
                        Forms\Components\Select::make('logged_by_user_id')
                            ->label('Cashier / Staff Member')
                            ->relationship('loggedBy', 'name')
                            ->default(fn () => auth()->id() ?? User::first()?->id)
                            ->required(),
                        Forms\Components\Select::make('practice_id')
                            ->relationship('practice', 'name')
                            ->default(fn () => Practice::firstOrCreate(['name' => 'Main Clinic'])->id)
                            ->required(),
                        Forms\Components\Textarea::make('notes')
                            ->label('Receipt Notes / Co-Pay Details')
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Date & Time')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('patient.first_name')
                    ->label('Patient')
                    ->formatStateUsing(fn (Payment $record) => "{$record->patient?->first_name} {$record->patient?->last_name}")
                    ->description(fn (Payment $record) => "File: {$record->patient?->file_number}")
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('invoice.invoice_number')
                    ->label('Invoice #')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Method')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'cash' => 'success',
                        'card_pos' => 'info',
                        'instapay' => 'warning',
                        'bank_transfer' => 'primary',
                        'insurance_tpa' => 'secondary',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount Collected')
                    ->money('USD')
                    ->weight('bold')
                    ->color('success')
                    ->sortable(),
                Tables\Columns\TextColumn::make('transaction_reference')
                    ->label('Ref / Auth Code')
                    ->searchable()
                    ->placeholder('N/A'),
                Tables\Columns\TextColumn::make('loggedBy.name')
                    ->label('Logged By')
                    ->searchable()
                    ->placeholder('System'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('payment_method')
                    ->options([
                        'cash' => 'Cash',
                        'card_pos' => 'Credit / Debit Card (POS)',
                        'instapay' => 'InstaPay',
                        'bank_transfer' => 'Bank Transfer',
                        'insurance_tpa' => 'Insurance / TPA',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
            'create' => Pages\CreatePayment::route('/create'),
            'edit' => Pages\EditPayment::route('/{record}/edit'),
        ];
    }
}
