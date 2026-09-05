<?php

namespace App\Filament\Resources\OperationResource\Forms;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;

class OperationForm
{
    public static function schema(): array
    {
        return [
            Select::make('patient_id')
                ->relationship('patient', 'first_name')
                ->required(),
            Select::make('doctor_id')
                ->relationship('doctor', 'name')
                ->required(),
            Select::make('operatory_id')
                ->relationship('operatory', 'name')
                ->required(),
            DateTimePicker::make('start_time')
                ->required(),
            DateTimePicker::make('end_time')
                ->required(),
            TextInput::make('procedure_name'),
            TextInput::make('tooth_number'),
            Textarea::make('notes'),
        ];
    }
}
