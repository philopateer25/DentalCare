<?php

namespace App\Filament\Resources\PatientResource\Widgets;

use App\Models\LabOrder;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms;

class PatientLabOrdersWidget extends BaseWidget
{
    public ?\App\Models\Patient $record = null;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                LabOrder::query()->where('patient_id', $this->record?->id)
            )
            ->columns([
                Tables\Columns\TextColumn::make('dentalLab.name')
                    ->label('Lab')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('tooth_number_fdi')
                    ->label('Tooth')
                    ->sortable(),
                Tables\Columns\TextColumn::make('material')
                    ->searchable(),
                Tables\Columns\TextColumn::make('shade')
                    ->searchable(),
                Tables\Columns\TextColumn::make('expected_delivery_at')
                    ->label('Expected')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'sent' => 'warning',
                        'in_progress' => 'primary',
                        'delivered' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->model(LabOrder::class)
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['patient_id'] = $this->record->id;
                        $data['practice_id'] = $this->record->practice_id ?? \App\Models\Practice::first()->id;
                        $data['doctor_id'] = auth()->id();
                        $data['sent_at'] = now();
                        $data['status'] = 'sent';
                        
                        return $data;
                    })
                    ->after(function (LabOrder $record, array $data) {
                        if (isset($data['cost']) && $data['cost'] > 0) {
                            $invoice = \App\Models\Invoice::create([
                                'practice_id' => $record->practice_id,
                                'patient_id' => $record->patient_id,
                                'treatment_plan_id' => $record->treatment_plan_id,
                                'invoice_number' => 'INV-' . strtoupper(uniqid()),
                                'issue_date' => now(),
                                'due_date' => $record->expected_delivery_at ?? now()->addDays(7),
                                'total_amount' => $data['cost'],
                                'remaining_balance' => $data['cost'],
                                'paid_amount' => 0,
                                'status' => 'unpaid',
                            ]);

                            $invoice->items()->create([
                                'procedure_name' => 'Lab Order: ' . $record->material . ' (' . $record->shade . ')',
                                'tooth_number' => $record->tooth_number_fdi,
                                'quantity' => 1,
                                'unit_price' => $data['cost'],
                                'total' => $data['cost'],
                            ]);
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
                                if ($plan && $plan->procedures->count() > 0) {
                                    $proc = $plan->procedures->first();
                                    if ($proc->tooth_number_fdi) {
                                        $set('tooth_number_fdi', $proc->tooth_number_fdi);
                                    }
                                }
                            })
                            ->columnSpanFull(),
                        Forms\Components\Select::make('dental_lab_id')
                            ->label('Dental Lab')
                            ->options(fn () => \App\Models\DentalLab::pluck('name', 'id'))
                            ->required(),
                        Forms\Components\TextInput::make('tooth_number_fdi')
                            ->label('Tooth Number')
                            ->numeric()
                            ->required(),
                        Forms\Components\TextInput::make('material')
                            ->required(),
                        Forms\Components\TextInput::make('shade')
                            ->required(),
                        Forms\Components\Textarea::make('instructions')
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('cost')
                            ->numeric()
                            ->prefix('EGP'),
                        Forms\Components\DatePicker::make('expected_delivery_at')
                            ->label('Expected Delivery Date')
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->form([
                        Forms\Components\Select::make('treatment_plan_id')
                            ->label('Load from Treatment Plan (Optional)')
                            ->options(fn () => \App\Models\TreatmentPlan::where('patient_id', $this->record?->id)->pluck('title', 'id'))
                            ->columnSpanFull(),
                        Forms\Components\Select::make('dental_lab_id')
                            ->label('Dental Lab')
                            ->options(fn () => \App\Models\DentalLab::pluck('name', 'id'))
                            ->required(),
                        Forms\Components\TextInput::make('tooth_number_fdi')
                            ->label('Tooth Number')
                            ->numeric()
                            ->required(),
                        Forms\Components\TextInput::make('material')
                            ->required(),
                        Forms\Components\TextInput::make('shade')
                            ->required(),
                        Forms\Components\Textarea::make('instructions')
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('cost')
                            ->numeric()
                            ->prefix('EGP'),
                        Forms\Components\DatePicker::make('expected_delivery_at')
                            ->label('Expected Delivery Date')
                            ->required(),
                    ]),
                Tables\Actions\Action::make('mark_delivered')
                    ->label('Mark Delivered')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (LabOrder $record) {
                        $record->update([
                            'status' => 'delivered',
                            'delivered_at' => now(),
                        ]);
                    })
                    ->visible(fn (LabOrder $record) => $record->status !== 'delivered'),
            ]);
    }
}
