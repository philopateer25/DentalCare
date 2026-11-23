<?php

namespace App\Filament\Resources\PatientResource\Pages;

use App\Filament\Resources\PatientResource;
use App\Models\Patient;
use App\Services\PaperRecords\PatientImport;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Table;

class ListPatients extends ListRecords
{
    protected static string $resource = PatientResource::class;

    public function getTitle(): string
    {
        return __('Patients Directory');
    }

    public function getSubheading(): ?string
    {
        $count = static::getResource()::getEloquentQuery()->count();

        return __('Manage :count registered patient files, appointments and billing history', ['count' => number_format($count)]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label(__('New patient'))
                ->icon('heroicon-o-user-plus'),
            Actions\Action::make('fromPaperCard')
                ->label(__('From paper card'))
                ->icon('heroicon-o-camera')
                ->color('gray')
                ->modalHeading(__('Patient with a paper card'))
                ->modalDescription(__('Name and phone only — then photograph the card with your phone.'))
                ->modalWidth('md')
                ->fillForm(fn () => $this->paperCardPrefill())
                ->form($this->paperCardForm())
                ->modalSubmitActionLabel(__('Create and scan card'))
                ->action(fn (array $data, $action) => $this->createFromPaperCard($data, $action)),
        ];
    }

    /** "Search → not found → create from paper card", pre-filled with the search text. */
    public function table(Table $table): Table
    {
        return parent::table($table)
            ->emptyStateActions([
                TableAction::make('fromPaperCardEmpty')
                    ->label(__('Create from paper card'))
                    ->icon('heroicon-o-camera')
                    ->modalHeading(__('Patient with a paper card'))
                    ->modalWidth('md')
                    ->fillForm(fn () => $this->paperCardPrefill())
                    ->form($this->paperCardForm())
                    ->modalSubmitActionLabel(__('Create and scan card'))
                    ->action(fn (array $data, $action) => $this->createFromPaperCard($data, $action)),
            ]);
    }

    /** @return array<int, Component> */
    private function paperCardForm(): array
    {
        return [
            TextInput::make('name')->label(__('Full name'))->required()->maxLength(120)->autofocus(),
            TextInput::make('phone')->label(__('Phone'))->tel()->required()->maxLength(20),
        ];
    }

    /** @return array{name?: string, phone?: string} */
    private function paperCardPrefill(): array
    {
        $search = trim((string) $this->getTableSearch());

        if ($search === '') {
            return [];
        }

        return preg_match('/^[\d\s+\-٠-٩]+$/u', $search) ? ['phone' => $search] : ['name' => $search];
    }

    private function createFromPaperCard(array $data, Actions\Action|TableAction $action): void
    {
        $practiceId = (int) (Filament::getTenant()?->id ?? auth()->user()->practice_id);
        $phone = PatientImport::normalisePhone((string) $data['phone']);

        if ($phone === null) {
            Notification::make()->title(__('This is not a valid phone number.'))->danger()->send();
            $action->halt();
        }

        $existing = Patient::where('practice_id', $practiceId)->where('phone', $phone)->first();
        if ($existing) {
            Notification::make()
                ->title(__('Already a patient: :name (:file).', ['name' => trim($existing->first_name.' '.$existing->last_name), 'file' => $existing->file_number]))
                ->danger()
                ->actions([NotificationAction::make('open')->label(__('Open'))->url(PatientResource::getUrl('view', ['record' => $existing]))])
                ->send();
            $action->halt();
        }

        [$first, $last] = array_pad(explode(' ', preg_replace('/\s+/u', ' ', trim($data['name'])) ?? '', 2), 2, '');

        $patient = Patient::create([
            'practice_id' => $practiceId,
            'first_name' => $first,
            'last_name' => $last,
            'phone' => $phone,
            'whatsapp_number' => str_starts_with($phone, '01') ? $phone : null,
            'gender' => 'other',
            'status' => 'active',
        ]);

        $this->redirect(PatientResource::getUrl('view', ['record' => $patient]).'?scan=1');
    }
}
