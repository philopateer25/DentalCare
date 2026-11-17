<div class="space-y-4" x-data="{ search: '', selectedCat: 'all' }">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 p-4 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-xs">
        <div>
            <h3 class="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-2">
                <span class="p-1 rounded bg-teal-500/10 text-teal-600 dark:text-teal-400">🏷️</span>
                <span>Clinic Fee Schedule & Procedure Pricing</span>
            </h3>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                Custom standard fees used for 3D model procedure proposals and treatment plans
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a 
                href="{{ route('filament.admin.resources.procedure-codes.index', ['tenant' => \Filament\Facades\Filament::getTenant()?->id ?? 1]) }}"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-bold text-white bg-teal-600 hover:bg-teal-500 rounded-xl transition-all shadow-sm active:scale-95">
                <x-heroicon-m-pencil-square class="w-4 h-4" />
                <span>Manage & Edit All Prices</span>
            </a>
            <a 
                href="{{ route('filament.admin.resources.procedure-codes.create', ['tenant' => \Filament\Facades\Filament::getTenant()?->id ?? 1]) }}"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-bold text-gray-700 dark:text-gray-200 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-xl transition-all">
                <x-heroicon-m-plus class="w-4 h-4" />
                <span>New Procedure</span>
            </a>
        </div>
    </div>

    @php
        $categories = \App\Models\ProcedureCategory::with(['procedureCodes' => fn($q) => $q->orderBy('code')])->get();
        $totalCodes = \App\Models\ProcedureCode::count();
    @endphp

    {{-- Filter Toolbar --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2 flex-1 max-w-sm">
            <input 
                type="text" 
                x-model="search" 
                placeholder="Search procedure title or CDT code..." 
                class="w-full text-xs rounded-xl border-gray-200 dark:border-gray-800 dark:bg-gray-900 text-gray-900 dark:text-white shadow-xs focus:ring-teal-500 focus:border-teal-500">
        </div>
        <div class="flex items-center gap-1.5 overflow-x-auto text-xs">
            <button 
                type="button" 
                @click="selectedCat = 'all'"
                :class="selectedCat === 'all' ? 'bg-teal-600 text-white font-bold' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-200'"
                class="px-3 py-1.5 rounded-lg transition-all">
                All ({{ $totalCodes }})
            </button>
            @foreach($categories as $cat)
                <button 
                    type="button" 
                    @click="selectedCat = '{{ $cat->id }}'"
                    :class="selectedCat === '{{ $cat->id }}' ? 'bg-teal-600 text-white font-bold' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-200'"
                    class="px-3 py-1.5 rounded-lg transition-all shrink-0">
                    {{ $cat->name }} ({{ $cat->procedureCodes->count() }})
                </button>
            @endforeach
        </div>
    </div>

    {{-- Procedure Fee Schedule Cards & Table --}}
    <div class="space-y-4">
        @foreach($categories as $cat)
            <div 
                x-show="selectedCat === 'all' || selectedCat === '{{ $cat->id }}'" 
                class="border border-gray-200 dark:border-gray-800 rounded-2xl overflow-hidden bg-white dark:bg-gray-900 shadow-xs">
                <div class="px-4 py-3 bg-gray-50/70 dark:bg-gray-800/50 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 flex items-center gap-2">
                        <span>🦷</span>
                        <span>{{ $cat->name }}</span>
                    </span>
                    <span class="text-[11px] font-mono text-gray-500">
                        {{ $cat->procedureCodes->count() }} procedures
                    </span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-gray-50/40 dark:bg-gray-800/20 text-gray-500 dark:text-gray-400 text-[11px] uppercase tracking-wider border-b border-gray-100 dark:border-gray-800">
                            <tr>
                                <th class="px-4 py-2.5 font-bold">Code</th>
                                <th class="px-4 py-2.5 font-bold">Procedure Title</th>
                                <th class="px-4 py-2.5 font-bold">Duration</th>
                                <th class="px-4 py-2.5 font-bold text-right">Standard Fee</th>
                                <th class="px-4 py-2.5 font-bold text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800 text-gray-700 dark:text-gray-300">
                            @forelse($cat->procedureCodes as $code)
                                <tr 
                                    x-show="search === '' || '{{ strtolower($code->code . ' ' . $code->title) }}'.includes(search.toLowerCase())"
                                    class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30 transition-colors">
                                    <td class="px-4 py-2.5 font-mono font-bold text-teal-600 dark:text-teal-400">
                                        {{ $code->code }}
                                    </td>
                                    <td class="px-4 py-2.5 font-medium">
                                        <div class="text-gray-900 dark:text-white font-semibold">{{ $code->title }}</div>
                                        @if($code->description)
                                            <div class="text-[11px] text-gray-400 line-clamp-1">{{ $code->description }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5 text-gray-500">
                                        {{ $code->estimated_duration_minutes ?? 30 }} min
                                    </td>
                                    <td class="px-4 py-2.5 text-right font-mono font-bold text-emerald-600 dark:text-emerald-400">
                                        {{ \App\Services\CurrencyHelper::format($code->standard_fee) }}
                                    </td>
                                    <td class="px-4 py-2.5 text-right">
                                        <a 
                                            href="{{ route('filament.admin.resources.procedure-codes.edit', ['tenant' => \Filament\Facades\Filament::getTenant()?->id ?? 1, 'record' => $code->id]) }}"
                                            class="text-indigo-600 dark:text-indigo-400 hover:underline font-bold text-[11px]">
                                            Edit Price &rarr;
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-4 text-center text-gray-400">
                                        No procedures in this category yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    </div>
</div>
