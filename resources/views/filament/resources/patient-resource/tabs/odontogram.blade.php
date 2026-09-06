<div style="width: 100%; height: 450px; border-radius: 0.75rem; overflow: hidden;"
     x-data="{ isDark: document.documentElement.classList.contains('dark') }"
     @theme-changed.window="isDark = document.documentElement.classList.contains('dark')">
    <iframe 
        x-bind:src="`{{ route('patient.odontogram', $getRecord()->id) }}?dark=` + (isDark ? '1' : '0')"
        style="width: 100%; height: 100%; border: none;" 
        allowfullscreen>
    </iframe>
</div>
