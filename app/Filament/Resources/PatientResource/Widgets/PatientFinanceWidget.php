<?php

namespace App\Filament\Resources\PatientResource\Widgets;

use App\Models\Invoice;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms;

class PatientFinanceWidget extends BaseWidget
{
    public ?\App\Models\Patient $record = null;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Invoice::query()->where('patient_id', $this->record?->id)
            )
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('issue_date')
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
                        'unpaid' => 'danger',
                        'partially_paid' => 'warning',
                        'paid' => 'success',
                        'overdue' => 'danger',
                        'cancelled' => 'gray',
                        default => 'primary',
                    }),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->model(Invoice::class)
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['patient_id'] = $this->record->id;
                        $data['practice_id'] = $this->record->practice_id ?? \App\Models\Practice::first()->id;
                        $data['invoice_number'] = 'INV-' . strtoupper(uniqid());
                        
                        $total = 0;
                        if (isset($data['items'])) {
                            foreach ($data['items'] as &$item) {
                                $item['total'] = ($item['quantity'] ?? 1) * ($item['unit_price'] ?? 0);
                                $total += $item['total'];
                            }
                        }
                        
                        $data['total_amount'] = $total;
                        $data['remaining_balance'] = $total;
                        $data['paid_amount'] = 0;
                        $data['status'] = 'unpaid';
                        
                        return $data;
                    })
                    ->after(function (Invoice $record, array $data) {
                        if (isset($data['items'])) {
                            foreach ($data['items'] as $item) {
                                $record->items()->create([
                                    'procedure_name' => $item['procedure_name'],
                                    'tooth_number' => $item['tooth_number'] ?? null,
                                    'quantity' => $item['quantity'],
                                    'unit_price' => $item['unit_price'],
                                    'total' => $item['quantity'] * $item['unit_price'],
                                ]);
                            }
                        }
                    })
                    ->form([
                        Forms\Components\Select::make('treatment_plan_id')
                            ->label('Load from Treatment Plan (Optional)')
                            ->options(fn () => \App\Models\TreatmentPlan::where('patient_id', $this->record?->id)->pluck('title', 'id'))
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if (!$state) return;
                                $plan = \App\Models\TreatmentPlan::with('procedures.procedureCode')->find($state);
                                if ($plan) {
                                    $items = [];
                                    foreach ($plan->procedures as $proc) {
                                        $items[] = [
                                            'procedure_name' => $proc->procedureCode->title,
                                            'tooth_number' => $proc->tooth_number_fdi ?? null,
                                            'quantity' => 1,
                                            'unit_price' => $proc->net_amount,
                                        ];
                                    }
                                    $set('items', $items);
                                }
                            })
                            ->columnSpanFull(),
                        Forms\Components\DatePicker::make('issue_date')
                            ->default(now())
                            ->required(),
                        Forms\Components\DatePicker::make('due_date'),
                        Forms\Components\Repeater::make('items')
                            ->schema([
                                Forms\Components\TextInput::make('procedure_name')->required(),
                                Forms\Components\TextInput::make('tooth_number')->label('Tooth (Opt)'),
                                Forms\Components\TextInput::make('quantity')->numeric()->default(1)->required()->live(),
                                Forms\Components\TextInput::make('unit_price')->numeric()->required()->live(),
                            ])
                            ->columns(4)
                            ->columnSpanFull(),
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('record_payment')
                    ->label('Record Payment')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->form([
                        Forms\Components\TextInput::make('amount')
                            ->numeric()
                            ->required()
                            ->default(fn (Invoice $record) => $record->remaining_balance),
                        Forms\Components\Select::make('payment_method')
                            ->options([
                                'cash' => 'Cash',
                                'instapay' => 'InstaPay',
                                'card_pos' => 'Card / POS',
                                'bank_transfer' => 'Bank Transfer',
                            ])
                            ->default('cash')
                            ->required(),
                        Forms\Components\TextInput::make('transaction_reference')
                            ->label('Transaction Ref / Receipt No'),
                    ])
                    ->action(function (Invoice $record, array $data) {
                        $record->payments()->create([
                            'practice_id' => $record->practice_id,
                            'patient_id' => $record->patient_id,
                            'amount' => $data['amount'],
                            'payment_method' => $data['payment_method'],
                            'transaction_reference' => $data['transaction_reference'],
                            'paid_at' => now(),
                        ]);

                        $record->paid_amount += $data['amount'];
                        $record->remaining_balance -= $data['amount'];
                        
                        if ($record->remaining_balance <= 0) {
                            $record->status = 'paid';
                        } else {
                            $record->status = 'partially_paid';
                        }
                        
                        $record->save();
                    })
                    ->visible(fn (Invoice $record) => $record->remaining_balance > 0),
            ]);
    }
}
