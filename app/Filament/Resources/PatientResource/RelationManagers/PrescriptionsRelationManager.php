<?php

namespace App\Filament\Resources\PatientResource\RelationManagers;

use App\Filament\Forms\CreatableSelect;
use App\Models\Prescription;
use App\Services\WhatsAppLink;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PrescriptionsRelationManager extends RelationManager
{
    /** Phase 3: clinical tabs appear once the clinic reaches rollout stage 3. */
    public static function canViewForRecord(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): bool
    {
        return \App\Services\FeatureManager::stageAllows('clinical') && parent::canViewForRecord($ownerRecord, $pageClass);
    }

    protected static string $relationship = 'prescriptions';

    /**
     * Relation managers on a View page are read-only by default in Filament, which hid the
     * Create button. Doctors and admins can write prescriptions.
     */
    public function isReadOnly(): bool
    {
        return ! (auth()->user()?->hasAnyRole(['doctor', 'clinic_admin', 'super_admin', 'developer']) ?? false);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // Phase 5: the clinic's own lists, most used first; anything typed is remembered.
                CreatableSelect::make('medication_name', 'medication')
                    ->label(__('Medication'))
                    ->required()
                    ->columnSpanFull(),

                CreatableSelect::make('dosage', 'rx_dosage')
                    ->label(__('Dosage'))
                    ->required(),

                CreatableSelect::make('frequency', 'rx_frequency')
                    ->label(__('Frequency'))
                    ->required(),

                CreatableSelect::make('duration', 'rx_duration')
                    ->label(__('Duration'))
                    ->required(),

                Forms\Components\DatePicker::make('date_prescribed')
                    ->default(now())
                    ->required(),

                Forms\Components\Select::make('status')
                    ->options([
                        'active' => 'Active',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ])
                    ->default('active')
                    ->required(),

                Forms\Components\Textarea::make('instructions')
                    ->maxLength(65535)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('medication_name')
            ->columns([
                Tables\Columns\TextColumn::make('medication_name')
                    ->weight('bold')
                    ->searchable(),
                Tables\Columns\TextColumn::make('dosage'),
                Tables\Columns\TextColumn::make('frequency'),
                Tables\Columns\TextColumn::make('duration'),
                Tables\Columns\TextColumn::make('date_prescribed')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'completed' => 'gray',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('doctor.name')
                    ->label('Prescribed By')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['doctor_id'] = auth()->id();

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('print')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(fn (Prescription $record): string => route('prescriptions.print', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('whatsapp')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->color('success')
                    ->label('WhatsApp')
                    ->visible(fn () => Filament::getTenant()?->hasFeature('whatsapp') ?? false)
                    // Opens WhatsApp with the prescription written out; staff press send.
                    ->url(fn (Prescription $record) => WhatsAppLink::forPrescription($record))
                    ->openUrlInNewTab(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
