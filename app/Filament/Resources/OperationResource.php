<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OperationResource\Pages;
use App\Filament\Resources\OperationResource\RelationManagers;
use App\Models\Operation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

use App\Filament\Resources\OperationResource\Forms\OperationForm;
use App\Filament\Resources\OperationResource\Tables\OperationTable;

class OperationResource extends Resource
{
    protected static ?string $model = \App\Models\Appointment::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    
    protected static ?string $modelLabel = 'Operation';
    protected static ?string $pluralModelLabel = 'Operations Log';
    protected static ?string $slug = 'operations-log';

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->where('status', 'completed');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema(OperationForm::schema());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns(OperationTable::columns())
            ->filters(OperationTable::filters())
            ->actions(OperationTable::actions())
            ->defaultSort('start_time', 'desc')
            ->bulkActions([
                \Filament\Tables\Actions\BulkActionGroup::make([
                    // 
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOperations::route('/'),
            'create' => Pages\CreateOperation::route('/create'),
            'edit' => Pages\EditOperation::route('/{record}/edit'),
        ];
    }
}
