<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Components\FileUpload;
use App\Models\Practice;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class Settings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static string $view = 'filament.pages.settings';

    public ?array $data = [];

    public function mount(): void
    {
        $practice = Practice::firstOrCreate(['id' => 1], ['name' => 'My Dental Clinic']);
        $this->form->fill($practice->toArray());
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                FileUpload::make('prescription_template')
                    ->label('Prescription Template Letterhead')
                    ->image()
                    ->directory('templates')
                    ->helperText('Upload an A4 sized image to use as the background for printed prescriptions.')
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Settings')
                ->submit('save'),
        ];
    }

    public function save(): void
    {
        $practice = Practice::firstOrCreate(['id' => 1]);
        $practice->update($this->form->getState());

        Notification::make()
            ->title('Settings saved successfully.')
            ->success()
            ->send();
    }
}
