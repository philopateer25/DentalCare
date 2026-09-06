<div class="w-full">
    @livewire(\App\Filament\Resources\PatientResource\Widgets\PatientFinanceWidget::class, ['record' => $getRecord()], key('patient-finance-'.$getRecord()->id))
</div>
