<div class="w-full space-y-6">
    <div>
        @livewire(\App\Filament\Resources\PatientResource\Widgets\PatientFinanceWidget::class, ['record' => $getRecord()], key('patient-finance-'.$getRecord()->id))
    </div>

    @include('filament.resources.patient-resource.tabs.procedure-pricing')
</div>
