<?php

namespace App\Filament\Resources\PatientResource\Widgets;

use App\Models\PatientFile;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms;
use Livewire\WithFileUploads;

class PatientFilesWidget extends BaseWidget
{
    use WithFileUploads;

    public ?\App\Models\Patient $record = null;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                PatientFile::query()->where('patient_id', $this->record?->id)
            )
            ->contentGrid([
                'md' => 2,
                'xl' => 3,
            ])
            ->columns([
                Tables\Columns\Layout\Stack::make([
                    Tables\Columns\ImageColumn::make('file_path')
                        ->height('200px')
                        ->width('100%')
                        ->extraImgAttributes(['class' => 'object-cover rounded-t-xl w-full'])
                        ->square(),
                    Tables\Columns\Layout\Stack::make([
                        Tables\Columns\TextColumn::make('title')
                            ->weight('bold')
                            ->size('lg'),
                        Tables\Columns\TextColumn::make('type')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'xray_panoramic' => 'Panoramic X-Ray',
                                'xray_periapical' => 'Periapical X-Ray',
                                'cbct' => 'CBCT Scan',
                                'intraoral_photo' => 'Intraoral Photo',
                                'consent_form' => 'Consent Form',
                                'other' => 'Other',
                                default => 'Unknown',
                            })
                            ->color(fn (string $state): string => match ($state) {
                                'consent_form' => 'warning',
                                'cbct', 'xray_panoramic', 'xray_periapical' => 'info',
                                default => 'success',
                            }),
                        Tables\Columns\TextColumn::make('tooth_number_fdi')
                            ->formatStateUsing(fn ($state) => $state ? "Tooth {$state}" : '')
                            ->color('gray'),
                        Tables\Columns\TextColumn::make('created_at')
                            ->date()
                            ->color('gray')
                            ->size('sm'),
                    ])->space(1)->extraAttributes(['class' => 'p-4']),
                ])->space(0)->extraAttributes(['class' => 'bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden']),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->model(PatientFile::class)
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['patient_id'] = $this->record->id;
                        $data['doctor_id'] = auth()->id();
                        return $data;
                    })
                    ->form([
                        Forms\Components\FileUpload::make('file_path')
                            ->label('Upload File')
                            ->directory('patient-files')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'application/pdf', 'application/dicom'])
                            ->required()
                            ->columnSpanFull(),
                        Forms\Components\Select::make('type')
                            ->options([
                                'xray_panoramic' => 'Panoramic X-Ray',
                                'xray_periapical' => 'Periapical X-Ray',
                                'cbct' => 'CBCT Scan',
                                'intraoral_photo' => 'Intraoral Photo',
                                'consent_form' => 'Consent Form',
                                'other' => 'Other',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('title')
                            ->required(),
                        Forms\Components\TextInput::make('tooth_number_fdi')
                            ->label('Tooth Number (Optional)')
                            ->numeric(),
                        Forms\Components\Textarea::make('notes')
                            ->columnSpanFull(),
                    ])
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->form([
                        Forms\Components\FileUpload::make('file_path')
                            ->label('Upload File')
                            ->directory('patient-files')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'application/pdf', 'application/dicom'])
                            ->required()
                            ->columnSpanFull(),
                        Forms\Components\Select::make('type')
                            ->options([
                                'xray_panoramic' => 'Panoramic X-Ray',
                                'xray_periapical' => 'Periapical X-Ray',
                                'cbct' => 'CBCT Scan',
                                'intraoral_photo' => 'Intraoral Photo',
                                'consent_form' => 'Consent Form',
                                'other' => 'Other',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('title')
                            ->required(),
                        Forms\Components\TextInput::make('tooth_number_fdi')
                            ->label('Tooth Number (Optional)')
                            ->numeric(),
                        Forms\Components\Textarea::make('notes')
                            ->columnSpanFull(),
                    ]),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
