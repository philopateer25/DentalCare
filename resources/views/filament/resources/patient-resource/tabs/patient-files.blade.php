<div class="w-full">
    @livewire(\App\Filament\Resources\PatientResource\Widgets\PatientFilesWidget::class, ['record' => $getRecord()], key('patient-files-'.$getRecord()->id))
</div>
