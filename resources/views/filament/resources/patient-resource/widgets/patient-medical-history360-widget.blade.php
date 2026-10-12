<x-filament-widgets::widget>
    @php
        $risks = $this->getMedicalRiskAssessment();
        $externalTreatments = $this->getExternalTreatments();
        $timeline = $this->getClinicJourneyTimeline();
        $roadmap = $this->getFutureRoadmap();
    @endphp

    <div class="space-y-6">
        {{-- TOP: CRITICAL MEDICAL RISK BANNER --}}
        <div class="p-4 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-sm">
            <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-800">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-shield-check class="w-5 h-5 text-primary-600 dark:text-primary-400" />
                    <h3 class="text-base font-bold text-gray-900 dark:text-white uppercase tracking-wider">
                        Critical Medical Risk & Screening Assessment
                    </h3>
                </div>
                <span class="text-xs text-gray-500 dark:text-gray-400 font-medium">
                    Nuanced Screening &bull; Explicit Negative vs. Missing Data
                </span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3 mt-4">
                @foreach($risks as $key => $risk)
                    @php
                        $color = $risk['color'] ?? 'slate';
                        $bgClass = match($color) {
                            'rose' => 'bg-rose-50/70 border-rose-200 dark:bg-rose-950/20 dark:border-rose-900/60',
                            'amber' => 'bg-amber-50/70 border-amber-200 dark:bg-amber-950/20 dark:border-amber-900/60',
                            'emerald' => 'bg-emerald-50/70 border-emerald-200 dark:bg-emerald-950/20 dark:border-emerald-900/60',
                            default => 'bg-gray-50 border-gray-200 dark:bg-gray-800/40 dark:border-gray-700',
                        };
                        $badgeClass = match($color) {
                            'rose' => 'bg-rose-100 text-rose-800 dark:bg-rose-900/70 dark:text-rose-200 border-rose-200',
                            'amber' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/70 dark:text-amber-200 border-amber-200',
                            'emerald' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/70 dark:text-emerald-200 border-emerald-200',
                            default => 'bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-300 border-gray-300',
                        };
                    @endphp
                    <div class="p-3.5 rounded-lg border {{ $bgClass }} flex flex-col justify-between">
                        <div>
                            <div class="flex items-start justify-between gap-2">
                                <span class="text-xs font-bold text-gray-900 dark:text-gray-100 uppercase tracking-wider">
                                    {{ $risk['title'] }}
                                </span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold border {{ $badgeClass }}">
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

        {{-- 3-COLUMN CLINICAL RECORD GRID --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- COLUMN 1: EXTERNAL DENTAL HISTORY (OUTSIDE CLINIC) --}}
            <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm flex flex-col">
                <div class="p-4 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-building-office-2 class="w-5 h-5 text-indigo-500" />
                        <div>
                            <h4 class="text-sm font-bold text-gray-900 dark:text-white">Outside Clinic History</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">External dental treatments & prior work</p>
                        </div>
                    </div>
                    <button 
                        type="button"
                        wire:click="openAddExternalModal"
                        class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg shadow-sm transition-colors">
                        <x-heroicon-m-plus class="w-4 h-4" />
                        <span>Log Treatment</span>
                    </button>
                </div>

                <div class="p-4 flex-1 space-y-3 overflow-y-auto max-h-[600px]">
                    @forelse($externalTreatments as $ext)
                        <div class="p-3.5 rounded-lg border border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/40 relative group">
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    @if($ext->tooth_number_fdi)
                                        <span class="px-2 py-0.5 text-xs font-bold rounded bg-indigo-100 text-indigo-800 dark:bg-indigo-900/60 dark:text-indigo-200">
                                            Tooth #{{ $ext->tooth_number_fdi }}
                                        </span>
                                    @else
                                        <span class="px-2 py-0.5 text-xs font-semibold rounded bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                            General
                                        </span>
                                    @endif
                                    <h5 class="text-xs font-bold text-gray-900 dark:text-white">
                                        {{ $ext->procedure_title }}
                                    </h5>
                                </div>
                                <button 
                                    type="button"
                                    wire:click="deleteExternalTreatment({{ $ext->id }})"
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
                                            <span>?? {{ $ext->performed_year }}</span>
                                        @endif
                                        @if($ext->clinic_or_doctor_name)
                                            <span>?? {{ $ext->clinic_or_doctor_name }}</span>
                                        @endif
                                    </div>
                                @endif

                                @if($ext->notes)
                                    <p class="mt-1 text-xs text-gray-700 dark:text-gray-300 italic">
                                        &ldquo;{{ $ext->notes }}&rdquo;
                                    </p>
                                @endif
                            </div>

                            <div class="mt-2.5 pt-2 border-t border-gray-200/70 dark:border-gray-700/60 flex items-center justify-between text-[10px] text-gray-400 dark:text-gray-500">
                                <span>Logged by: <strong class="text-gray-600 dark:text-gray-400">{{ $ext->creator?->name ?? 'Staff' }}</strong></span>
                                <span>{{ $ext->created_at->format('M d, Y') }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8 text-gray-400 dark:text-gray-500 text-xs">
                            <x-heroicon-o-document-text class="w-8 h-8 mx-auto mb-2 text-gray-300 dark:text-gray-600" />
                            <p>No outside dental treatments logged.</p>
                            <p class="mt-1 text-[11px]">Click &ldquo;Log Treatment&rdquo; to add crowns, implants, or prior RCTs.</p>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- COLUMN 2: CLINIC JOURNEY TIMELINE (INSIDE CLINIC) --}}
            <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm flex flex-col">
                <div class="p-4 border-b border-gray-100 dark:border-gray-800 flex items-center gap-2">
                    <x-heroicon-o-clock class="w-5 h-5 text-emerald-500" />
                    <div>
                        <h4 class="text-sm font-bold text-gray-900 dark:text-white">Clinic Journey Timeline</h4>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Chronological procedures, visits & prescriptions</p>
                    </div>
                </div>

                <div class="p-4 flex-1 overflow-y-auto max-h-[600px]">
                    @if(count($timeline) > 0)
                        <div class="relative border-l-2 border-gray-200 dark:border-gray-800 ml-3 pl-4 space-y-4">
                            @foreach($timeline as $item)
                                @php
                                    $dotColor = match($item['type']) {
                                        'procedure' => 'bg-emerald-500 ring-emerald-100 dark:ring-emerald-950',
                                        'prescription' => 'bg-amber-500 ring-amber-100 dark:ring-amber-950',
                                        'appointment' => 'bg-sky-500 ring-sky-100 dark:ring-sky-950',
                                        'voice_note' => 'bg-purple-500 ring-purple-100 dark:ring-purple-950',
                                        default => 'bg-gray-400 ring-gray-100 dark:ring-gray-950',
                                    };
                                @endphp
                                <div class="relative group">
                                    {{-- Timeline Dot --}}
                                    <div class="absolute -left-[23px] top-1.5 w-3 h-3 rounded-full {{ $dotColor }} ring-4"></div>

                                    <div class="p-3 rounded-lg border border-gray-200 dark:border-gray-800 bg-gray-50/40 dark:bg-gray-800/30">
                                        <div class="flex items-start justify-between gap-2">
                                            <h5 class="text-xs font-bold text-gray-900 dark:text-white">
                                                {{ $item['title'] }}
                                            </h5>
                                            <span class="text-[10px] text-gray-400 dark:text-gray-500 whitespace-nowrap">
                                                {{ $item['date']->format('M d, Y') }}
                                            </span>
                                        </div>

                                        <div class="mt-1 text-[11px] text-gray-500 dark:text-gray-400 flex items-center justify-between">
                                            <span>Doctor: <strong class="text-gray-700 dark:text-gray-300">{{ $item['doctor'] }}</strong></span>
                                            <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300">
                                                {{ $item['status'] }}
                                            </span>
                                        </div>

                                        {{-- Type Specific Details --}}
                                        @if($item['type'] === 'procedure')
                                            <div class="mt-2 pt-2 border-t border-gray-100 dark:border-gray-800 space-y-1 text-xs text-gray-600 dark:text-gray-300">
                                                @if(!empty($item['details']['anesthetic']))
                                                    <p>?? <strong class="text-gray-800 dark:text-gray-200">Anesthetic:</strong> {{ $item['details']['anesthetic'] }}</p>
                                                @endif
                                                @if(!empty($item['details']['materials']))
                                                    <p>?? <strong class="text-gray-800 dark:text-gray-200">Materials:</strong> {{ is_array($item['details']['materials']) ? implode(', ', $item['details']['materials']) : $item['details']['materials'] }}</p>
                                                @endif
                                                @if(!empty($item['details']['tools']))
                                                    <p>??? <strong class="text-gray-800 dark:text-gray-200">Tools:</strong> {{ is_array($item['details']['tools']) ? implode(', ', $item['details']['tools']) : $item['details']['tools'] }}</p>
                                                @endif
                                                @if(!empty($item['details']['hemostasis']))
                                                    <p class="text-emerald-600 dark:text-emerald-400 font-medium">?? {{ $item['details']['hemostasis'] }}</p>
                                                @endif
                                                @if(!empty($item['details']['notes']))
                                                    <p class="italic text-gray-500 dark:text-gray-400 mt-1">&ldquo;{{ $item['details']['notes'] }}&rdquo;</p>
                                                @endif
                                            </div>
                                        @elseif($item['type'] === 'voice_note')
                                            <div class="mt-2 pt-2 border-t border-gray-100 dark:border-gray-800 space-y-1 text-xs text-gray-600 dark:text-gray-300">
                                                <div class="flex items-center justify-between">
                                                    <a href="{{ $item['details']['share_url'] }}" target="_blank" class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold rounded bg-purple-100 text-purple-800 hover:bg-purple-200 dark:bg-purple-900/60 dark:text-purple-200 transition-colors">
                                                        <x-heroicon-m-speaker-wave class="w-3.5 h-3.5" />
                                                        <span>Listen Audio</span>
                                                    </a>
                                                    @if(!empty($item['details']['duration']))
                                                        <span class="text-[11px] text-gray-400">?? {{ $item['details']['duration'] }}</span>
                                                    @endif
                                                </div>
                                                @if(!empty($item['details']['expires_at']))
                                                    <p class="text-[10px] text-gray-400">Secured link expires: {{ $item['details']['expires_at'] }}</p>
                                                @endif
                                            </div>
                                        @elseif($item['type'] === 'appointment')
                                            <div class="mt-2 pt-2 border-t border-gray-100 dark:border-gray-800 space-y-1 text-xs text-gray-600 dark:text-gray-300">
                                                @if(!empty($item['details']['operatory']))
                                                    <p>?? {{ $item['details']['operatory'] }}</p>
                                                @endif
                                                @if(!empty($item['details']['chief_complaint']))
                                                    <p>?? &ldquo;{{ $item['details']['chief_complaint'] }}&rdquo;</p>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-8 text-gray-400 dark:text-gray-500 text-xs">
                            <x-heroicon-o-calendar-days class="w-8 h-8 mx-auto mb-2 text-gray-300 dark:text-gray-600" />
                            <p>No clinic procedures or visits recorded yet.</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- COLUMN 3: FUTURE ROADMAP (PLANS & UPCOMING VISITS) --}}
            <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm flex flex-col">
                <div class="p-4 border-b border-gray-100 dark:border-gray-800 flex items-center gap-2">
                    <x-heroicon-o-map class="w-5 h-5 text-sky-500" />
                    <div>
                        <h4 class="text-sm font-bold text-gray-900 dark:text-white">Future Roadmap</h4>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Upcoming appointment & active plan phases</p>
                    </div>
                </div>

                <div class="p-4 flex-1 space-y-4 overflow-y-auto max-h-[600px]">
                    {{-- Next Upcoming Appointment Card --}}
                    @if($roadmap['next_appointment'])
                        @php
                            $apt = $roadmap['next_appointment'];
                            $diff = Carbon\Carbon::parse($apt->starts_at)->diffForHumans();
                        @endphp
                        <div class="p-3.5 rounded-lg border-2 border-sky-300 dark:border-sky-800 bg-sky-50/50 dark:bg-sky-950/20">
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
                                <p>????? Doctor: <strong class="text-gray-800 dark:text-gray-200">{{ $apt->doctor?->name ?: 'Staff' }}</strong></p>
                                @if($apt->operatory)
                                    <p>?? Room: {{ $apt->operatory->name }}</p>
                                @endif
                                @if($apt->chief_complaint)
                                    <p class=\"italic\">&ldquo;{{ $apt->chief_complaint }}&rdquo;</p>
                                @endif
                            </div>
                        </div>
                    @else
                        <div class="p-3.5 rounded-lg border border-dashed border-gray-200 dark:border-gray-800 bg-gray-50/30 dark:bg-gray-800/20 text-center text-xs text-gray-400 dark:text-gray-500">
                            <p>No upcoming appointment scheduled.</p>
                        </div>
                    @endif

                    {{-- Active Treatment Plan Phases --}}
                    @if($roadmap['active_plan'])
                        @php $plan = $roadmap['active_plan']; @endphp
                        <div class="space-y-3">
                            <div class="p-3 rounded-lg border border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/40">
                                <div class="flex items-center justify-between">
                                    <h5 class="text-xs font-bold text-gray-900 dark:text-white">
                                        {{ $plan->title }}
                                    </h5>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-200 uppercase">
                                        {{ $plan->status }}
                                    </span>
                                </div>
                                <p class="mt-1 text-[11px] text-gray-500">
                                    Supervising: {{ $plan->doctor?->name ?: 'Doctor' }}
                                </p>
                            </div>

                            @if($plan->phases && $plan->phases->count() > 0)
                                <div class="space-y-2">
                                    @foreach($plan->phases as $phase)
                                        <div class="p-2.5 rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900/80">
                                            <div class="flex items-center justify-between text-xs font-bold text-gray-800 dark:text-gray-200 pb-1.5 border-b border-gray-100 dark:border-gray-800">
                                                <span>Phase {{ $phase->phase_number ?? $loop->iteration }}: {{ $phase->phase_name ?: 'Phase' }}</span>
                                                <span class="text-[10px] font-medium text-gray-400">{{ $phase->status }}</span>
                                            </div>
                                            <div class="mt-2 space-y-1.5">
                                                @forelse($phase->procedures as $proc)
                                                    <div class="flex items-center justify-between text-xs text-gray-600 dark:text-gray-400">
                                                        <span class="flex items-center gap-1.5">
                                                            @if($proc->tooth_number_fdi)
                                                                <span class="font-mono font-bold text-primary-600 dark:text-primary-400">#{{ $proc->tooth_number_fdi }}</span>
                                                            @endif
                                                            <span>{{ $proc->procedureCode?->title ?: 'Clinical Item' }}</span>
                                                        </span>
                                                        <span class="text-[10px] uppercase font-semibold text-gray-500">
                                                            {{ $proc->status }}
                                                        </span>
                                                    </div>
                                                @empty
                                                    <p class="text-[11px] text-gray-400 italic">No procedures under this phase</p>
                                                @endforelse
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="p-3.5 rounded-lg border border-dashed border-gray-200 dark:border-gray-800 bg-gray-50/30 dark:bg-gray-800/20 text-center text-xs text-gray-400 dark:text-gray-500">
                            <p>No active treatment plan found.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- QUICK-ADD EXTERNAL TREATMENT MODAL --}}
        @if($showAddExternalModal)
            <div class="fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl max-w-md w-full shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-150">
                    <div class="p-4 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
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
                                placeholder="e.g. Porcelain Crown, Implant, RCT, Extraction"
                                class="w-full text-xs rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-primary-500 focus:border-primary-500 p-2"
                                required />
                            @error('extProcedureTitle') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">
                                    Tooth # (FDI)
                                </label>
                                <input 
                                    type="number" 
                                    wire:model="extToothNumber"
                                    placeholder="e.g. 16, 21, 36"
                                    min="11" max="85"
                                    class="w-full text-xs rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-primary-500 focus:border-primary-500 p-2" />
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
                                    class="w-full text-xs rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-primary-500 focus:border-primary-500 p-2" />
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">
                                External Clinic / Doctor Name
                            </label>
                            <input 
                                type="text" 
                                wire:model="extClinicOrDoctor"
                                placeholder="e.g. Dr. Ahmed Clinic, Cairo Center"
                                class="w-full text-xs rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-primary-500 focus:border-primary-500 p-2" />
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">
                                Clinical Notes / Observations
                            </label>
                            <textarea 
                                wire:model="extNotes"
                                rows="3"
                                placeholder="Add clinical details, observed margin fit, periapical radiograph notes, etc."
                                class="w-full text-xs rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-primary-500 focus:border-primary-500 p-2"></textarea>
                        </div>

                        <div class="pt-2 flex items-center justify-end gap-2 border-t border-gray-100 dark:border-gray-800">
                            <button 
                                type="button"
                                wire:click="closeAddExternalModal"
                                class="px-3 py-1.5 text-xs font-semibold text-gray-700 dark:text-gray-300 bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 rounded-lg transition-colors">
                                Cancel
                            </button>
                            <button 
                                type="submit"
                                class="px-3.5 py-1.5 text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg shadow-sm transition-colors">
                                Save External Record
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
</x-filament-widgets::widget>
