<x-filament-widgets::widget class="dx-greeting-widget">
    <div class="dx-greeting">
        <div class="dx-greeting-main">
            <div class="dx-greeting-title-row">
                <h1 class="dx-greeting-title">{{ $greeting }}{{ $firstName ? ', '.$firstName : '' }}</h1>
                <span class="dx-live-pill"><span></span>{{ __('Clinic Live') }}</span>
            </div>
            <div class="dx-greeting-meta">
                <span class="dx-greeting-date">{{ $date }}</span>
                @if ($clinic)
                    <span class="dx-greeting-sep">&bull;</span>
                    <span>{{ $clinic }}</span>
                @endif
            </div>
            @if ($onDuty->isNotEmpty())
                <div class="dx-greeting-duty">
                    <x-filament::icon icon="heroicon-o-user-group" class="h-4 w-4" />
                    {{ __('On duty') }}: {{ $onDuty->implode(', ') }}
                </div>
            @endif
        </div>

        @if ($createUrl)
            <div class="dx-greeting-actions">
                <a href="{{ $appointmentsUrl }}" class="dx-btn dx-btn-soft">
                    <x-filament::icon icon="heroicon-o-clipboard-document-check" class="h-[18px] w-[18px]" />
                    {{ __('Today\'s appointments') }}
                </a>
                <a href="{{ $createUrl }}" class="dx-btn dx-btn-primary">
                    <x-filament::icon icon="heroicon-o-plus" class="h-[18px] w-[18px]" />
                    {{ __('New appointment') }}
                </a>
            </div>
        @endif
    </div>
</x-filament-widgets::widget>
