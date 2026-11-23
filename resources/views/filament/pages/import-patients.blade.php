@php
    $money = fn ($v) => \App\Services\CurrencyHelper::format($v);
    $muted = 'text-sm text-gray-500 dark:text-gray-400';
    $text = 'text-sm text-gray-950 dark:text-white';
@endphp

{{-- Only Filament components / Filament's prebuilt classes here: the admin theme has no Tailwind build (docs/decisions.md #13). --}}
<x-filament-panels::page>
    <x-filament::section :heading="__('1. Fill in the template')">
        <p class="{{ $text }}">
            {{ __('One row per patient. Name and phone are required. Fill "Amount owed" for anyone who still owes money — it becomes an opening-balance invoice. Add the number of installments to split it into a payment plan. Arabic or English column names both work.') }}
        </p>
        <div class="mt-3">
            <x-filament::button tag="a" :href="route('patients.import.template')" icon="heroicon-o-arrow-down-tray" color="gray">
                {{ __('Download template (.xlsx)') }}
            </x-filament::button>
        </div>
    </x-filament::section>

    @if ($result)
        <x-filament::section :heading="__('Done')">
            <p class="{{ $text }}">
                {{ __(':patients patients imported. :invoices opening balances (:owed) and :plans installment plans created.', [
                    'patients' => $result['patients'], 'invoices' => $result['invoices'], 'owed' => $money($result['owed']), 'plans' => $result['plans'],
                ]) }}
            </p>
            <div class="mt-3 flex flex-wrap gap-2">
                <x-filament::button tag="a" :href="\App\Filament\Resources\PatientResource::getUrl('index')">{{ __('Open patients') }}</x-filament::button>
                <x-filament::button color="gray" wire:click="startOver">{{ __('Import another file') }}</x-filament::button>
            </div>
        </x-filament::section>
    @elseif ($summary)
        <x-filament::section :heading="__('3. Check the result, then import')">
            <div class="flex flex-wrap items-center gap-2">
                <x-filament::badge color="success">{{ __(':count ready', ['count' => $summary['ok']]) }}</x-filament::badge>
                @if ($summary['bad'] > 0)
                    <x-filament::badge color="danger">{{ __(':count with problems (skipped)', ['count' => $summary['bad']]) }}</x-filament::badge>
                @endif
                <span class="{{ $muted }}">
                    {{ __('Owed in total: :owed · installment plans: :plans', ['owed' => $money($summary['owed']), 'plans' => $summary['plans']]) }}
                </span>
            </div>

            @if ($problems)
                <p class="mt-4 {{ $muted }}">{{ __('Fix these rows in Excel and import the file again later — rows already imported are recognised by phone and skipped.') }}</p>
                <div class="mt-2 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-start {{ $muted }}">
                                <th class="py-2 pe-3 text-start">{{ __('Row') }}</th>
                                <th class="py-2 pe-3 text-start">{{ __('Name') }}</th>
                                <th class="py-2 pe-3 text-start">{{ __('Phone') }}</th>
                                <th class="py-2 text-start">{{ __('Problem') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($problems as $p)
                                <tr class="border-t border-gray-200 dark:border-white/10 align-top {{ $text }}">
                                    <td class="py-2 pe-3">{{ $p['line'] }}</td>
                                    <td class="py-2 pe-3">{{ $p['name'] }}</td>
                                    <td class="py-2 pe-3">{{ $p['phone'] }}</td>
                                    <td class="py-2 text-danger-600 dark:text-danger-400">{{ implode(' ', $p['errors']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="mt-4 flex flex-wrap gap-2">
                @if ($summary['ok'] > 0)
                    <x-filament::button wire:click="importRows" icon="heroicon-o-check">
                        {{ __('Import :count patients', ['count' => $summary['ok']]) }}
                    </x-filament::button>
                @endif
                <x-filament::button color="gray" wire:click="startOver">{{ __('Start over') }}</x-filament::button>
            </div>
        </x-filament::section>
    @else
        <x-filament::section :heading="__('2. Upload and check')">
            <form wire:submit="checkFile" class="space-y-4">
                {{ $this->form }}
                @if ($fileError)
                    <p class="text-sm text-danger-600 dark:text-danger-400">{{ $fileError }}</p>
                @endif
                <x-filament::button type="submit" icon="heroicon-o-magnifying-glass">{{ __('Check file (nothing is saved yet)') }}</x-filament::button>
            </form>
        </x-filament::section>
    @endif
</x-filament-panels::page>
