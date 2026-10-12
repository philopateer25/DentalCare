<?php

namespace App\Filament\Resources\PatientResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use App\Services\ClinicalPresets;

class ExternalTreatmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'externalTreatments';

    protected static ?string $title = 'Past Treatments Outside Clinic';

    protected static ?string $icon = 'heroicon-o-arrow-top-right-on-square';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('External Treatment Details')
                    ->description('Record prior dental work performed before visiting this clinic (implants, root canals, crowns, surgeries).')
                    ->schema([
                        Forms\Components\Select::make('procedure_title')
                            ->label('Procedure Done Outside Clinic')
                            ->options(ClinicalPresets::externalTreatmentTypes())
                            ->searchable()
                            ->preload()
                            ->createOptionUsing(fn (string $data) => $data)
                            ->required()
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('tooth_number_fdi')
                            ->label('Tooth FDI No.')
                            ->numeric()
                            ->placeholder('e.g., 36, 46')
                            ->helperText('Leave empty if full arch or general procedure'),

                        Forms\Components\TextInput::make('performed_year')
                            ->label('When Was It Performed?')
                            ->placeholder('e.g., 2022 or approx 3 years ago'),

                        Forms\Components\TextInput::make('clinic_or_doctor_name')
                            ->label('External Clinic / Treating Doctor')
                            ->placeholder('e.g., Cairo Dental Clinic / Dr. Sherif')
                            ->columnSpan(2),

                        Forms\Components\Textarea::make('notes')
                            ->label('Clinical Notes / Materials / Patient Comments')
                            ->placeholder('e.g. Straumann implant placed, crown had chipped 6 months ago, root canal had silver point...')
                            ->columnSpanFull(),
                    ])
                    ->columns(3),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('procedure_title')
            ->columns([
                Tables\Columns\TextColumn::make('procedure_title')
                    ->label('External Procedure')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('tooth_number_fdi')
                    ->label('Tooth')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn ($state) => $state ? "Tooth #{$state}" : 'General')
                    ->sortable(),

                Tables\Columns\TextColumn::make('performed_year')
                    ->label('Year / Timing')
                    ->sortable(),

                Tables\Columns\TextColumn::make('clinic_or_doctor_name')
                    ->label('External Clinic / Doctor')
                    ->searchable(),

                Tables\Columns\TextColumn::make('notes')
                    ->label('Notes')
                    ->limit(40)
                    ->toggleable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add External Treatment'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
