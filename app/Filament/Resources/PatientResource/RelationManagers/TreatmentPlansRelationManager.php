<?php

namespace App\Filament\Resources\PatientResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Get;
use Filament\Forms\Set;
use App\Models\ProcedureCode;

class TreatmentPlansRelationManager extends RelationManager
{
    protected static string $relationship = 'treatmentPlans';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Plan Details')->schema([
                    Forms\Components\TextInput::make('title')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\Select::make('doctor_id')
                        ->relationship('doctor', 'name')
                        ->required()
                        ->label('Assigned Doctor'),
                    Forms\Components\Select::make('status')
                        ->options([
                            'draft' => 'Draft / Proposed',
                            'approved' => 'Approved',
                            'in_progress' => 'In Progress',
                            'completed' => 'Completed',
                            'cancelled' => 'Cancelled',
                        ])
                        ->default('draft')
                        ->required(),
                    Forms\Components\Placeholder::make('lab_orders')
                        ->label('Associated Lab Orders')
                        ->content(function ($record) {
                            if (!$record || !$record->labOrders || $record->labOrders->isEmpty()) {
                                return 'No lab orders associated with this treatment plan.';
                            }
                            
                            $html = '<ul class="list-disc pl-5">';
                            foreach ($record->labOrders as $order) {
                                $labName = $order->dentalLab->name ?? 'Unknown Lab';
                                $status = strtoupper($order->status);
                                $tooth = $order->tooth_number_fdi ? "Tooth {$order->tooth_number_fdi}" : 'General';
                                $date = $order->expected_delivery_at ? $order->expected_delivery_at->format('M d, Y') : 'TBD';
                                
                                $html .= "<li><strong>{$labName}</strong> - {$tooth} ({$order->material} / {$order->shade})<br>Status: <strong>{$status}</strong> | Expected: {$date}</li>";
                            }
                            $html .= '</ul>';
                            
                            return new \Illuminate\Support\HtmlString($html);
                        })
                        ->visible(fn ($record) => $record !== null),
                    Forms\Components\Textarea::make('notes')->columnSpanFull(),
                ])->columns(2),
                
                Forms\Components\Repeater::make('phases')
                    ->relationship()
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->label('Phase Name (e.g., Phase 1 - Surgery)'),
                        Forms\Components\TextInput::make('sequence')
                            ->numeric()
                            ->default(1)
                            ->required(),
                            
                        Forms\Components\Repeater::make('procedures')
                            ->relationship()
                            ->schema([
                                Forms\Components\Select::make('procedure_code_id')
                                    ->relationship('procedureCode', 'title')
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                        if (!$state) return;
                                        $code = ProcedureCode::find($state);
                                        if ($code) {
                                            $set('fee', $code->standard_fee);
                                            $discount = (float) $get('discount') ?? 0;
                                            $set('net_amount', max(0, $code->standard_fee - $discount));
                                        }
                                    }),
                                Forms\Components\TextInput::make('tooth_number_fdi')
                                    ->numeric()
                                    ->label('Tooth No.')
                                    ->nullable(),
                                Forms\Components\Select::make('surface')
                                    ->options([
                                        'M' => 'Mesial (M)',
                                        'O' => 'Occlusal (O)',
                                        'D' => 'Distal (D)',
                                        'B' => 'Buccal (B)',
                                        'L' => 'Lingual (L)',
                                        'I' => 'Incisal (I)',
                                        'ROOT' => 'Root',
                                        'WHOLE' => 'Whole Tooth',
                                    ])
                                    ->default('WHOLE'),
                                Forms\Components\Select::make('status')
                                    ->options([
                                        'planned' => 'Planned',
                                        'in_progress' => 'In Progress',
                                        'completed' => 'Completed',
                                        'cancelled' => 'Cancelled',
                                    ])
                                    ->default('planned')
                                    ->required(),
                                Forms\Components\TextInput::make('fee')
                                    ->numeric()
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                        $fee = (float) $state ?? 0;
                                        $discount = (float) $get('discount') ?? 0;
                                        $set('net_amount', max(0, $fee - $discount));
                                    }),
                                Forms\Components\TextInput::make('discount')
                                    ->numeric()
                                    ->default(0)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                        $discount = (float) $state ?? 0;
                                        $fee = (float) $get('fee') ?? 0;
                                        $set('net_amount', max(0, $fee - $discount));
                                    }),
                                Forms\Components\TextInput::make('net_amount')
                                    ->numeric()
                                    ->required()
                                    ->readOnly(),
                                Forms\Components\Textarea::make('notes')->columnSpanFull(),
                            ])
                            ->columns(4)
                            ->columnSpanFull()
                            ->itemLabel(fn (?array $state): ?string => ($state['tooth_number_fdi'] ?? null) ? "Tooth {$state['tooth_number_fdi']}" : null)
                            ->addActionLabel('Add Procedure')
                            ->collapsible(),
                    ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->itemLabel(fn (?array $state): ?string => $state['name'] ?? null)
                    ->addActionLabel('Add Phase')
                    ->collapsible(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'approved' => 'info',
                        'in_progress' => 'primary',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('total_amount')
                    ->money('EGP')
                    ->sortable(),
                Tables\Columns\TextColumn::make('net_amount')
                    ->money('EGP')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['total_amount'] = 0;
                        $data['discount_amount'] = 0;
                        $data['net_amount'] = 0;
                        return $data;
                    })
                    ->after(function (\Illuminate\Database\Eloquent\Model $record) {
                        $record->recalculateTotals();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->after(function (\Illuminate\Database\Eloquent\Model $record) {
                        $record->recalculateTotals();
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
