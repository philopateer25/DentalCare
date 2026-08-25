<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvoiceResource\Pages;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use App\Jobs\SendWhatsAppMessage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Financial Ledger';

    public static function updateTotals(Forms\Get $get, Forms\Set $set): void
    {
        $items = $get('items') ?? [];
        $subtotal = 0;

        foreach ($items as $item) {
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $quantity = (int) ($item['quantity'] ?? 1);
            $subtotal += ($unitPrice * $quantity);
        }

        $discount = (float) ($get('discount') ?? 0);
        $tax = (float) ($get('tax') ?? 0);
        $totalAmount = max(0, round($subtotal - $discount + $tax, 2));

        $paidAmount = (float) ($get('paid_amount') ?? 0);
        $remainingBalance = max(0, round($totalAmount - $paidAmount, 2));

        $status = 'unpaid';
        if ($totalAmount > 0 && $paidAmount >= $totalAmount) {
            $status = 'paid';
        } elseif ($paidAmount > 0) {
            $status = 'partially_paid';
        }

        $set('subtotal', $subtotal);
        $set('total_amount', $totalAmount);
        $set('remaining_balance', $remainingBalance);
        $set('status', $status);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Invoice Header')
                    ->schema([
                        Forms\Components\TextInput::make('invoice_number')
                            ->default(fn () => 'INV-' . strtoupper(Str::random(6)))
                            ->readOnly()
                            ->required(),
                        Forms\Components\Select::make('patient_id')
                            ->label('Patient')
                            ->options(Patient::query()->pluck('full_name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('doctor_id')
                            ->label('Attending Doctor / Specialist')
                            ->options(User::query()->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->nullable(),
                        Forms\Components\DatePicker::make('invoice_date')
                            ->default(now())
                            ->required(),
                    ])
                    ->columns(4),

                Forms\Components\Section::make('Line Items & Procedure Breakdown')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship()
                            ->schema([
                                Forms\Components\TextInput::make('procedure_name')
                                    ->required()
                                    ->columnSpan(3),
                                Forms\Components\TextInput::make('tooth_number')
                                    ->placeholder('e.g. 16, 21')
                                    ->columnSpan(1),
                                Forms\Components\TextInput::make('unit_price')
                                    ->numeric()
                                    ->required()
                                    ->prefix('EGP')
                                    ->columnSpan(2)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set) {
                                        $unitPrice = (float) ($get('unit_price') ?? 0);
                                        $qty = (int) ($get('quantity') ?? 1);
                                        $total = round($unitPrice * $qty, 2);
                                        $set('total', $total);

                                        $commPct = (float) ($get('doctor_commission_percentage') ?? 0);
                                        $commAmt = round(($total * $commPct) / 100, 2);
                                        $set('doctor_commission_amount', $commAmt);
                                    }),
                                Forms\Components\TextInput::make('quantity')
                                    ->numeric()
                                    ->default(1)
                                    ->required()
                                    ->columnSpan(1)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set) {
                                        $unitPrice = (float) ($get('unit_price') ?? 0);
                                        $qty = (int) ($get('quantity') ?? 1);
                                        $total = round($unitPrice * $qty, 2);
                                        $set('total', $total);

                                        $commPct = (float) ($get('doctor_commission_percentage') ?? 0);
                                        $commAmt = round(($total * $commPct) / 100, 2);
                                        $set('doctor_commission_amount', $commAmt);
                                    }),
                                Forms\Components\TextInput::make('total')
                                    ->numeric()
                                    ->readOnly()
                                    ->dehydrated()
                                    ->prefix('EGP')
                                    ->columnSpan(2),
                                Forms\Components\TextInput::make('doctor_commission_percentage')
                                    ->label('Doc Comm %')
                                    ->numeric()
                                    ->default(0)
                                    ->columnSpan(1.5)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set) {
                                        $total = (float) ($get('total') ?? 0);
                                        $commPct = (float) ($get('doctor_commission_percentage') ?? 0);
                                        $commAmt = round(($total * $commPct) / 100, 2);
                                        $set('doctor_commission_amount', $commAmt);
                                    }),
                                Forms\Components\TextInput::make('doctor_commission_amount')
                                    ->label('Doc Comm (EGP)')
                                    ->numeric()
                                    ->readOnly()
                                    ->dehydrated()
                                    ->columnSpan(1.5),
                            ])
                            ->columns(12)
                            ->live()
                            ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => static::updateTotals($get, $set)),
                    ]),

                Forms\Components\Section::make('Totals & Financial Status')
                    ->schema([
                        Forms\Components\TextInput::make('subtotal')
                            ->numeric()
                            ->readOnly()
                            ->dehydrated()
                            ->prefix('EGP'),
                        Forms\Components\TextInput::make('discount')
                            ->numeric()
                            ->default(0)
                            ->prefix('EGP')
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => static::updateTotals($get, $set)),
                        Forms\Components\TextInput::make('tax')
                            ->numeric()
                            ->default(0)
                            ->prefix('EGP')
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => static::updateTotals($get, $set)),
                        Forms\Components\TextInput::make('total_amount')
                            ->numeric()
                            ->readOnly()
                            ->dehydrated()
                            ->prefix('EGP'),
                        Forms\Components\TextInput::make('paid_amount')
                            ->numeric()
                            ->disabled()
                            ->default(0)
                            ->prefix('EGP'),
                        Forms\Components\TextInput::make('remaining_balance')
                            ->numeric()
                            ->readOnly()
                            ->dehydrated()
                            ->prefix('EGP'),
                        Forms\Components\Select::make('status')
                            ->options([
                                'unpaid' => 'Unpaid',
                                'partially_paid' => 'Partially Paid',
                                'paid' => 'Paid',
                                'cancelled' => 'Cancelled',
                            ])
                            ->default('unpaid')
                            ->disabled()
                            ->dehydrated(),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('patient.full_name')
                    ->label('Patient')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('doctor.name')
                    ->label('Doctor')
                    ->searchable(),
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
            ->actions([
                Tables\Actions\Action::make('sendViaWhatsApp')
                    ->label('Send via WhatsApp')
                    ->icon('heroicon-o-document-text')
                    ->color('info')
                    ->action(function (Invoice $record) {
                        // Generate PDF
                        $pdf = Pdf::loadView('pdf.invoice', ['invoice' => $record]);
                        $fileName = "Invoice_{$record->invoice_number}.pdf";
                        $path = "public/invoices/{$fileName}";
                        
                        // Save temporarily to public disk
                        Storage::disk('local')->put($path, $pdf->output());
                        
                        // Get public URL (assuming local storage is linked, or use asset helper)
                        $url = asset("storage/invoices/{$fileName}");
                        
                        // Dispatch job
                        if ($record->patient && $record->patient->phone) {
                            $caption = "Hello {$record->patient->full_name}, here is your invoice from today's visit.";
                            SendWhatsAppMessage::dispatch($record->patient->phone, null, $url, $fileName, $caption);
                            Notification::make()->title('Invoice sent via WhatsApp!')->success()->send();
                        } else {
                            Notification::make()->title('Patient has no phone number!')->danger()->send();
                        }
                    }),
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
                        $payment = $record->payments()->create([
                            'practice_id' => $record->practice_id,
                            'patient_id' => $record->patient_id,
                            'amount' => $data['amount'],
                            'payment_method' => $data['payment_method'],
                            'transaction_reference' => $data['transaction_reference'] ?? null,
                            'paid_at' => $data['paid_at'],
                            'notes' => $data['notes'] ?? null,
                            'logged_by_user_id' => auth()->id(),
                        ]);

                        // Record Doctor Commission if doctor is assigned
                        if ($record->doctor_id) {
                            foreach ($record->items as $item) {
                                if ($item->doctor_commission_percentage > 0) {
                                    $itemComm = round(($data['amount'] * ($item->total / max(1, $record->total_amount)) * $item->doctor_commission_percentage) / 100, 2);
                                    \App\Models\DoctorCommission::create([
                                        'doctor_id' => $record->doctor_id,
                                        'payment_id' => $payment->id,
                                        'treatment_procedure_id' => $item->treatment_procedure_id,
                                        'gross_amount' => $item->total,
                                        'commission_percentage' => $item->doctor_commission_percentage,
                                        'commission_amount' => $itemComm,
                                        'status' => 'accrued',
                                    ]);
                                }
                            }
                        }

                        $record->refreshFinancials();

                        Notification::make()
                            ->title('Payment Captured Successfully')
                            ->success()
                            ->send();
                    }),
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
            'index' => Pages\ListInvoices::route('/'),
            'create' => Pages\CreateInvoice::route('/create'),
            'edit' => Pages\EditInvoice::route('/{record}/edit'),
        ];
    }
}
