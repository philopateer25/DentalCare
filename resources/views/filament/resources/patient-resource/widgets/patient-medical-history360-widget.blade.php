<x-filament-widgets::widget>
    @php
        $risks = $this->getMedicalRiskAssessment();
        $externalTreatments = $this->getExternalTreatments();
        $timeline = $this->getClinicJourneyTimeline();
        $roadmap = $this->getFutureRoadmap();
        $allPlans = $this->getAllTreatmentPlans();
        $toothHistory = $this->getToothHistoryMap();
        $filterCounts = $this->getTimelineFilterCounts();
        $availableTeeth = $this->getAvailableTimelineTeeth();
        $availableDoctors = $this->getAvailableTimelineDoctors();
        $hasActiveFilters = ($this->typeFilter !== 'all' || $this->toothFilter !== 'all' || $this->doctorFilter !== 'all' || $this->historyFilter !== 'all' || !empty(trim($this->searchQuery)));
    @endphp

    <div class="space-y-6" 
         x-data="{ 
             currentFilter: @entangle('historyFilter'),
             isTimelineCollapsed: false,
             isTimelineMaximized: false,
             expandAllEntries: false,
             init() {
                 window.addEventListener('message', (e) => {
                     if (e.data && (e.data.type === 'DENTAL_ODONTOGRAM_UPDATED' || e.data.type === 'TOOTH_FINDING_SAVED')) {
                         $wire.$refresh();
                     }
                 });
                 window.addEventListener('focus', () => {
                     $wire.$refresh();
                 });
             }
         }"
         x-on:refresh-patient-360.window="$wire.$refresh()">

        {{-- 1. EXECUTIVE HEADER & CLINICAL STATS BAR --}}
        <div class="p-5 rounded-2xl bg-gradient-to-r from-slate-950 via-teal-950 to-slate-900 text-white border border-teal-800/40 shadow-xl relative overflow-hidden">
            <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-teal-500/10 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute -left-10 -top-10 w-64 h-64 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>
            
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 relative z-10">
                <div class="flex items-center gap-3.5">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-teal-500 to-emerald-600 flex items-center justify-center shadow-lg shadow-teal-500/20 text-white ring-1 ring-white/20">
                        <x-heroicon-o-shield-check class="w-7 h-7" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-lg font-black tracking-tight text-white">
                                360° Comprehensive Medical & Dental Profile
                            </h2>
                            <span class="px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                Unified Clinical View
                            </span>
                        </div>
                        <p class="text-xs text-teal-200/80 mt-0.5">
                            Synchronized medical risk screening, clinic procedures, outside treatments, and 3D dental mapping.
                        </p>
                    </div>
                </div>

                {{-- Action Buttons --}}
                <div class="flex items-center gap-2.5 flex-wrap">
                    <button 
                        type="button"
                        wire:click="openAddExternalModal"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-bold text-white bg-teal-600 hover:bg-teal-500 active:scale-95 rounded-xl shadow-md shadow-teal-900/30 transition-all border border-teal-400/30">
                        <x-heroicon-m-plus class="w-4 h-4" />
                        <span>Log Outside Treatment</span>
                    </button>

                    <a 
                        href="{{ route('patient.odontogram', $record?->id ?? 1) }}" 
                        target="_blank"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-bold text-white bg-white/10 hover:bg-white/20 active:scale-95 rounded-xl backdrop-blur-md transition-all border border-white/15 hover:border-amber-400/40">
                        <x-heroicon-m-sparkles class="w-4 h-4 text-amber-300" />
                        <span>Open 3D Odontogram</span>
                    </a>
                </div>
            </div>

            {{-- Metric Chips --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-4 pt-4 border-t border-teal-800/40 text-xs">
                <div class="flex items-center gap-2.5 p-2.5 rounded-xl bg-white/5 border border-white/10 backdrop-blur-sm hover:border-teal-400/30 transition-all">
                    <x-heroicon-o-check-circle class="w-5 h-5 text-emerald-400 shrink-0" />
                    <div>
                        <span class="text-[10px] uppercase text-teal-200/70 block font-semibold">Completed Inside</span>
                        <strong class="text-sm font-black text-white">{{ count(array_filter($timeline, fn($i) => ($i['source'] ?? '') === 'inside' && $i['type'] === 'procedure')) }} Procedures</strong>
                    </div>
                </div>

                <div class="flex items-center gap-2.5 p-2.5 rounded-xl bg-white/5 border border-white/10 backdrop-blur-sm hover:border-teal-400/30 transition-all">
                    <x-heroicon-o-building-office-2 class="w-5 h-5 text-teal-400 shrink-0" />
                    <div>
                        <span class="text-[10px] uppercase text-teal-200/70 block font-semibold">Outside / Prior Work</span>
                        <strong class="text-sm font-black text-white">{{ count($externalTreatments) }} Logged</strong>
                    </div>
                </div>

                <div class="flex items-center gap-2.5 p-2.5 rounded-xl bg-white/5 border border-white/10 backdrop-blur-sm hover:border-teal-400/30 transition-all">
                    <x-heroicon-o-clipboard-document-list class="w-5 h-5 text-amber-400 shrink-0" />
                    <div>
                        <span class="text-[10px] uppercase text-teal-200/70 block font-semibold">Treatment Plans</span>
                        <strong class="text-sm font-black text-white">{{ count($allPlans) }} Total</strong>
                    </div>
                </div>

                <div class="flex items-center gap-2.5 p-2.5 rounded-xl bg-white/5 border border-white/10 backdrop-blur-sm hover:border-teal-400/30 transition-all">
                    <x-heroicon-o-sparkles class="w-5 h-5 text-cyan-400 shrink-0" />
                    <div>
                        <span class="text-[10px] uppercase text-teal-200/70 block font-semibold">Teeth Treated</span>
                        <strong class="text-sm font-black text-white">{{ count($toothHistory) }} Teeth</strong>
                    </div>
                </div>
            </div>
        </div>

        {{-- 2. INTERACTIVE TEETH WITH CLINICAL HISTORY (DEEP LINK TO 3D MODEL) --}}
        @if(count($toothHistory) > 0)
            <div class="p-4 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-sm">
                <div class="flex items-center justify-between pb-2.5 border-b border-gray-100 dark:border-gray-800">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-sparkles class="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
                        <h4 class="text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider">
                            Teeth With Clinical History (Click to focus 3D Model)
                        </h4>
                    </div>
                    <span class="text-[10px] text-gray-400 font-medium">
                        {{ count($toothHistory) }} teeth treated inside or outside clinic
                    </span>
                </div>

                <div class="flex items-center gap-2 mt-3 flex-wrap">
                    @foreach($toothHistory as $tNum => $tData)
                        @php
                            $hasCondition = !empty($tData['condition']) && ($tData['condition_raw'] ?? '') !== 'healthy';
                            $condColor = $tData['condition_color'] ?? 'indigo';
                            $badgeBg = match($condColor) {
                                'slate' => 'bg-slate-200 text-slate-800 dark:bg-slate-700 dark:text-slate-200',
                                'purple' => 'bg-purple-100 text-purple-800 dark:bg-purple-900/60 dark:text-purple-200',
                                'blue' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/60 dark:text-blue-200',
                                'cyan' => 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900/60 dark:text-cyan-200',
                                'rose' => 'bg-rose-100 text-rose-800 dark:bg-rose-900/60 dark:text-rose-200',
                                'amber' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-200',
                                'emerald' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-200',
                                default => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/60 dark:text-indigo-200',
                            };
                            $totalEvents = ($tData['inside_count'] ?? 0) + ($tData['outside_count'] ?? 0) + ($tData['condition_count'] ?? 0);
                        @endphp
                        <button 
                            type="button"
                            wire:click="openTooth3DModal('{{ $tNum }}')"
                            class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border text-xs font-bold transition-all shadow-sm active:scale-95 group hover:ring-2 hover:ring-primary-400
                                {{ ($tData['inside_count'] ?? 0) > 0 && ($tData['outside_count'] ?? 0) > 0 
                                    ? 'bg-gradient-to-r from-emerald-50 to-indigo-50 border-emerald-300 dark:from-emerald-950/40 dark:to-indigo-950/40 dark:border-emerald-700 text-emerald-900 dark:text-emerald-200 hover:border-emerald-500' 
                                    : (($tData['inside_count'] ?? 0) > 0 
                                        ? 'bg-emerald-50/80 border-emerald-200 dark:bg-emerald-950/30 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 hover:border-emerald-400' 
                                        : (($tData['outside_count'] ?? 0) > 0
                                            ? 'bg-indigo-50/80 border-indigo-200 dark:bg-indigo-950/30 dark:border-indigo-800 text-indigo-800 dark:text-indigo-300 hover:border-indigo-400'
                                            : 'bg-slate-50 border-slate-200 dark:bg-slate-800/40 dark:border-slate-700 text-slate-800 dark:text-slate-200 hover:border-slate-400')) }}">
                            <span>🦷 #{{ $tNum }}</span>
                            @if($hasCondition)
                                <span class="px-1.5 py-0.5 rounded text-[9px] font-black uppercase tracking-tight {{ $badgeBg }}">
                                    {{ $tData['condition'] }}
                                </span>
                            @endif
                            <span class="px-1.5 py-0.2 rounded-full text-[9px] font-black bg-white/80 dark:bg-gray-800 shadow-xs">
                                {{ $totalEvents > 0 ? $totalEvents : 1 }}
                            </span>
                            <x-heroicon-m-chevron-right class="w-3 h-3 opacity-0 group-hover:opacity-100 transition-opacity" />
                        </button>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- 3. CRITICAL MEDICAL RISK & SCREENING ASSESSMENT RIBBON --}}
        <div class="p-4 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-sm">
            <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-800">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-shield-check class="w-5 h-5 text-primary-600 dark:text-primary-400" />
                    <h3 class="text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider">
                        Critical Medical Risk & Screening Assessment
                    </h3>
                </div>
                <span class="text-[10px] text-gray-500 dark:text-gray-400 font-medium">
                    Nuanced Screening &bull; Explicit Negative vs. Missing Data
                </span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3 mt-3">
                @foreach($risks as $key => $risk)
                    @php
                        $color = $risk['color'] ?? 'slate';
                        $bgClass = match($color) {
                            'rose' => 'bg-rose-50/80 border-rose-200 dark:bg-rose-950/30 dark:border-rose-900/60',
                            'amber' => 'bg-amber-50/80 border-amber-200 dark:bg-amber-950/30 dark:border-amber-900/60',
                            'emerald' => 'bg-emerald-50/80 border-emerald-200 dark:bg-emerald-950/30 dark:border-emerald-900/60',
                            default => 'bg-gray-50 border-gray-200 dark:bg-gray-800/40 dark:border-gray-700',
                        };
                        $badgeClass = match($color) {
                            'rose' => 'bg-rose-100 text-rose-800 dark:bg-rose-900/80 dark:text-rose-200 border-rose-200',
                            'amber' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/80 dark:text-amber-200 border-amber-200',
                            'emerald' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/80 dark:text-emerald-200 border-emerald-200',
                            default => 'bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-300 border-gray-300',
                        };
                    @endphp
                    <div class="p-3 rounded-lg border {{ $bgClass }} flex flex-col justify-between">
                        <div>
                            <div class="flex items-start justify-between gap-1.5">
                                <span class="text-xs font-bold text-gray-900 dark:text-gray-100 uppercase tracking-wider">
                                    {{ $risk['title'] }}
                                </span>
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold border {{ $badgeClass }} whitespace-nowrap">
                                    {{ $risk['badge'] }}
                                </span>
                            </div>
                            <p class="mt-2 text-xs leading-relaxed text-gray-700 dark:text-gray-300">
                                {{ $risk['description'] }}
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- 4. THREE-COLUMN INTEGRATED CLINICAL GRID --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- COLUMN 1: OUTSIDE CLINIC HISTORY LOG --}}
            <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm flex flex-col">
                <div class="p-4 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-building-office-2 class="w-5 h-5 text-indigo-500" />
                        <div>
                            <h4 class="text-sm font-bold text-gray-900 dark:text-white">Outside Clinic History</h4>
                            <p class="text-[11px] text-gray-500 dark:text-gray-400">External dental treatments & prior work</p>
                        </div>
                    </div>
                    <button 
                        type="button"
                        wire:click="openAddExternalModal"
                        class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg shadow-sm transition-colors">
                        <x-heroicon-m-plus class="w-3.5 h-3.5" />
                        <span>Log Record</span>
                    </button>
                </div>

                <div class="p-4 flex-1 space-y-3 overflow-y-auto max-h-[620px]">
                    @forelse($externalTreatments as $ext)
                        <div class="p-3.5 rounded-lg border border-gray-200 dark:border-gray-800 bg-gray-50/60 dark:bg-gray-800/40 relative group hover:border-indigo-300 dark:hover:border-indigo-700 transition-all cursor-pointer"
                             wire:click="openExternalModal({{ $ext->id }})">
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex items-center gap-2 flex-wrap">
                                    @if($ext->tooth_number_fdi)
                                        <button 
                                            type="button"
                                            wire:click.stop="openTooth3DModal('{{ $ext->tooth_number_fdi }}')"
                                            class="px-2 py-0.5 text-xs font-bold rounded bg-indigo-100 text-indigo-800 dark:bg-indigo-900/80 dark:text-indigo-200 hover:bg-indigo-200 flex items-center gap-1 shadow-xs"
                                            title="Click to view Tooth in 3D">
                                            <span>🦷 #{{ $ext->tooth_number_fdi }}</span>
                                            <x-heroicon-m-sparkles class="w-3 h-3 text-indigo-600 dark:text-indigo-400" />
                                        </button>
                                    @else
                                        <span class="px-2 py-0.5 text-xs font-semibold rounded bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                            General
                                        </span>
                                    @endif
                                    <h5 class="text-xs font-bold text-gray-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                                        {{ $ext->procedure_title }}
                                    </h5>
                                </div>

                                <button 
                                    type="button"
                                    wire:click.stop="deleteExternalTreatment({{ $ext->id }})"
                                    wire:confirm="Are you sure you want to remove this external treatment record?"
                                    class="opacity-0 group-hover:opacity-100 text-gray-400 hover:text-red-500 transition-opacity p-0.5"
                                    title="Delete record">
                                    <x-heroicon-m-trash class="w-3.5 h-3.5" />
                                </button>
                            </div>

                            <div class="mt-2 space-y-1 text-xs text-gray-600 dark:text-gray-400">
                                @if($ext->performed_year || $ext->clinic_or_doctor_name)
                                    <div class="flex items-center gap-3 text-[11px] text-gray-500">
                                        @if($ext->performed_year)
                                            <span>📅 {{ $ext->performed_year }}</span>
                                        @endif
                                        @if($ext->clinic_or_doctor_name)
                                            <span>🏥 {{ $ext->clinic_or_doctor_name }}</span>
                                        @endif
                                    </div>
                                @endif

                                @if($ext->notes)
                                    <p class="mt-1 text-xs text-gray-700 dark:text-gray-300 italic line-clamp-2">
                                        &ldquo;{{ $ext->notes }}&rdquo;
                                    </p>
                                @endif
                            </div>

                            <div class="mt-2.5 pt-2 border-t border-gray-200/70 dark:border-gray-700/60 flex items-center justify-between text-[10px] text-gray-400 dark:text-gray-500">
                                <span>Logged by: <strong class="text-gray-600 dark:text-gray-400">{{ $ext->creator?->name ?? 'Staff' }}</strong></span>
                                <span class="text-indigo-600 dark:text-indigo-400 font-semibold group-hover:underline">View Details &rarr;</span>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-10 text-gray-400 dark:text-gray-500 text-xs">
                            <x-heroicon-o-document-text class="w-8 h-8 mx-auto mb-2 text-gray-300 dark:text-gray-600" />
                            <p>No outside dental treatments logged.</p>
                            <button 
                                type="button" 
                                wire:click="openAddExternalModal"
                                class="mt-2 text-xs text-indigo-600 dark:text-indigo-400 font-bold hover:underline">
                                + Log Prior Treatments (Crowns, Implants, RCTs)
                            </button>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- COLUMN 2: UNIFIED CHRONOLOGICAL JOURNEY TIMELINE --}}
            <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm flex flex-col transition-all duration-200"
                 :class="isTimelineMaximized ? 'lg:col-span-3 order-first ring-2 ring-emerald-500/30 shadow-lg' : ''">
                
                {{-- HEADER (WITH OPEN/CLOSE & MAXIMIZE CONTROLS - 0MS INSTANT) --}}
                <div class="p-4 border-b border-gray-100 dark:border-gray-800 bg-gray-50/40 dark:bg-gray-800/20">
                    <div class="flex items-center justify-between gap-3 flex-wrap">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-lg bg-emerald-100 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                                <x-heroicon-o-clock class="w-5 h-5" />
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <h4 class="text-sm font-bold text-gray-900 dark:text-white">Unified Clinical Logs</h4>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $hasActiveFilters ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-200' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300' }}">
                                        {{ count($timeline) }} {{ $hasActiveFilters ? 'of ' . $filterCounts['all'] : '' }} Records
                                    </span>
                                </div>
                                <p class="text-[11px] text-gray-500 dark:text-gray-400">Procedures, appointments, 3D odontogram findings & outside history</p>
                            </div>
                        </div>

                        {{-- Action Controls: Expand/Collapse Details, Maximize, Open/Close --}}
                        <div class="flex items-center gap-1.5">
                            <div x-show="!isTimelineCollapsed" class="flex items-center gap-1.5">
                                <button 
                                    type="button" 
                                    @click="expandAllEntries = !expandAllEntries"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-semibold rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors shadow-xs active:scale-95"
                                    title="Expand or collapse detailed inline protocol information">
                                    <x-heroicon-m-arrows-pointing-out class="w-3.5 h-3.5 transition-transform duration-200" ::class="expandAllEntries ? 'rotate-180 text-emerald-500' : ''" />
                                    <span x-text="expandAllEntries ? 'Collapse Details' : 'Expand Details'">Expand Details</span>
                                </button>

                                <button 
                                    type="button" 
                                    @click="isTimelineMaximized = !isTimelineMaximized"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-semibold rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors shadow-xs active:scale-95"
                                    :title="isTimelineMaximized ? 'Restore to column' : 'Expand to full width'">
                                    <x-heroicon-m-arrows-pointing-in class="w-3.5 h-3.5 text-emerald-600" x-show="isTimelineMaximized" />
                                    <x-heroicon-m-arrows-pointing-out class="w-3.5 h-3.5 text-emerald-600" x-show="!isTimelineMaximized" />
                                    <span x-text="isTimelineMaximized ? 'Restore' : 'Maximize'">Maximize</span>
                                </button>
                            </div>

                            {{-- Open / Close Toggle Button (Instant 0ms Client-Side Alpine Toggle) --}}
                            <button 
                                type="button" 
                                @click="isTimelineCollapsed = !isTimelineCollapsed"
                                class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-bold rounded-lg border transition-all active:scale-95"
                                :class="isTimelineCollapsed ? 'bg-primary-600 text-white border-primary-600 hover:bg-primary-500 shadow-xs' : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-700 hover:bg-gray-200 dark:hover:bg-gray-700'">
                                <x-heroicon-m-chevron-down class="w-3.5 h-3.5" x-show="isTimelineCollapsed" />
                                <x-heroicon-m-chevron-up class="w-3.5 h-3.5" x-show="!isTimelineCollapsed" />
                                <span x-text="isTimelineCollapsed ? 'Open Logs' : 'Close Logs'">Close Logs</span>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- COLLAPSED STATE VIEW (Instant Alpine Toggle) --}}
                <div x-show="isTimelineCollapsed" x-cloak class="p-4 bg-gray-50/60 dark:bg-gray-800/30 text-center flex flex-col items-center justify-center py-6 space-y-2">
                    <x-heroicon-o-bars-3-bottom-left class="w-7 h-7 text-gray-400 dark:text-gray-500" />
                    <p class="text-xs font-semibold text-gray-600 dark:text-gray-300">
                        Unified Clinical Logs are closed ({{ count($timeline) }} entries available)
                    </p>
                    <div class="flex items-center gap-1.5 flex-wrap justify-center text-[10px] text-gray-500">
                        <span>💉 {{ $filterCounts['procedure'] }} Procedures</span> &bull;
                        <span>📅 {{ $filterCounts['appointment'] }} Visits</span> &bull;
                        <span>🦷 {{ $filterCounts['condition'] }} Findings</span> &bull;
                        <span>💊 {{ $filterCounts['prescription'] }} Rx</span> &bull;
                        <span>🏥 {{ $filterCounts['external'] }} Outside</span>
                    </div>
                    <button 
                        type="button" 
                        @click="isTimelineCollapsed = false"
                        class="mt-2 inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-white bg-primary-600 hover:bg-primary-500 rounded-lg shadow-sm transition-all active:scale-95">
                        <x-heroicon-m-chevron-down class="w-4 h-4" />
                        <span>Expand Unified Logs</span>
                    </button>
                </div>

                {{-- OPEN STATE: COMPREHENSIVE FILTER TOOLBAR & STREAM --}}
                <div x-show="!isTimelineCollapsed" class="flex flex-col flex-1">
                    {{-- OPEN STATE: COMPREHENSIVE FILTER TOOLBAR --}}
                    <div class="p-3 border-b border-gray-100 dark:border-gray-800 bg-gray-50/70 dark:bg-gray-800/40 space-y-2.5">
                        {{-- Row 1: Event Type Quick Pills with Live Counts --}}
                        <div class="flex items-center gap-1.5 overflow-x-auto pb-1 scrollbar-thin">
                            <button 
                                type="button" 
                                wire:click="setTypeFilter('all')"
                                class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-bold rounded-lg whitespace-nowrap transition-all {{ $typeFilter === 'all' ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900 shadow-xs' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-100 border border-gray-200 dark:border-gray-700' }}">
                                <span>⚡ All Types</span>
                                <span class="px-1.5 py-0.2 rounded-full text-[10px] font-black {{ $typeFilter === 'all' ? 'bg-white/20 text-white dark:bg-black/20 dark:text-gray-900' : 'bg-gray-100 dark:bg-gray-700 text-gray-500' }}">
                                    {{ $filterCounts['all'] }}
                                </span>
                            </button>

                            <button 
                                type="button" 
                                wire:click="setTypeFilter('procedure')"
                                class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-bold rounded-lg whitespace-nowrap transition-all {{ $typeFilter === 'procedure' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-white dark:bg-gray-800 text-emerald-700 dark:text-emerald-400 hover:bg-emerald-50 border border-emerald-200 dark:border-emerald-900' }}">
                                <span>💉 Procedures</span>
                                <span class="px-1.5 py-0.2 rounded-full text-[10px] font-black {{ $typeFilter === 'procedure' ? 'bg-emerald-800 text-white' : 'bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300' }}">
                                    {{ $filterCounts['procedure'] }}
                                </span>
                            </button>

                            <button 
                                type="button" 
                                wire:click="setTypeFilter('appointment')"
                                class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-bold rounded-lg whitespace-nowrap transition-all {{ $typeFilter === 'appointment' ? 'bg-sky-600 text-white shadow-xs' : 'bg-white dark:bg-gray-800 text-sky-700 dark:text-sky-400 hover:bg-sky-50 border border-sky-200 dark:border-sky-900' }}">
                                <span>📅 Visits</span>
                                <span class="px-1.5 py-0.2 rounded-full text-[10px] font-black {{ $typeFilter === 'appointment' ? 'bg-sky-800 text-white' : 'bg-sky-100 dark:bg-sky-950 text-sky-800 dark:text-sky-300' }}">
                                    {{ $filterCounts['appointment'] }}
                                </span>
                            </button>

                            <button 
                                type="button" 
                                wire:click="setTypeFilter('condition')"
                                class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-bold rounded-lg whitespace-nowrap transition-all {{ $typeFilter === 'condition' ? 'bg-cyan-600 text-white shadow-xs' : 'bg-white dark:bg-gray-800 text-cyan-700 dark:text-cyan-400 hover:bg-cyan-50 border border-cyan-200 dark:border-cyan-900' }}">
                                <span>🦷 3D Teeth</span>
                                <span class="px-1.5 py-0.2 rounded-full text-[10px] font-black {{ $typeFilter === 'condition' ? 'bg-cyan-800 text-white' : 'bg-cyan-100 dark:bg-cyan-950 text-cyan-800 dark:text-cyan-300' }}">
                                    {{ $filterCounts['condition'] }}
                                </span>
                            </button>

                            <button 
                                type="button" 
                                wire:click="setTypeFilter('external')"
                                class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-bold rounded-lg whitespace-nowrap transition-all {{ $typeFilter === 'external' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-white dark:bg-gray-800 text-indigo-700 dark:text-indigo-400 hover:bg-indigo-50 border border-indigo-200 dark:border-indigo-900' }}">
                                <span>🏥 Outside Work</span>
                                <span class="px-1.5 py-0.2 rounded-full text-[10px] font-black {{ $typeFilter === 'external' ? 'bg-indigo-800 text-white' : 'bg-indigo-100 dark:bg-indigo-950 text-indigo-800 dark:text-indigo-300' }}">
                                    {{ $filterCounts['external'] }}
                                </span>
                            </button>

                            <button 
                                type="button" 
                                wire:click="setTypeFilter('prescription')"
                                class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-bold rounded-lg whitespace-nowrap transition-all {{ $typeFilter === 'prescription' ? 'bg-amber-600 text-white shadow-xs' : 'bg-white dark:bg-gray-800 text-amber-700 dark:text-amber-400 hover:bg-amber-50 border border-amber-200 dark:border-amber-900' }}">
                                <span>💊 Prescriptions</span>
                                <span class="px-1.5 py-0.2 rounded-full text-[10px] font-black {{ $typeFilter === 'prescription' ? 'bg-amber-800 text-white' : 'bg-amber-100 dark:bg-amber-950 text-amber-800 dark:text-amber-300' }}">
                                    {{ $filterCounts['prescription'] }}
                                </span>
                            </button>

                            @if(($filterCounts['voice_note'] ?? 0) > 0)
                                <button 
                                    type="button" 
                                    wire:click="setTypeFilter('voice_note')"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-bold rounded-lg whitespace-nowrap transition-all {{ $typeFilter === 'voice_note' ? 'bg-purple-600 text-white shadow-xs' : 'bg-white dark:bg-gray-800 text-purple-700 dark:text-purple-400 hover:bg-purple-50 border border-purple-200 dark:border-purple-900' }}">
                                    <span>🎙️ Voice Notes</span>
                                    <span class="px-1.5 py-0.2 rounded-full text-[10px] font-black {{ $typeFilter === 'voice_note' ? 'bg-purple-800 text-white' : 'bg-purple-100 dark:bg-purple-950 text-purple-800 dark:text-purple-300' }}">
                                        {{ $filterCounts['voice_note'] }}
                                    </span>
                                </button>
                            @endif
                        </div>

                        {{-- Row 2: Search Box, Teeth Filter, Doctor Filter, Source, Sort & Reset --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-2 text-xs">
                            {{-- Search Input --}}
                            <div class="lg:col-span-4 relative">
                                <x-heroicon-m-magnifying-glass class="w-4 h-4 text-gray-400 absolute left-2.5 top-2.5 pointer-events-none" />
                                <input 
                                    type="text" 
                                    wire:model.live.debounce.250ms="searchQuery"
                                    placeholder="Search procedures, crowns, notes..."
                                    class="w-full pl-8 pr-7 py-1.5 text-xs rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 shadow-2xs" />
                                @if(!empty($searchQuery))
                                    <button 
                                        type="button" 
                                        wire:click="$set('searchQuery', '')" 
                                        class="absolute right-2 top-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                                        <x-heroicon-m-x-mark class="w-3.5 h-3.5" />
                                    </button>
                                @endif
                            </div>

                            {{-- Tooth Filter Dropdown --}}
                            <div class="lg:col-span-3">
                                <select 
                                    wire:model.live="toothFilter"
                                    class="w-full py-1.5 px-2.5 text-xs rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-1 focus:ring-primary-500 focus:border-primary-500 shadow-2xs">
                                    <option value="all">🦷 All Teeth</option>
                                    <option value="general">General (No Tooth)</option>
                                    @foreach($availableTeeth as $tNum)
                                        <option value="{{ $tNum }}">Tooth #{{ $tNum }}</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Doctor Filter Dropdown --}}
                            <div class="lg:col-span-3">
                                <select 
                                    wire:model.live="doctorFilter"
                                    class="w-full py-1.5 px-2.5 text-xs rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-1 focus:ring-primary-500 focus:border-primary-500 shadow-2xs">
                                    <option value="all">👨‍⚕️ All Doctors</option>
                                    @foreach($availableDoctors as $doc)
                                        <option value="{{ $doc }}">{{ $doc }}</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Sort & Source & Reset (lg:col-span-2) --}}
                            <div class="lg:col-span-2 flex items-center gap-1 justify-end">
                                {{-- Inside / Outside Source --}}
                                <div class="flex items-center bg-gray-200 dark:bg-gray-700 p-0.5 rounded-lg text-[10px] font-bold">
                                    <button 
                                        type="button" 
                                        wire:click="setHistoryFilter('all')"
                                        class="px-1.5 py-0.5 rounded {{ $historyFilter === 'all' ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-2xs' : 'text-gray-500 hover:text-gray-900' }}"
                                        title="All sources">
                                        All
                                    </button>
                                    <button 
                                        type="button" 
                                        wire:click="setHistoryFilter('internal')"
                                        class="px-1.5 py-0.5 rounded {{ $historyFilter === 'internal' ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-2xs' : 'text-gray-500 hover:text-gray-900' }}"
                                        title="Inside Clinic">
                                        In
                                    </button>
                                    <button 
                                        type="button" 
                                        wire:click="setHistoryFilter('external')"
                                        class="px-1.5 py-0.5 rounded {{ $historyFilter === 'external' ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-2xs' : 'text-gray-500 hover:text-gray-900' }}"
                                        title="Outside Clinic">
                                        Out
                                    </button>
                                </div>

                                {{-- Sort Order Toggle --}}
                                <button 
                                    type="button" 
                                    wire:click="setSortOrder('{{ $sortOrder === 'desc' ? 'asc' : 'desc' }}')"
                                    class="p-1.5 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-100 shadow-2xs"
                                    title="Toggle Date Sort Order: {{ $sortOrder === 'desc' ? 'Newest First' : 'Oldest First' }}">
                                    <x-heroicon-m-arrows-up-down class="w-3.5 h-3.5" />
                                </button>

                                {{-- Reset Filters --}}
                                @if($hasActiveFilters)
                                    <button 
                                        type="button" 
                                        wire:click="resetTimelineFilters"
                                        class="px-2 py-1 text-[10px] font-bold rounded-lg bg-rose-50 text-rose-700 border border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-900 hover:bg-rose-100 transition-colors"
                                        title="Clear all active filters">
                                        Reset
                                    </button>
                                @endif
                            </div>
                        </div>

                        {{-- Active Filter Feedback Bar --}}
                        @if($hasActiveFilters)
                            <div class="flex items-center justify-between text-[11px] text-gray-500 dark:text-gray-400 pt-1 border-t border-gray-200/60 dark:border-gray-700/50">
                                <span>
                                    Filtered: <strong class="text-gray-800 dark:text-gray-200">{{ count($timeline) }}</strong> matching records
                                </span>
                                <button 
                                    type="button" 
                                    wire:click="resetTimelineFilters"
                                    class="text-primary-600 dark:text-primary-400 font-bold hover:underline">
                                    Show all {{ $filterCounts['all'] }} entries &rarr;
                                </button>
                            </div>
                        @endif
                    </div>

                    {{-- TIMELINE LOG ENTRIES STREAM --}}
                    <div class="p-4 flex-1 overflow-y-auto max-h-[640px]">
                        @if(count($timeline) > 0)
                            <div class="relative border-l-2 border-gray-200 dark:border-gray-800 ml-3 pl-4 space-y-4">
                                @foreach($timeline as $item)
                                    @php
                                        $itemKey = $item['key'] ?? "log-{$loop->index}";
                                        // Expand state is handled client-side by Alpine (expandAllEntries || itemExpanded)
                                        $isOutside = ($item['source'] ?? '') === 'outside';
                                        $dotColor = match($item['type']) {
                                            'procedure' => 'bg-emerald-500 ring-emerald-100 dark:ring-emerald-950',
                                            'external' => 'bg-indigo-500 ring-indigo-100 dark:ring-indigo-950',
                                            'prescription' => 'bg-amber-500 ring-amber-100 dark:ring-amber-950',
                                            'appointment' => 'bg-sky-500 ring-sky-100 dark:ring-sky-950',
                                            'condition' => 'bg-cyan-500 ring-cyan-100 dark:ring-cyan-950',
                                            'voice_note' => 'bg-purple-500 ring-purple-100 dark:ring-purple-950',
                                            default => 'bg-gray-400 ring-gray-100 dark:ring-gray-950',
                                        };
                                        $clickHandler = match($item['type']) {
                                            'procedure' => "openProcedureModal({$item['id']})",
                                            'external' => "openExternalModal({$item['id']})",
                                            'prescription' => "openPrescriptionModal({$item['id']})",
                                            'appointment' => "openVisitModal({$item['id']})",
                                            'condition' => (!empty($item['tooth_number_fdi']) ? "openTooth3DModal('{$item['tooth_number_fdi']}')" : null),
                                            default => null,
                                        };
                                    @endphp
                                    <div class="relative group" wire:key="{{ $itemKey }}" x-data="{ itemExpanded: false }">
                                        {{-- Timeline Dot --}}
                                        <div class="absolute -left-[23px] top-2.5 w-3 h-3 rounded-full {{ $dotColor }} ring-4"></div>

                                        <div class="p-3 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50/60 dark:bg-gray-800/40 hover:border-primary-400 dark:hover:border-primary-600 transition-all shadow-xs">
                                            {{-- Item Header --}}
                                            <div class="flex items-start justify-between gap-2">
                                                <div class="flex items-center gap-1.5 flex-wrap">
                                                    @if(!empty($item['tooth_number_fdi']))
                                                        <button 
                                                            type="button"
                                                            wire:click.stop="openTooth3DModal('{{ $item['tooth_number_fdi'] }}')"
                                                            class="px-2 py-0.5 text-[10px] font-black rounded-md bg-primary-100 text-primary-900 dark:bg-primary-900/60 dark:text-primary-200 hover:bg-primary-200 flex items-center gap-0.5 shadow-2xs transition-transform active:scale-95"
                                                            title="Click to view Tooth in 3D">
                                                            <span>🦷 #{{ $item['tooth_number_fdi'] }}</span>
                                                        </button>
                                                    @endif

                                                    <h5 
                                                        @if($clickHandler) wire:click="{{ $clickHandler }}" @endif
                                                        class="text-xs font-bold text-gray-900 dark:text-white group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors {{ $clickHandler ? 'cursor-pointer' : '' }}">
                                                        {{ $item['title'] }}
                                                    </h5>
                                                </div>

                                                <div class="flex items-center gap-2 shrink-0">
                                                    <span class="text-[10px] font-medium text-gray-400 dark:text-gray-500 whitespace-nowrap">
                                                        {{ $item['display_date'] ?? $item['date']->format('M d, Y') }}
                                                    </span>

                                                    {{-- Expand/Collapse Inline Toggle (Instant 0ms Client-Side) --}}
                                                    <button 
                                                        type="button" 
                                                        @click="itemExpanded = !itemExpanded"
                                                        class="p-0.5 rounded text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors active:scale-90"
                                                        :title="(expandAllEntries || itemExpanded) ? 'Collapse inline details' : 'Expand inline details'">
                                                        <x-heroicon-m-chevron-down class="w-4 h-4 transition-transform duration-200" ::class="(expandAllEntries || itemExpanded) ? 'rotate-180 text-primary-500' : ''" />
                                                    </button>
                                                </div>
                                            </div>

                                            <div class="mt-1 text-[11px] text-gray-500 dark:text-gray-400 flex items-center justify-between">
                                                <span>Doctor/Source: <strong class="text-gray-700 dark:text-gray-300">{{ $item['doctor'] }}</strong></span>
                                                <div class="flex items-center gap-1.5">
                                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold {{ $isOutside ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/60 dark:text-indigo-300' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300' }}">
                                                        {{ $item['status'] }}
                                                    </span>
                                                    @if($clickHandler)
                                                        <button 
                                                            type="button" 
                                                            wire:click="{{ $clickHandler }}"
                                                            class="text-[10px] font-bold text-primary-600 dark:text-primary-400 hover:underline">
                                                            Detail &rarr;
                                                        </button>
                                                    @endif
                                                </div>
                                            </div>

                                            {{-- EXPANDABLE INLINE CLINICAL PROTOCOL DETAILS (Instant Alpine Toggle) --}}
                                            <div x-show="expandAllEntries || itemExpanded" 
                                                 x-cloak
                                                 x-transition:enter="transition ease-out duration-150"
                                                 x-transition:enter-start="opacity-0 -translate-y-1"
                                                 x-transition:enter-end="opacity-100 translate-y-0"
                                                 class="mt-2.5 pt-2.5 border-t border-gray-200/80 dark:border-gray-700/80 space-y-1.5 text-xs text-gray-700 dark:text-gray-300">
                                                    @if($item['type'] === 'procedure')
                                                        @if(!empty($item['details']['anesthetic']))
                                                            <p>💉 <strong class="text-gray-800 dark:text-gray-200">Anesthetic:</strong> {{ $item['details']['anesthetic'] }}</p>
                                                        @endif
                                                        @if(!empty($item['details']['materials']))
                                                            <p>🧪 <strong class="text-gray-800 dark:text-gray-200">Materials:</strong> {{ is_array($item['details']['materials']) ? implode(', ', $item['details']['materials']) : $item['details']['materials'] }}</p>
                                                        @endif
                                                        @if(!empty($item['details']['tools']))
                                                            <p>🔧 <strong class="text-gray-800 dark:text-gray-200">Tools:</strong> {{ is_array($item['details']['tools']) ? implode(', ', $item['details']['tools']) : $item['details']['tools'] }}</p>
                                                        @endif
                                                        @if(!empty($item['details']['hemostasis']))
                                                            <p class="text-emerald-600 dark:text-emerald-400 font-semibold">✨ {{ $item['details']['hemostasis'] }}</p>
                                                        @endif
                                                        @if(!empty($item['details']['notes']))
                                                            <p class="italic text-gray-600 dark:text-gray-400 bg-white dark:bg-gray-800/80 p-2 rounded border border-gray-100 dark:border-gray-800">
                                                                &ldquo;{{ $item['details']['notes'] }}&rdquo;
                                                            </p>
                                                        @endif
                                                        @if($clickHandler)
                                                            <button 
                                                                type="button" 
                                                                wire:click="{{ $clickHandler }}"
                                                                class="text-[10px] text-emerald-600 dark:text-emerald-400 font-bold hover:underline block mt-1">
                                                                View Complete Surgical / Clinical Record &rarr;
                                                            </button>
                                                        @endif
                                                    @elseif($item['type'] === 'appointment')
                                                        @if(!empty($item['details']['operatory']))
                                                            <p>🏢 <strong class="text-gray-800 dark:text-gray-200">Operatory:</strong> {{ $item['details']['operatory'] }}</p>
                                                        @endif
                                                        @if(!empty($item['details']['chief_complaint']))
                                                            <p class="italic text-gray-600 dark:text-gray-400 bg-white dark:bg-gray-800/80 p-2 rounded border border-gray-100 dark:border-gray-800">
                                                                Chief Complaint: &ldquo;{{ $item['details']['chief_complaint'] }}&rdquo;
                                                            </p>
                                                        @endif
                                                        @if(!empty($item['details']['notes']))
                                                            <p class="italic text-gray-500">Visit Notes: &ldquo;{{ $item['details']['notes'] }}&rdquo;</p>
                                                        @endif
                                                        @if($clickHandler)
                                                            <button 
                                                                type="button" 
                                                                wire:click="{{ $clickHandler }}"
                                                                class="text-[10px] text-sky-600 dark:text-sky-400 font-bold hover:underline block mt-1">
                                                                View Clinic Visit Record &rarr;
                                                            </button>
                                                        @endif
                                                    @elseif($item['type'] === 'condition')
                                                        <div class="flex items-center justify-between">
                                                            <span class="font-bold text-cyan-700 dark:text-cyan-300">🦷 Documented in 3D: {{ $item['details']['condition'] ?? '' }}</span>
                                                            @if(!empty($item['tooth_number_fdi']))
                                                                <button 
                                                                    type="button" 
                                                                    wire:click="openTooth3DModal('{{ $item['tooth_number_fdi'] }}')"
                                                                    class="text-[10px] text-cyan-600 dark:text-cyan-400 font-bold hover:underline">
                                                                    Inspect 3D Model &rarr;
                                                                </button>
                                                            @endif
                                                        </div>
                                                        @if(!empty($item['details']['surfaces']))
                                                            <p class="text-[11px] text-gray-500">Surfaces Involved: <strong>{{ $item['details']['surfaces'] }}</strong></p>
                                                        @endif
                                                        @if(!empty($item['details']['notes']))
                                                            <p class="italic text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800/80 p-2 rounded border border-gray-100 dark:border-gray-800">
                                                                &ldquo;{{ $item['details']['notes'] }}&rdquo;
                                                            </p>
                                                        @endif
                                                    @elseif($item['type'] === 'external')
                                                        @if(!empty($item['details']['notes']))
                                                            <p class="italic text-gray-600 dark:text-gray-400 bg-white dark:bg-gray-800/80 p-2 rounded border border-gray-100 dark:border-gray-800">
                                                                &ldquo;{{ $item['details']['notes'] }}&rdquo;
                                                            </p>
                                                        @endif
                                                        @if(!empty($item['details']['logged_by']))
                                                            <p class="text-[10px] text-gray-400">Recorded by: {{ $item['details']['logged_by'] }}</p>
                                                        @endif
                                                        @if($clickHandler)
                                                            <button 
                                                                type="button" 
                                                                wire:click="{{ $clickHandler }}"
                                                                class="text-[10px] text-indigo-600 dark:text-indigo-400 font-bold hover:underline block mt-1">
                                                                View Outside Treatment Record &rarr;
                                                            </button>
                                                        @endif
                                                    @elseif($item['type'] === 'prescription')
                                                        @if(!empty($item['details']['frequency']))
                                                            <p>⏰ <strong class="text-gray-800 dark:text-gray-200">Frequency:</strong> {{ $item['details']['frequency'] }} ({{ $item['details']['duration'] ?? 'Standard' }})</p>
                                                        @endif
                                                        @if(!empty($item['details']['instructions']))
                                                            <p class="italic text-gray-600 dark:text-gray-400 bg-white dark:bg-gray-800/80 p-2 rounded border border-gray-100 dark:border-gray-800">
                                                                Instructions: &ldquo;{{ $item['details']['instructions'] }}&rdquo;
                                                            </p>
                                                        @endif
                                                        @if($clickHandler)
                                                            <button 
                                                                type="button" 
                                                                wire:click="{{ $clickHandler }}"
                                                                class="text-[10px] text-amber-600 dark:text-amber-400 font-bold hover:underline block mt-1">
                                                                View Medication Details &rarr;
                                                            </button>
                                                        @endif
                                                    @elseif($item['type'] === 'voice_note')
                                                        <div class="flex items-center justify-between">
                                                            <a href="{{ $item['details']['share_url'] }}" target="_blank" wire:click.stop class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-purple-600 text-white hover:bg-purple-500 shadow-xs transition-colors">
                                                                <x-heroicon-m-speaker-wave class="w-4 h-4" />
                                                                <span>Listen Post-Op Audio</span>
                                                            </a>
                                                            @if(!empty($item['details']['duration']))
                                                                <span class="text-xs text-gray-500 font-semibold">⏱️ {{ $item['details']['duration'] }}</span>
                                                            @endif
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-12 text-gray-400 dark:text-gray-500 text-xs space-y-2">
                                <x-heroicon-o-funnel class="w-8 h-8 mx-auto text-gray-300 dark:text-gray-600" />
                                <p class="font-bold text-gray-700 dark:text-gray-300 text-sm">No records match your active filters</p>
                                <p class="text-gray-500 text-xs">Try adjusting your type, tooth, doctor, or keyword search.</p>
                                @if($hasActiveFilters)
                                    <button 
                                        type="button" 
                                        wire:click="resetTimelineFilters"
                                        class="mt-2 inline-flex items-center gap-1 px-3 py-1.5 text-xs font-bold text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-950/40 rounded-lg hover:underline border border-primary-200 dark:border-primary-900">
                                        <x-heroicon-m-arrow-path class="w-3.5 h-3.5" />
                                        <span>Reset All Filters</span>
                                    </button>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- COLUMN 3: FUTURE ROADMAP & ALL TREATMENT PLANS --}}
            <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm flex flex-col">
                <div class="p-4 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-map class="w-5 h-5 text-sky-500" />
                        <div>
                            <h4 class="text-sm font-bold text-gray-900 dark:text-white">Roadmap & Plans</h4>
                            <p class="text-[11px] text-gray-500 dark:text-gray-400">Next visit & all clinical treatment plans</p>
                        </div>
                    </div>
                    <span class="text-[10px] font-bold text-gray-400">
                        {{ count($allPlans) }} Plans
                    </span>
                </div>

                <div class="p-4 flex-1 space-y-4 overflow-y-auto max-h-[620px]">
                    {{-- Next Upcoming Appointment Card --}}
                    @if($roadmap['next_appointment'])
                        @php
                            $apt = $roadmap['next_appointment'];
                            $diff = Carbon\Carbon::parse($apt->starts_at)->diffForHumans();
                        @endphp
                        <div class="p-3.5 rounded-lg border-2 border-sky-300 dark:border-sky-800 bg-sky-50/50 dark:bg-sky-950/20 cursor-pointer hover:border-sky-400 transition-colors"
                             wire:click="openVisitModal({{ $apt->id }})">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-sky-800 dark:text-sky-300">
                                    Next Appointment
                                </span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-sky-200 text-sky-900 dark:bg-sky-900 dark:text-sky-200">
                                    {{ $diff }}
                                </span>
                            </div>
                            <h5 class="mt-2 text-sm font-bold text-gray-900 dark:text-white">
                                {{ Carbon\Carbon::parse($apt->starts_at)->format('l, M d, Y - h:i A') }}
                            </h5>
                            <div class="mt-1.5 space-y-1 text-xs text-gray-600 dark:text-gray-400">
                                <p>👨‍⚕️ Doctor: <strong class="text-gray-800 dark:text-gray-200">{{ $apt->doctor?->name ?: 'Staff' }}</strong></p>
                                @if($apt->operatory)
                                    <p>🚪 Room: {{ $apt->operatory->name }}</p>
                                @endif
                                @if($apt->chief_complaint)
                                    <p class="italic">&ldquo;{{ $apt->chief_complaint }}&rdquo;</p>
                                @endif
                            </div>
                        </div>
                    @else
                        <div class="p-3 rounded-lg border border-dashed border-gray-200 dark:border-gray-800 bg-gray-50/30 dark:bg-gray-800/20 text-center text-xs text-gray-400 dark:text-gray-500">
                            <p>No upcoming appointment scheduled.</p>
                        </div>
                    @endif

                    {{-- All Treatment Plans Section --}}
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <h5 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Treatment Plans List (Click for details)
                            </h5>
                        </div>

                        @forelse($allPlans as $plan)
                            @php
                                $statusColor = match($plan->status) {
                                    'completed' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-200',
                                    'active', 'in_progress' => 'bg-sky-100 text-sky-800 dark:bg-sky-900/60 dark:text-sky-200',
                                    'proposed' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-200',
                                    default => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300',
                                };
                                $totalProcs = 0;
                                foreach($plan->phases as $ph) {
                                    $totalProcs += $ph->procedures->count();
                                }
                            @endphp
                            <div class="p-3.5 rounded-lg border border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/40 hover:border-primary-400 dark:hover:border-primary-600 transition-all cursor-pointer group"
                                 wire:click="openTreatmentPlanModal({{ $plan->id }})">
                                <div class="flex items-center justify-between">
                                    <h5 class="text-xs font-bold text-gray-900 dark:text-white group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
                                        {{ $plan->title }}
                                    </h5>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $statusColor }}">
                                        {{ $plan->status }}
                                    </span>
                                </div>
                                <div class="mt-1.5 flex items-center justify-between text-[11px] text-gray-500 dark:text-gray-400">
                                    <span>Doctor: <strong class="text-gray-700 dark:text-gray-300">{{ $plan->doctor?->name ?: 'Doctor' }}</strong></span>
                                    <span>{{ $plan->phases->count() }} Phases &bull; {{ $totalProcs }} Procedures</span>
                                </div>
                                @if($plan->total_cost)
                                    <div class="mt-2 pt-2 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between text-[11px]">
                                        <span class="text-gray-400">Total Cost:</span>
                                        <strong class="text-emerald-600 dark:text-emerald-400">${{ number_format($plan->total_cost, 2) }}</strong>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="p-3 rounded-lg border border-dashed border-gray-200 dark:border-gray-800 bg-gray-50/30 dark:bg-gray-800/20 text-center text-xs text-gray-400 dark:text-gray-500">
                                <p>No treatment plans recorded yet.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        {{-- 5. DETAIL POPUP MODALS --}}

        {{-- A. TREATMENT PLAN MODAL --}}
        @if($activeModal === 'plan' && !empty($modalData))
            <div class="fixed inset-0 z-50 overflow-y-auto bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl max-w-2xl w-full shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-150 flex flex-col max-h-[85vh]">
                    <div class="p-5 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between bg-gray-50/50 dark:bg-gray-800/40">
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-base font-bold text-gray-900 dark:text-white">
                                    {{ $modalData['title'] }}
                                </h3>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-primary-100 text-primary-800 dark:bg-primary-900/60 dark:text-primary-200">
                                    {{ $modalData['status'] }}
                                </span>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                Supervising Doctor: <strong>{{ $modalData['doctor'] }}</strong> &bull; Created: {{ $modalData['created_at'] }}
                            </p>
                        </div>
                        <button type="button" wire:click="closeModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 p-1">
                            <x-heroicon-m-x-mark class="w-6 h-6" />
                        </button>
                    </div>

                    <div class="p-5 overflow-y-auto space-y-4 flex-1">
                        @if(!empty($modalData['phases']))
                            @foreach($modalData['phases'] as $phase)
                                <div class="p-3.5 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50/40 dark:bg-gray-800/30">
                                    <div class="flex items-center justify-between pb-2 border-b border-gray-200/60 dark:border-gray-700/60">
                                        <h5 class="text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider">
                                            {{ $phase['name'] }}
                                        </h5>
                                        <span class="text-[10px] font-semibold text-gray-500 uppercase">{{ $phase['status'] }}</span>
                                    </div>

                                    <div class="mt-2.5 space-y-2">
                                        @forelse($phase['procedures'] as $proc)
                                            <div class="p-2.5 rounded-lg border border-gray-100 dark:border-gray-800 bg-white dark:bg-gray-900 flex items-start justify-between gap-3">
                                                <div class="space-y-1">
                                                    <div class="flex items-center gap-2">
                                                        @if(!empty($proc['tooth_number_fdi']))
                                                            <button 
                                                                type="button" 
                                                                wire:click="openTooth3DModal('{{ $proc['tooth_number_fdi'] }}')"
                                                                class="px-2 py-0.5 text-xs font-bold rounded bg-primary-100 text-primary-800 dark:bg-primary-900 dark:text-primary-200 hover:bg-primary-200">
                                                                🦷 #{{ $proc['tooth_number_fdi'] }}
                                                            </button>
                                                        @endif
                                                        <span class="text-xs font-bold text-gray-900 dark:text-white">{{ $proc['title'] }}</span>
                                                        @if(!empty($proc['code']))
                                                            <span class="text-[10px] font-mono text-gray-400">({{ $proc['code'] }})</span>
                                                        @endif
                                                    </div>
                                                    @if(!empty($proc['anesthetic']))
                                                        <p class="text-[11px] text-emerald-600 dark:text-emerald-400">💉 Anesthetic: {{ $proc['anesthetic'] }}</p>
                                                    @endif
                                                    @if(!empty($proc['notes']))
                                                        <p class="text-[11px] text-gray-500 italic">&ldquo;{{ $proc['notes'] }}&rdquo;</p>
                                                    @endif
                                                </div>

                                                <div class="text-right shrink-0">
                                                    <span class="text-[10px] uppercase font-bold px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                                                        {{ $proc['status'] }}
                                                    </span>
                                                    @if(!empty($proc['cost']))
                                                        <span class="block text-xs font-bold text-emerald-600 mt-1">${{ number_format($proc['cost'], 2) }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                        @empty
                                            <p class="text-xs text-gray-400 italic">No procedures recorded in this phase.</p>
                                        @endforelse
                                    </div>
                                </div>
                            @endforeach
                        @endif
                    </div>

                    <div class="p-4 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between bg-gray-50/50 dark:bg-gray-800/40">
                        @if(!empty($modalData['total_cost']))
                            <div>
                                <span class="text-xs text-gray-400">Total Plan Cost:</span>
                                <strong class="text-sm font-black text-emerald-600 ml-1">${{ number_format($modalData['total_cost'], 2) }}</strong>
                            </div>
                        @else
                            <div></div>
                        @endif
                        <button type="button" wire:click="closeModal" class="px-4 py-2 text-xs font-bold text-gray-700 dark:text-gray-300 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 rounded-xl transition-colors">
                            Close Details
                        </button>
                    </div>
                </div>
            </div>
        @endif

        {{-- B. PROCEDURE DETAILS MODAL --}}
        @if($activeModal === 'procedure' && !empty($modalData))
            <div class="fixed inset-0 z-50 overflow-y-auto bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl max-w-lg w-full shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-150">
                    <div class="p-5 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between bg-gray-50/50 dark:bg-gray-800/40">
                        <div class="flex items-center gap-2">
                            @if(!empty($modalData['tooth_number_fdi']))
                                <button 
                                    type="button" 
                                    wire:click="openTooth3DModal('{{ $modalData['tooth_number_fdi'] }}')"
                                    class="px-2.5 py-1 text-xs font-bold rounded-lg bg-primary-100 text-primary-800 dark:bg-primary-900/60 dark:text-primary-200 hover:bg-primary-200 flex items-center gap-1"
                                    title="Focus in 3D Odontogram">
                                    <span>🦷 Tooth #{{ $modalData['tooth_number_fdi'] }}</span>
                                    <x-heroicon-m-sparkles class="w-3.5 h-3.5 text-primary-600" />
                                </button>
                            @endif
                            <h3 class="text-sm font-bold text-gray-900 dark:text-white">
                                {{ $modalData['title'] }}
                            </h3>
                        </div>
                        <button type="button" wire:click="closeModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 p-1">
                            <x-heroicon-m-x-mark class="w-5 h-5" />
                        </button>
                    </div>

                    <div class="p-5 space-y-3.5 text-xs text-gray-700 dark:text-gray-300">
                        <div class="grid grid-cols-2 gap-3 p-3 rounded-xl bg-gray-50/70 dark:bg-gray-800/40 border border-gray-100 dark:border-gray-800">
                            <div>
                                <span class="text-[10px] text-gray-400 uppercase font-bold block">Status</span>
                                <span class="font-bold text-emerald-600 dark:text-emerald-400 uppercase">{{ $modalData['status'] }}</span>
                            </div>
                            <div>
                                <span class="text-[10px] text-gray-400 uppercase font-bold block">Doctor</span>
                                <strong class="text-gray-900 dark:text-white">{{ $modalData['doctor'] }}</strong>
                            </div>
                            @if(!empty($modalData['completed_at']))
                                <div class="col-span-2">
                                    <span class="text-[10px] text-gray-400 uppercase font-bold block">Executed Date</span>
                                    <span>{{ $modalData['completed_at'] }}</span>
                                </div>
                            @endif
                        </div>

                        {{-- Clinical Details --}}
                        <div class="space-y-2">
                            @if(!empty($modalData['anesthetic_agent']))
                                <div class="p-2.5 rounded-lg bg-emerald-50/60 dark:bg-emerald-950/20 border border-emerald-100 dark:border-emerald-900/50">
                                    <strong class="text-emerald-800 dark:text-emerald-300 block mb-0.5">💉 Anesthesia Protocol</strong>
                                    <span>{{ $modalData['anesthetic_agent'] }} @if(!empty($modalData['anesthetic_dosage'])) ({{ $modalData['anesthetic_dosage'] }}) @endif @if(!empty($modalData['anesthetic_technique'])) &bull; {{ $modalData['anesthetic_technique'] }} @endif</span>
                                </div>
                            @endif

                            @if(!empty($modalData['materials_used']))
                                <div class="p-2.5 rounded-lg bg-sky-50/60 dark:bg-sky-950/20 border border-sky-100 dark:border-sky-900/50">
                                    <strong class="text-sky-800 dark:text-sky-300 block mb-0.5">🧪 Materials Applied</strong>
                                    <span>{{ $modalData['materials_used'] }}</span>
                                </div>
                            @endif

                            @if(!empty($modalData['tools_used']))
                                <div class="p-2.5 rounded-lg bg-purple-50/60 dark:bg-purple-950/20 border border-purple-100 dark:border-purple-900/50">
                                    <strong class="text-purple-800 dark:text-purple-300 block mb-0.5">🛠️ Instruments & Handpieces</strong>
                                    <span>{{ $modalData['tools_used'] }}</span>
                                </div>
                            @endif

                            @if(!empty($modalData['hemostasis']))
                                <div class="p-2.5 rounded-lg bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-900 text-emerald-800 dark:text-emerald-300 font-bold">
                                    ✨ {{ $modalData['hemostasis'] }}
                                </div>
                            @endif

                            @if(!empty($modalData['notes']))
                                <div>
                                    <strong class="text-gray-500 block mb-1">Chairside Observations & Notes</strong>
                                    <p class="p-3 rounded-lg bg-gray-50 dark:bg-gray-800/60 border border-gray-100 dark:border-gray-800 italic">
                                        &ldquo;{{ $modalData['notes'] }}&rdquo;
                                    </p>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="p-4 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between bg-gray-50/50 dark:bg-gray-800/40">
                        @if(!empty($modalData['tooth_number_fdi']))
                            <button 
                                type="button" 
                                wire:click="openTooth3DModal('{{ $modalData['tooth_number_fdi'] }}')"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-white bg-primary-600 hover:bg-primary-500 rounded-xl transition-colors">
                                <x-heroicon-m-sparkles class="w-4 h-4" />
                                <span>Inspect in 3D Model</span>
                            </button>
                        @else
                            <div></div>
                        @endif
                        <button type="button" wire:click="closeModal" class="px-4 py-1.5 text-xs font-bold text-gray-700 dark:text-gray-300 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 rounded-xl transition-colors">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        @endif

        {{-- C. PRESCRIPTION DETAILS MODAL --}}
        @if($activeModal === 'prescription' && !empty($modalData))
            <div class="fixed inset-0 z-50 overflow-y-auto bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl max-w-lg w-full shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-150">
                    <div class="p-5 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between bg-gray-50/50 dark:bg-gray-800/40">
                        <div class="flex items-center gap-2">
                            <x-heroicon-o-document-text class="w-5 h-5 text-amber-500" />
                            <h3 class="text-sm font-bold text-gray-900 dark:text-white">
                                Prescription Record &bull; {{ $modalData['issue_date'] }}
                            </h3>
                        </div>
                        <button type="button" wire:click="closeModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 p-1">
                            <x-heroicon-m-x-mark class="w-5 h-5" />
                        </button>
                    </div>

                    <div class="p-5 space-y-4 text-xs text-gray-700 dark:text-gray-300">
                        <div class="flex items-center justify-between text-xs text-gray-500">
                            <span>Prescribing Doctor: <strong class="text-gray-900 dark:text-white">{{ $modalData['doctor'] }}</strong></span>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-200 uppercase">
                                {{ $modalData['status'] }}
                            </span>
                        </div>

                        <div>
                            <strong class="text-xs font-bold text-gray-900 dark:text-white block mb-2">Medications Schedule</strong>
                            <div class="space-y-2">
                                @forelse($modalData['medications'] as $med)
                                    <div class="p-3 rounded-lg border border-gray-100 dark:border-gray-800 bg-gray-50/60 dark:bg-gray-800/40">
                                        <div class="font-bold text-gray-900 dark:text-white text-xs">
                                            {{ is_array($med) ? ($med['name'] ?? 'Medication') : $med }}
                                        </div>
                                        @if(is_array($med))
                                            <div class="text-[11px] text-gray-500 mt-1 space-y-0.5">
                                                @if(!empty($med['dosage'])) <div>Dosage: <strong>{{ $med['dosage'] }}</strong></div> @endif
                                                @if(!empty($med['frequency'])) <div>Frequency: <strong>{{ $med['frequency'] }}</strong></div> @endif
                                                @if(!empty($med['duration'])) <div>Duration: <strong>{{ $med['duration'] }}</strong></div> @endif
                                            </div>
                                        @endif
                                    </div>
                                @empty
                                    <p class="text-xs text-gray-400 italic">No specific medications itemized.</p>
                                @endforelse
                            </div>
                        </div>

                        @if(!empty($modalData['instructions']))
                            <div>
                                <strong class="text-xs font-bold text-gray-900 dark:text-white block mb-1">Doctor Instructions</strong>
                                <p class="p-3 rounded-lg bg-amber-50/50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-900/50 text-amber-900 dark:text-amber-200">
                                    {{ $modalData['instructions'] }}
                                </p>
                            </div>
                        @endif
                    </div>

                    <div class="p-4 border-t border-gray-100 dark:border-gray-800 flex items-center justify-end bg-gray-50/50 dark:bg-gray-800/40">
                        <button type="button" wire:click="closeModal" class="px-4 py-1.5 text-xs font-bold text-gray-700 dark:text-gray-300 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 rounded-xl transition-colors">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        @endif

        {{-- D. EXTERNAL TREATMENT DETAILS MODAL --}}
        @if($activeModal === 'external' && !empty($modalData))
            <div class="fixed inset-0 z-50 overflow-y-auto bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl max-w-md w-full shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-150">
                    <div class="p-5 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between bg-gray-50/50 dark:bg-gray-800/40">
                        <div class="flex items-center gap-2">
                            @if(!empty($modalData['tooth_number_fdi']))
                                <button 
                                    type="button" 
                                    wire:click="openTooth3DModal('{{ $modalData['tooth_number_fdi'] }}')"
                                    class="px-2.5 py-1 text-xs font-bold rounded-lg bg-indigo-100 text-indigo-800 dark:bg-indigo-900/60 dark:text-indigo-200 hover:bg-indigo-200 flex items-center gap-1"
                                    title="Focus in 3D Odontogram">
                                    <span>🦷 #{{ $modalData['tooth_number_fdi'] }}</span>
                                    <x-heroicon-m-sparkles class="w-3.5 h-3.5 text-indigo-600" />
                                </button>
                            @endif
                            <h3 class="text-sm font-bold text-gray-900 dark:text-white">
                                {{ $modalData['procedure_title'] }}
                            </h3>
                        </div>
                        <button type="button" wire:click="closeModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 p-1">
                            <x-heroicon-m-x-mark class="w-5 h-5" />
                        </button>
                    </div>

                    <div class="p-5 space-y-3.5 text-xs text-gray-700 dark:text-gray-300">
                        <div class="space-y-1 text-xs">
                            <div class="flex items-center justify-between py-1 border-b border-gray-100 dark:border-gray-800">
                                <span class="text-gray-400">Performed Year:</span>
                                <strong>{{ $modalData['performed_year'] ?: 'Not Specified' }}</strong>
                            </div>
                            <div class="flex items-center justify-between py-1 border-b border-gray-100 dark:border-gray-800">
                                <span class="text-gray-400">External Clinic / Doctor:</span>
                                <strong>{{ $modalData['clinic_or_doctor_name'] ?: 'Not Specified' }}</strong>
                            </div>
                            <div class="flex items-center justify-between py-1 border-b border-gray-100 dark:border-gray-800">
                                <span class="text-gray-400">Logged By Staff:</span>
                                <strong>{{ $modalData['created_by'] }} ({{ $modalData['created_at'] }})</strong>
                            </div>
                        </div>

                        @if(!empty($modalData['notes']))
                            <div>
                                <strong class="text-gray-500 block mb-1">Clinical Observations & Margin Fit</strong>
                                <p class="p-3 rounded-lg bg-gray-50 dark:bg-gray-800/60 border border-gray-100 dark:border-gray-800 italic">
                                    &ldquo;{{ $modalData['notes'] }}&rdquo;
                                </p>
                            </div>
                        @endif
                    </div>

                    <div class="p-4 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between bg-gray-50/50 dark:bg-gray-800/40">
                        @if(!empty($modalData['tooth_number_fdi']))
                            <button 
                                type="button" 
                                wire:click="openTooth3DModal('{{ $modalData['tooth_number_fdi'] }}')"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-500 rounded-xl transition-colors">
                                <x-heroicon-m-sparkles class="w-4 h-4" />
                                <span>Inspect in 3D Model</span>
                            </button>
                        @else
                            <div></div>
                        @endif
                        <button type="button" wire:click="closeModal" class="px-4 py-1.5 text-xs font-bold text-gray-700 dark:text-gray-300 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 rounded-xl transition-colors">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        @endif

        {{-- E. CLINIC VISIT DETAILS MODAL --}}
        @if($activeModal === 'visit' && !empty($modalData))
            <div class="fixed inset-0 z-50 overflow-y-auto bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl max-w-md w-full shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-150">
                    <div class="p-5 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between bg-gray-50/50 dark:bg-gray-800/40">
                        <div class="flex items-center gap-2">
                            <x-heroicon-o-calendar-days class="w-5 h-5 text-sky-500" />
                            <h3 class="text-sm font-bold text-gray-900 dark:text-white">
                                Clinic Visit Details &bull; {{ $modalData['type'] }}
                            </h3>
                        </div>
                        <button type="button" wire:click="closeModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 p-1">
                            <x-heroicon-m-x-mark class="w-5 h-5" />
                        </button>
                    </div>

                    <div class="p-5 space-y-3 text-xs text-gray-700 dark:text-gray-300">
                        <div class="p-3 rounded-xl bg-sky-50/60 dark:bg-sky-950/20 border border-sky-100 dark:border-sky-900/50 space-y-1.5">
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500">Date & Time:</span>
                                <strong>{{ $modalData['starts_at'] }}</strong>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500">Doctor:</span>
                                <strong>{{ $modalData['doctor'] }}</strong>
                            </div>
                            @if(!empty($modalData['operatory']))
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-500">Operatory:</span>
                                    <strong>{{ $modalData['operatory'] }}</strong>
                                </div>
                            @endif
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500">Status:</span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-sky-200 text-sky-900 dark:bg-sky-900 dark:text-sky-200">{{ $modalData['status'] }}</span>
                            </div>
                        </div>

                        @if(!empty($modalData['chief_complaint']))
                            <div>
                                <strong class="text-gray-500 block mb-1">Chief Complaint</strong>
                                <p class="p-2.5 rounded-lg bg-gray-50 dark:bg-gray-800/60 border border-gray-100 dark:border-gray-800 italic">
                                    &ldquo;{{ $modalData['chief_complaint'] }}&rdquo;
                                </p>
                            </div>
                        @endif

                        @if(!empty($modalData['notes']))
                            <div>
                                <strong class="text-gray-500 block mb-1">Doctor Visit Notes</strong>
                                <p class="p-2.5 rounded-lg bg-gray-50 dark:bg-gray-800/60 border border-gray-100 dark:border-gray-800">
                                    {{ $modalData['notes'] }}
                                </p>
                            </div>
                        @endif
                    </div>

                    <div class="p-4 border-t border-gray-100 dark:border-gray-800 flex items-center justify-end bg-gray-50/50 dark:bg-gray-800/40">
                        <button type="button" wire:click="closeModal" class="px-4 py-1.5 text-xs font-bold text-gray-700 dark:text-gray-300 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 rounded-xl transition-colors">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        @endif

        {{-- F. 3D TOOTH FOCUS MODAL --}}
        @if($activeModal === 'tooth_3d' && !empty($modalData))
            <div class="fixed inset-0 z-50 overflow-y-auto bg-black/70 backdrop-blur-md flex items-center justify-center p-4">
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl max-w-3xl w-full shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-150 flex flex-col max-h-[90vh]">
                    <div class="p-5 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between bg-gradient-to-r from-slate-900 to-indigo-950 text-white">
                        <div class="flex items-center gap-3">
                            <span class="px-3 py-1 rounded-xl bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 text-sm font-black font-mono">
                                🦷 Tooth #{{ $modalData['tooth'] }}
                            </span>
                            <div>
                                <h3 class="text-sm font-bold text-white">
                                    3D Tooth Inspection & Clinical Record
                                </h3>
                                <p class="text-[11px] text-indigo-200/80">
                                    Cross-referenced with 3D anatomical odontogram
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <a 
                                href="{{ route('patient.odontogram', $record?->id ?? 1) }}?tooth={{ $modalData['tooth'] }}&isolate=1" 
                                target="_blank"
                                class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-500 rounded-xl transition-colors shadow-sm">
                                <x-heroicon-m-arrows-pointing-out class="w-3.5 h-3.5" />
                                <span>Fullscreen 3D</span>
                            </a>
                            <button type="button" wire:click="closeModal" class="text-gray-400 hover:text-white p-1">
                                <x-heroicon-m-x-mark class="w-6 h-6" />
                            </button>
                        </div>
                    </div>

                    <div class="p-5 overflow-y-auto space-y-4 flex-1">
                        {{-- 3D Odontogram Frame Focused on this Tooth --}}
                        <div class="w-full h-72 rounded-xl overflow-hidden border border-gray-200 dark:border-gray-800 bg-slate-950 relative shadow-inner">
                            <iframe 
                                src="{{ route('patient.odontogram', $record?->id ?? 1) }}?tooth={{ $modalData['tooth'] }}&isolate=1&dark=1"
                                class="w-full h-full border-none"
                                allowfullscreen>
                            </iframe>
                        </div>

                        {{-- Quick 1-Click Tooth Condition Marker (Crown, Implant, RCT, Caries, etc.) --}}
                        <div class="p-3.5 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50/80 dark:bg-gray-800/60 shadow-xs">
                            <div class="flex items-center justify-between mb-2.5">
                                <span class="text-xs font-bold uppercase tracking-wider text-gray-900 dark:text-white flex items-center gap-1.5">
                                    <x-heroicon-m-sparkles class="w-4 h-4 text-amber-500" />
                                    <span>Quick Mark / Change Condition</span>
                                </span>
                                <span class="text-[10px] text-gray-500 dark:text-gray-400">
                                    Dual-syncs to 3D Odontogram & Medical History
                                </span>
                            </div>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                @php
                                    $currCond = $modalData['condition_raw'] ?? 'healthy';
                                    $condOptions = [
                                        ['key' => 'crown', 'label' => 'Crown', 'icon' => '👑', 'bg' => 'bg-amber-500/10 text-amber-700 dark:text-amber-300 border-amber-300 dark:border-amber-800', 'active' => 'ring-2 ring-amber-600 bg-amber-600 text-white dark:bg-amber-600 dark:text-white font-black shadow-md shadow-amber-600/30'],
                                        ['key' => 'root_canal', 'label' => 'Root Canal', 'icon' => '⚡', 'bg' => 'bg-purple-500/10 text-purple-700 dark:text-purple-300 border-purple-300 dark:border-purple-800', 'active' => 'ring-2 ring-purple-600 bg-purple-600 text-white dark:bg-purple-600 dark:text-white font-black shadow-md shadow-purple-600/30'],
                                        ['key' => 'active_caries', 'label' => 'Caries', 'icon' => '🔴', 'bg' => 'bg-rose-500/10 text-rose-700 dark:text-rose-300 border-rose-300 dark:border-rose-800', 'active' => 'ring-2 ring-rose-600 bg-rose-600 text-white dark:bg-rose-600 dark:text-rose-300 font-black shadow-md shadow-rose-600/30'],
                                        ['key' => 'implant', 'label' => 'Implant', 'icon' => '🔩', 'bg' => 'bg-cyan-500/10 text-cyan-700 dark:text-cyan-300 border-cyan-300 dark:border-cyan-800', 'active' => 'ring-2 ring-cyan-600 bg-cyan-600 text-white dark:bg-cyan-600 dark:text-white font-black shadow-md shadow-cyan-600/30'],
                                        ['key' => 'composite_filled', 'label' => 'Composite', 'icon' => '🔵', 'bg' => 'bg-blue-500/10 text-blue-700 dark:text-blue-300 border-blue-300 dark:border-blue-800', 'active' => 'ring-2 ring-blue-600 bg-blue-600 text-white dark:bg-blue-600 dark:text-white font-black shadow-md shadow-blue-600/30'],
                                        ['key' => 'missing', 'label' => 'Missing', 'icon' => '⚪', 'bg' => 'bg-slate-500/10 text-slate-700 dark:text-slate-300 border-slate-300 dark:border-slate-800', 'active' => 'ring-2 ring-slate-600 bg-slate-600 text-white dark:bg-slate-600 dark:text-white font-black shadow-md shadow-slate-600/30'],
                                        ['key' => 'healthy', 'label' => 'Sound / Intact', 'icon' => '🟢', 'bg' => 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border-emerald-300 dark:border-emerald-800', 'active' => 'ring-2 ring-emerald-600 bg-emerald-600 text-white dark:bg-emerald-600 dark:text-white font-black shadow-md shadow-emerald-600/30'],
                                        ['key' => 'custom', 'label' => 'Custom', 'icon' => '📝', 'bg' => 'bg-indigo-500/10 text-indigo-700 dark:text-indigo-300 border-indigo-300 dark:border-indigo-800', 'active' => 'ring-2 ring-indigo-600 bg-indigo-600 text-white dark:bg-indigo-600 dark:text-white font-black shadow-md shadow-indigo-600/30'],
                                    ];
                                @endphp
                                @foreach($condOptions as $opt)
                                    @php $isSelected = ($currCond === $opt['key']); @endphp
                                    <button 
                                        type="button"
                                        wire:click="updateToothCondition('{{ $modalData['tooth'] }}', '{{ $opt['key'] }}')"
                                        wire:loading.attr="disabled"
                                        class="inline-flex items-center justify-center gap-1.5 px-2.5 py-1.5 text-xs rounded-xl border transition-all active:scale-95 shadow-xs
                                            {{ $isSelected ? $opt['active'] : ($opt['bg'] . ' hover:bg-white dark:hover:bg-gray-700') }}">
                                        <span>{{ $opt['icon'] }}</span>
                                        <span>{{ $opt['label'] }}</span>
                                        @if($isSelected)
                                            <span class="text-[10px] ml-0.5 font-black">✓</span>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        {{-- Active 3D Anatomical Condition & Findings Banner --}}
                        @if(!empty($modalData['current_condition']) && ($modalData['condition_raw'] ?? '') !== 'healthy')
                            <div class="p-3.5 rounded-xl border border-cyan-300 dark:border-cyan-800 bg-cyan-50/70 dark:bg-cyan-950/30 flex items-start justify-between gap-3">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="px-2.5 py-0.5 rounded-lg text-xs font-black uppercase tracking-tight bg-cyan-200 text-cyan-900 dark:bg-cyan-900 dark:text-cyan-200 shadow-xs">
                                            {{ $modalData['current_condition'] }}
                                        </span>
                                        <span class="text-xs font-bold text-gray-900 dark:text-white">Active Condition Documented in 3D Model</span>
                                    </div>
                                    @if(!empty($modalData['surfaces']))
                                        <div class="mt-1.5 text-xs text-gray-600 dark:text-gray-300">
                                            Surfaces: <strong>{{ is_array($modalData['surfaces']) ? implode(', ', $modalData['surfaces']) : $modalData['surfaces'] }}</strong>
                                        </div>
                                    @endif
                                    @if(!empty($modalData['tooth_notes']))
                                        <p class="mt-1 text-xs text-gray-700 dark:text-gray-300 italic">&ldquo;{{ $modalData['tooth_notes'] }}&rdquo;</p>
                                    @endif
                                </div>
                                <span class="text-[10px] font-bold text-cyan-700 dark:text-cyan-400 uppercase tracking-wider shrink-0 bg-white/80 dark:bg-gray-800 px-2 py-0.5 rounded">
                                    3D Odontogram
                                </span>
                            </div>
                        @endif

                        {{-- Inside Clinic Procedures Done on this Tooth --}}
                        <div>
                            <h5 class="text-xs font-bold uppercase tracking-wider text-gray-900 dark:text-white mb-2 flex items-center gap-1.5">
                                <x-heroicon-o-check-circle class="w-4 h-4 text-emerald-500" />
                                <span>Inside Clinic Procedures on Tooth #{{ $modalData['tooth'] }} ({{ count($modalData['inside_procedures'] ?? []) }})</span>
                            </h5>
                            <div class="space-y-2">
                                @forelse($modalData['inside_procedures'] as $proc)
                                    <div class="p-3 rounded-lg border border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/40 flex items-start justify-between gap-3">
                                        <div class="space-y-1">
                                            <div class="font-bold text-xs text-gray-900 dark:text-white">
                                                {{ $proc->procedureCode?->title ?: 'Clinical Procedure' }}
                                            </div>
                                            <div class="text-[11px] text-gray-500">
                                                Doctor: {{ $proc->doctor?->name ?: 'Clinic Doctor' }} &bull; {{ $proc->completed_at ? \Illuminate\Support\Carbon::parse($proc->completed_at)->format('M d, Y') : 'In Plan' }}
                                            </div>
                                            @if($proc->anesthetic_agent || $proc->anesthetic_dosage || $proc->anesthetic_technique)
                                                <div class="text-[11px] text-emerald-700 dark:text-emerald-400 font-medium">
                                                    💉 Anesthetic: {{ implode(' &bull; ', array_filter([$proc->anesthetic_agent, $proc->anesthetic_dosage, $proc->anesthetic_technique])) }}
                                                </div>
                                            @endif
                                            @if(!empty($proc->materials_used_json))
                                                <div class="text-[11px] text-blue-700 dark:text-blue-400">
                                                    🧪 Materials: {{ is_array($proc->materials_used_json) ? implode(', ', $proc->materials_used_json) : $proc->materials_used_json }}
                                                </div>
                                            @endif
                                            @if(!empty($proc->tools_used_json))
                                                <div class="text-[11px] text-amber-700 dark:text-amber-400">
                                                    🔧 Tools: {{ is_array($proc->tools_used_json) ? implode(', ', $proc->tools_used_json) : $proc->tools_used_json }}
                                                </div>
                                            @endif
                                            @if($proc->hemostasis_achieved)
                                                <div class="text-[10px] text-teal-600 dark:text-teal-400 font-semibold">
                                                    ✨ Hemostasis Achieved
                                                </div>
                                            @endif
                                            @if($proc->chairside_notes)
                                                <p class="text-[11px] text-gray-600 dark:text-gray-300 italic">&ldquo;{{ $proc->chairside_notes }}&rdquo;</p>
                                            @endif
                                        </div>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-200 shrink-0">
                                            {{ $proc->status }}
                                        </span>
                                    </div>
                                @empty
                                    <p class="text-xs text-gray-400 italic">No procedures completed in our clinic for this tooth yet.</p>
                                @endforelse
                            </div>
                        </div>

                        {{-- Outside Clinic Treatments on this Tooth --}}
                        <div>
                            <h5 class="text-xs font-bold uppercase tracking-wider text-gray-900 dark:text-white mb-2 flex items-center gap-1.5">
                                <x-heroicon-o-building-office-2 class="w-4 h-4 text-indigo-500" />
                                <span>Outside / Prior Treatments on Tooth #{{ $modalData['tooth'] }} ({{ count($modalData['outside_treatments'] ?? []) }})</span>
                            </h5>
                            <div class="space-y-2">
                                @forelse($modalData['outside_treatments'] as $ext)
                                    <div class="p-3 rounded-lg border border-gray-100 dark:border-gray-800 bg-indigo-50/30 dark:bg-indigo-950/20 flex items-start justify-between">
                                        <div>
                                            <div class="font-bold text-xs text-gray-900 dark:text-white">
                                                {{ $ext->procedure_title }}
                                            </div>
                                            <div class="text-[11px] text-gray-500 mt-0.5">
                                                {{ $ext->clinic_or_doctor_name ?: 'Outside Clinic' }} &bull; Year: {{ $ext->performed_year ?: 'Unspecified' }}
                                            </div>
                                            @if($ext->notes)
                                                <p class="text-[11px] text-gray-600 dark:text-gray-300 italic mt-1">&ldquo;{{ $ext->notes }}&rdquo;</p>
                                            @endif
                                        </div>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-indigo-100 text-indigo-800 dark:bg-indigo-900/60 dark:text-indigo-200">
                                            External
                                        </span>
                                    </div>
                                @empty
                                    <p class="text-xs text-gray-400 italic">No outside treatments recorded for this tooth.</p>
                                @endforelse
                            </div>
                        </div>

                        {{-- Post-Op WhatsApp Voice Notes for this Tooth --}}
                        @if(!empty($modalData['voice_notes']) && count($modalData['voice_notes']) > 0)
                            <div>
                                <h5 class="text-xs font-bold uppercase tracking-wider text-gray-900 dark:text-white mb-2 flex items-center gap-1.5">
                                    <x-heroicon-o-speaker-wave class="w-4 h-4 text-purple-500" />
                                    <span>Post-Op Voice Instructions for Tooth #{{ $modalData['tooth'] }} ({{ count($modalData['voice_notes']) }})</span>
                                </h5>
                                <div class="space-y-2">
                                    @foreach($modalData['voice_notes'] as $vn)
                                        <div class="p-3 rounded-lg border border-purple-100 dark:border-purple-900/50 bg-purple-50/30 dark:bg-purple-950/20 flex items-center justify-between">
                                            <div>
                                                <strong class="text-xs font-bold text-gray-900 dark:text-white block">{{ $vn->title }}</strong>
                                                <span class="text-[11px] text-gray-500">Sent via WhatsApp &bull; {{ $vn->created_at->format('M d, Y') }}</span>
                                            </div>
                                            <a href="{{ url('/voice-notes/stream/' . $vn->share_token) }}" target="_blank" class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold rounded-lg bg-purple-600 text-white hover:bg-purple-500">
                                                <x-heroicon-m-speaker-wave class="w-3.5 h-3.5" />
                                                <span>Listen</span>
                                            </a>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="p-4 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between bg-gray-50/50 dark:bg-gray-800/40">
                        <button 
                            type="button" 
                            wire:click="openAddExternalModal"
                            class="text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:underline">
                            + Log Outside Work for Tooth #{{ $modalData['tooth'] }}
                        </button>
                        <button type="button" wire:click="closeModal" class="px-4 py-1.5 text-xs font-bold text-gray-700 dark:text-gray-300 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 rounded-xl transition-colors">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        @endif

        {{-- G. QUICK-ADD EXTERNAL TREATMENT MODAL --}}
        @if($activeModal === 'external_add')
            <div class="fixed inset-0 z-50 overflow-y-auto bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl max-w-md w-full shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-150">
                    <div class="p-4 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between bg-gray-50/50 dark:bg-gray-800/40">
                        <h4 class="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <x-heroicon-m-plus-circle class="w-5 h-5 text-indigo-500" />
                            <span>Log External Dental Treatment</span>
                        </h4>
                        <button 
                            type="button"
                            wire:click="closeAddExternalModal"
                            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                            <x-heroicon-m-x-mark class="w-5 h-5" />
                        </button>
                    </div>

                    <form wire:submit.prevent="saveExternalTreatment" class="p-4 space-y-3.5">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">
                                Procedure Title <span class="text-rose-500">*</span>
                            </label>
                            <input 
                                type="text" 
                                wire:model="extProcedureTitle"
                                placeholder="e.g. Porcelain Crown, Titanium Implant, Root Canal, Composite Filling"
                                class="w-full text-xs rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-primary-500 focus:border-primary-500 p-2.5"
                                required />
                            @error('extProcedureTitle') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">
                                    Tooth # (FDI: 11-85)
                                </label>
                                <input 
                                    type="number" 
                                    wire:model="extToothNumber"
                                    placeholder="e.g. 16, 21, 36"
                                    min="11" max="85"
                                    class="w-full text-xs rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-primary-500 focus:border-primary-500 p-2.5" />
                                @error('extToothNumber') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">
                                    Performed Year
                                </label>
                                <input 
                                    type="text" 
                                    wire:model="extPerformedYear"
                                    placeholder="e.g. 2022"
                                    class="w-full text-xs rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-primary-500 focus:border-primary-500 p-2.5" />
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">
                                Outside Clinic / Doctor Name
                            </label>
                            <input 
                                type="text" 
                                wire:model="extClinicOrDoctor"
                                placeholder="e.g. Dr. Ahmed Clinic, Cairo Center"
                                class="w-full text-xs rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-primary-500 focus:border-primary-500 p-2.5" />
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">
                                Clinical Notes / Observations
                            </label>
                            <textarea 
                                wire:model="extNotes"
                                rows="3"
                                placeholder="Add observations on crown margin, existing peri-implant bone, previous endodontic obturation, etc."
                                class="w-full text-xs rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-primary-500 focus:border-primary-500 p-2.5"></textarea>
                        </div>

                        <div class="pt-2 flex items-center justify-end gap-2 border-t border-gray-100 dark:border-gray-800">
                            <button 
                                type="button"
                                wire:click="closeAddExternalModal"
                                class="px-3.5 py-2 text-xs font-semibold text-gray-700 dark:text-gray-300 bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 rounded-xl transition-colors">
                                Cancel
                            </button>
                            <button 
                                type="submit"
                                class="px-4 py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-md transition-colors">
                                Save External Record
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
</x-filament-widgets::widget>
