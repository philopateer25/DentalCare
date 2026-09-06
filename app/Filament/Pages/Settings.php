<?php

namespace App\Filament\Pages;

use App\Models\Practice;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;

class Settings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 1;
    protected static string $view = 'filament.pages.settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()->hasAnyRole(['clinic_admin', 'super_admin']);
    }

    public function mount(): void
    {
        $practice = \Filament\Facades\Filament::getTenant();
        if ($practice) {
            $this->form->fill($practice->toArray());
        } else {
            $this->form->fill();
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Clinic Information')
                    ->description('General details about your dental practice.')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')
                                ->label('Clinic Name')
                                ->required()
                                ->maxLength(255),
                            TextInput::make('tax_id')
                                ->label('Tax ID / Registration Number')
                                ->maxLength(255),
                        ]),
                        FileUpload::make('logo_url')
                            ->label('Clinic Logo')
                            ->image()
                            ->directory('clinic-logos')
                            ->maxSize(2048)
                            ->columnSpanFull(),
                    ]),

                Section::make('Localization')
                    ->description('Set your local currency and timezone.')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('currency')
                                ->label('Default Currency')
                                ->options([
                                    'EGP' => 'Egyptian Pound (EGP)',
                                    'USD' => 'US Dollar (USD)',
                                    'EUR' => 'Euro (EUR)',
                                    'GBP' => 'British Pound (GBP)',
                                ])
                                ->required(),
                            Select::make('timezone')
                                ->label('Timezone')
                                ->options([
                                    'Africa/Cairo' => 'Cairo',
                                    'Asia/Dubai' => 'Dubai',
                                    'Asia/Riyadh' => 'Riyadh',
                                    'Europe/London' => 'London',
                                    'America/New_York' => 'New York',
                                ])
                                ->required(),
                        ]),
                    ]),

                Section::make('Clinical Templates')
                    ->description('Configure default text templates for prescriptions and reports.')
                    ->schema([
                        Textarea::make('prescription_template')
                            ->label('Prescription Header/Footer Template')
                            ->rows(4)
                            ->placeholder("e.g. Dr. Ahmed's Dental Clinic\n123 Main St\n...")
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $practice = \Filament\Facades\Filament::getTenant();
        if ($practice) {
            $practice->update($data);
        }

        Notification::make()
            ->title('Settings saved successfully')
            ->success()
            ->send();
    }
}
