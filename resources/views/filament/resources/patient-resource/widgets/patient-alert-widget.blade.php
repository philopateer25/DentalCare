<x-filament-widgets::widget>
    @php
        $medicalHistory = $record?->medicalHistory;
        
        $alerts = [];
        if ($medicalHistory) {
            if ($medicalHistory->latex_allergy) $alerts[] = 'Latex Allergy';
            if ($medicalHistory->penicillin_allergy) $alerts[] = 'Penicillin Allergy';
            if ($medicalHistory->local_anesthetic_allergy) $alerts[] = 'Local Anesthetic Allergy';
            if ($medicalHistory->diabetic_status) $alerts[] = 'Diabetic Patient';
            if ($medicalHistory->cardiac_history) $alerts[] = 'Cardiac History';
            if ($medicalHistory->hypertension_status) $alerts[] = 'Hypertension';
            if ($medicalHistory->bleeding_disorder) $alerts[] = 'Bleeding Disorder';
        }
    @endphp

    @if(count($alerts) > 0)
        <div class="p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50 dark:bg-gray-800 dark:text-red-400 border border-red-200" role="alert">
            <div class="flex items-center">
                <x-heroicon-o-exclamation-triangle class="w-6 h-6 mr-2 text-red-600 dark:text-red-400" />
                <span class="font-bold text-lg">CRITICAL MEDICAL ALERTS</span>
            </div>
            <ul class="mt-2 ml-8 list-disc list-outside font-medium">
                @foreach($alerts as $alert)
                    <li>{{ $alert }}</li>
                @endforeach
            </ul>
        </div>
    @endif
</x-filament-widgets::widget>
