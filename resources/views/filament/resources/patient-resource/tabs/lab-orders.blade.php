<div class="w-full">
    @livewire(\App\Filament\Resources\PatientResource\Widgets\PatientLabOrdersWidget::class, ['record' => $getRecord()], key('patient-lab-orders-'.$getRecord()->id))
</div>
