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
        $patientId = $this->record?->id;

        return $table
            ->query(
                Invoice::query()->where('patient_id', $patientId)
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
                        $data['practice_id'] = $this->record->practice_id ?? \Filament\Facades\Filament::getTenant()->id;
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
                                    'invoiceable_type' => $item['invoiceable_type'] ?? null,
                                    'invoiceable_id' => $item['invoiceable_id'] ?? null,
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
                            ->label('Add from Treatment Plan')
                            ->options(function () use ($patientId) {
                                return \App\Models\TreatmentPlan::where('patient_id', $patientId)
                                    ->pluck('title', 'id')
                                    ->toArray();
                            })
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                if (!$state) return;
                                $plan = \App\Models\TreatmentPlan::with('procedures.procedureCode')->find($state);
                                if ($plan) {
                                    $items = $get('items') ?? [];
                                    foreach ($plan->procedures as $proc) {
                                        $items[] = [
                                            'invoiceable_type' => \App\Models\TreatmentProcedure::class,
                                            'invoiceable_id' => $proc->id,
                                            'procedure_name' => "Treatment: " . ($proc->procedureCode->title ?? 'Procedure'),
                                            'tooth_number' => $proc->tooth_number_fdi ?? null,
                                            'quantity' => 1,
                                            'unit_price' => $proc->net_amount,
                                        ];
                                    }
                                    $set('items', $items);
                                    $set('treatment_plan_id', null);
                                }
                            }),
                            
                        Forms\Components\Select::make('appointment_id')
                            ->label('Add Consultation Fee')
                            ->options(function () use ($patientId) {
                                $appointments = \App\Models\Appointment::where('patient_id', $patientId)->get();
                                $options = [];
                                foreach ($appointments as $a) {
                                    $options[$a->id] = "Appt #{$a->id}: " . ($a->chief_complaint ?: 'General Consultation');
                                }
                                return $options;
                            })
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                if (!$state) return;
                                $appt = \App\Models\Appointment::find($state);
                                if ($appt) {
                                    $items = $get('items') ?? [];
                                    $items[] = [
                                        'invoiceable_type' => \App\Models\Appointment::class,
                                        'invoiceable_id' => $appt->id,
                                        'procedure_name' => "Consultation Fee - " . ($appt->chief_complaint ?? 'General'),
                                        'tooth_number' => null,
                                        'quantity' => 1,
                                        'unit_price' => $appt->consultation_fee ?? 0,
                                    ];
                                    $set('items', $items);
                                    $set('appointment_id', null);
                                }
                            }),
                            
                        Forms\Components\Select::make('lab_order_id')
                            ->label('Add Lab Order Fee')
                            ->options(function () use ($patientId) {
                                $orders = \App\Models\LabOrder::where('patient_id', $patientId)->get();
                                $options = [];
                                foreach ($orders as $o) {
                                    $options[$o->id] = "Lab Order #{$o->id} - {$o->material}";
                                }
                                return $options;
                            })
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                if (!$state) return;
                                $order = \App\Models\LabOrder::find($state);
                                if ($order) {
                                    $items = $get('items') ?? [];
                                    $items[] = [
                                        'invoiceable_type' => \App\Models\LabOrder::class,
                                        'invoiceable_id' => $order->id,
                                        'procedure_name' => "Lab Fee: {$order->material} ({$order->shade})",
                                        'tooth_number' => $order->tooth_number_fdi,
                                        'quantity' => 1,
                                        'unit_price' => $order->cost ?? 0,
                                    ];
                                    $set('items', $items);
                                    $set('lab_order_id', null);
                                }
                            }),

                        Forms\Components\DatePicker::make('issue_date')
                            ->default(now())
                            ->required(),
                        Forms\Components\DatePicker::make('due_date'),
                        Forms\Components\Repeater::make('items')
                            ->schema([
                                Forms\Components\Hidden::make('invoiceable_type'),
                                Forms\Components\Hidden::make('invoiceable_id'),
                                Forms\Components\TextInput::make('procedure_name')->required()->columnSpan(2),
                                Forms\Components\TextInput::make('tooth_number')->label('Tooth (Opt)'),
                                Forms\Components\TextInput::make('quantity')->numeric()->default(1)->required()->live(),
                                Forms\Components\TextInput::make('unit_price')->numeric()->required()->live(),
                            ])
                            ->columns(5)
                            ->columnSpanFull(),
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->form([
                        Forms\Components\Repeater::make('items')
                            ->relationship()
                            ->schema([
                                Forms\Components\TextInput::make('procedure_name')->columnSpan(2)->label('Item Name'),
                                Forms\Components\TextInput::make('invoiceable_type')->label('Source Type')
                                    ->formatStateUsing(fn ($state) => match($state) {
                                        \App\Models\TreatmentProcedure::class => 'Treatment',
                                        \App\Models\Appointment::class => 'Appointment',
                                        \App\Models\LabOrder::class => 'Lab Order',
                                        default => 'Manual Entry',
                                    }),
                                Forms\Components\TextInput::make('quantity')->numeric(),
                                Forms\Components\TextInput::make('unit_price')->numeric(),
                            ])
                            ->columns(5)
                            ->columnSpanFull()
                            ->disableItemCreation()
                            ->disableItemDeletion()
                            ->disableItemMovement(),
                    ]),
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
