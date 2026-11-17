<x-filament-panels::page>
    {{-- Summary Statistics Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="p-5 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm flex items-center space-x-4">
            <div class="p-3 bg-teal-50 dark:bg-teal-950/40 text-teal-600 dark:text-teal-400 rounded-lg">
                <x-heroicon-o-archive-box class="w-6 h-6" />
            </div>
            <div>
                <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Total Backups</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $this->backups->count() }}</p>
            </div>
        </div>

        <div class="p-5 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm flex items-center space-x-4">
            <div class="p-3 bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 rounded-lg">
                <x-heroicon-o-circle-stack class="w-6 h-6" />
            </div>
            <div>
                <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Storage Used</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $this->totalSize }}</p>
            </div>
        </div>

        <div class="p-5 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm flex items-center space-x-4">
            <div class="p-3 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 rounded-lg">
                <x-heroicon-o-lock-closed class="w-6 h-6" />
            </div>
            <div>
                <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Security Mode</p>
                <p class="text-sm font-bold text-emerald-600 dark:text-emerald-400">AES-256 Encrypted</p>
            </div>
        </div>

        <div class="p-5 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm flex items-center space-x-4">
            <div class="p-3 bg-purple-50 dark:bg-purple-950/40 text-purple-600 dark:text-purple-400 rounded-lg">
                <x-heroicon-o-clock class="w-6 h-6" />
            </div>
            <div>
                <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Daily Schedule</p>
                <p class="text-sm font-semibold text-gray-900 dark:text-white">Every day at 02:00 AM</p>
            </div>
        </div>
    </div>

    {{-- Backups List Section --}}
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center space-x-2">
                <x-heroicon-o-folder class="w-5 h-5 text-gray-500" />
                <span>Available Local Backups</span>
            </div>
        </x-slot>

        <x-slot name="description">
            Backups are stored locally on this machine inside an isolated protected directory. Deleting any backup requires a unique security password. Restoring will replace current live data with the selected snapshot.
        </x-slot>

        @if($this->backups->isEmpty())
            <div class="text-center py-12">
                <x-heroicon-o-shield-exclamation class="w-12 h-12 mx-auto text-amber-500" />
                <h3 class="mt-3 text-base font-semibold text-gray-900 dark:text-white">No backups created yet</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Click the "Create Backup Now" button above to generate your first encrypted backup.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-600 dark:text-gray-300 divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900/50 text-xs uppercase font-semibold text-gray-500 dark:text-gray-400">
                        <tr>
                            <th class="py-3 px-4">Archive Filename</th>
                            <th class="py-3 px-4">Date & Time</th>
                            <th class="py-3 px-4">Size</th>
                            <th class="py-3 px-4">Protection</th>
                            <th class="py-3 px-4">SHA-256 Checksum</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($this->backups as $backup)
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50 transition">
                                <td class="py-3 px-4 font-mono text-xs font-semibold text-gray-900 dark:text-white flex items-center space-x-2">
                                    <x-heroicon-o-archive-box class="w-4 h-4 text-teal-600" />
                                    <span>{{ $backup['filename'] }}</span>
                                </td>
                                <td class="py-3 px-4 whitespace-nowrap">
                                    <div class="font-medium text-gray-800 dark:text-gray-200">{{ $backup['created_at']->format('M d, Y - h:i A') }}</div>
                                    <div class="text-xs text-gray-400">{{ $backup['created_at']->diffForHumans() }}</div>
                                </td>
                                <td class="py-3 px-4 font-medium whitespace-nowrap">
                                    {{ $backup['size_formatted'] }}
                                </td>
                                <td class="py-3 px-4 whitespace-nowrap">
                                    @if($backup['is_encrypted'])
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                            <x-heroicon-s-lock-closed class="w-3.5 h-3.5" />
                                            AES-256 Encrypted
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                            Plain ZIP
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3 px-4 font-mono text-xs text-gray-500 whitespace-nowrap" title="{{ $backup['checksum'] }}">
                                    {{ substr($backup['checksum'], 0, 12) }}...{{ substr($backup['checksum'], -6) }}
                                </td>
                                <td class="py-3 px-4 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end space-x-2">
                                        {{-- Restore Action --}}
                                        <x-filament::button
                                            wire:click="openRestoreModal('{{ $backup['filename'] }}')"
                                            icon="heroicon-o-arrow-path-rounded-square"
                                            size="sm"
                                            color="warning"
                                        >
                                            Restore
                                        </x-filament::button>

                                        {{-- Download Action --}}
                                        <x-filament::button
                                            wire:click="downloadBackup('{{ $backup['filename'] }}')"
                                            icon="heroicon-o-arrow-down-tray"
                                            size="sm"
                                            color="gray"
                                        >
                                            Download
                                        </x-filament::button>

                                        {{-- Delete Action (Password Protected) --}}
                                        <x-filament::button
                                            wire:click="openDeleteModal('{{ $backup['filename'] }}')"
                                            icon="heroicon-o-trash"
                                            size="sm"
                                            color="danger"
                                            outlined
                                        >
                                            Delete
                                        </x-filament::button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>

    {{-- Security & Best Practice Guide Card --}}
    <div class="p-6 bg-gradient-to-r from-teal-900 to-slate-900 rounded-xl text-white shadow-lg space-y-4">
        <div class="flex items-center space-x-3">
            <x-heroicon-o-shield-check class="w-7 h-7 text-teal-400" />
            <h3 class="text-lg font-bold">Best Practices for Clinic Data Protection</h3>
        </div>

        <p class="text-sm text-gray-300 leading-relaxed">
            Your clinic backups are protected with military-grade <strong>AES-256 encryption</strong> and authenticated via <strong>HMAC-SHA256</strong>. Deletion is safeguarded by a unique administrative password to prevent accidental or unauthorized data loss.
        </p>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-2">
            <div class="bg-white/10 p-4 rounded-lg backdrop-blur-sm border border-white/10">
                <div class="font-semibold text-teal-300 mb-1 flex items-center gap-1.5">
                    <x-heroicon-o-circle-stack class="w-4 h-4" /> 1. The 3-2-1 Rule
                </div>
                <p class="text-xs text-gray-300 leading-normal">
                    Keep <strong>3 copies</strong> of your clinic data: on <strong>2 different media</strong> (computer SSD + external USB drive), with at least <strong>1 offsite or encrypted cloud</strong> copy.
                </p>
            </div>

            <div class="bg-white/10 p-4 rounded-lg backdrop-blur-sm border border-white/10">
                <div class="font-semibold text-teal-300 mb-1 flex items-center gap-1.5">
                    <x-heroicon-o-key class="w-4 h-4" /> 2. Unique Deletion Security
                </div>
                <p class="text-xs text-gray-300 leading-normal">
                    Backups cannot be deleted without the dedicated deletion password (<code class="bg-black/30 px-1 py-0.5 rounded text-amber-300">BACKUP_DELETE_PASSWORD</code>). This prevents ransomware or disgruntled staff from wiping backups.
                </p>
            </div>

            <div class="bg-white/10 p-4 rounded-lg backdrop-blur-sm border border-white/10">
                <div class="font-semibold text-teal-300 mb-1 flex items-center gap-1.5">
                    <x-heroicon-o-arrow-path class="w-4 h-4" /> 3. Test Restore Drills
                </div>
                <p class="text-xs text-gray-300 leading-normal">
                    Use the <strong>Restore</strong> button or <code class="bg-black/30 px-1 py-0.5 rounded text-amber-300">backup:restore</code> CLI periodically on a test system to verify recovery readiness.
                </p>
            </div>
        </div>
    </div>

    {{-- MODAL 1: Password-Protected Delete Modal --}}
    @if($isDeleteModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-red-200 dark:border-red-900/50 space-y-5 animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-center space-x-3 text-red-600 dark:text-red-400">
                    <div class="p-3 bg-red-100 dark:bg-red-950/60 rounded-xl">
                        <x-heroicon-o-shield-exclamation class="w-7 h-7" />
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">Security Verification Required</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Deletion of clinic backup archives is strictly protected</p>
                    </div>
                </div>

                <div class="p-4 bg-gray-50 dark:bg-gray-900/60 rounded-xl border border-gray-200 dark:border-gray-700 text-sm space-y-1">
                    <span class="text-xs font-semibold uppercase tracking-wider text-gray-400">Archive to delete</span>
                    <p class="font-mono text-sm font-semibold text-gray-800 dark:text-gray-200 break-all">{{ $deleteFilename }}</p>
                </div>

                <div class="space-y-2">
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
                        Enter Unique Backup Deletion Password:
                    </label>
                    <input
                        type="password"
                        wire:model="deletePassword"
                        wire:keydown.enter="executeDelete"
                        placeholder="Enter deletion security password..."
                        class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-2 focus:ring-red-500 focus:border-red-500 text-sm font-mono"
                        autofocus
                    />
                    @if($deleteErrorMessage)
                        <p class="text-xs font-medium text-red-600 dark:text-red-400 flex items-center gap-1 pt-1">
                            <x-heroicon-s-x-circle class="w-4 h-4" /> {{ $deleteErrorMessage }}
                        </p>
                    @endif
                    <p class="text-xs text-gray-400">
                        Default configured key: <code class="font-mono text-gray-500">BACKUP_DELETE_PASSWORD</code> in <code class="font-mono text-gray-500">.env</code>.
                    </p>
                </div>

                <div class="flex items-center justify-end space-x-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                    <x-filament::button wire:click="closeDeleteModal" color="gray" size="md">
                        Cancel
                    </x-filament::button>

                    <x-filament::button
                        wire:click="executeDelete"
                        color="danger"
                        size="md"
                        icon="heroicon-o-trash"
                        wire:loading.attr="disabled"
                    >
                        <span wire:loading.remove wire:target="executeDelete">Confirm Permanent Delete</span>
                        <span wire:loading wire:target="executeDelete">Verifying & Deleting...</span>
                    </x-filament::button>
                </div>
            </div>
        </div>
    @endif

    {{-- MODAL 2: Live System Restore Modal --}}
    @if($isRestoreModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-xl w-full p-6 shadow-2xl border border-amber-300 dark:border-amber-700/60 space-y-5 animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-center space-x-3 text-amber-600 dark:text-amber-400">
                    <div class="p-3 bg-amber-100 dark:bg-amber-950/60 rounded-xl">
                        <x-heroicon-o-exclamation-triangle class="w-7 h-7" />
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">Live System Restore</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Replaces current database and clinic files</p>
                    </div>
                </div>

                <div class="p-4 bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/60 rounded-xl text-xs text-amber-900 dark:text-amber-200 space-y-2">
                    <p class="font-bold flex items-center gap-1.5">
                        <x-heroicon-s-exclamation-circle class="w-4 h-4 text-amber-600 dark:text-amber-400 flex-shrink-0" />
                        CRITICAL WARNING
                    </p>
                    <p class="leading-relaxed">
                        Restoring will overwrite your current active database tables, appointments, invoices, and clinical media files with the state captured in this backup. We strongly recommend creating a fresh backup before proceeding.
                    </p>
                </div>

                <div class="p-3 bg-gray-50 dark:bg-gray-900/60 rounded-xl border border-gray-200 dark:border-gray-700 text-xs font-mono break-all">
                    <span class="text-gray-400 block mb-0.5">Selected Archive:</span>
                    <strong class="text-gray-900 dark:text-white">{{ $restoreFilename }}</strong>
                </div>

                {{-- Restoration Options --}}
                <div class="space-y-3">
                    <span class="block text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">What to Restore:</span>
                    
                    <div class="grid grid-cols-2 gap-3">
                        <label class="flex items-center space-x-2.5 p-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/30 cursor-pointer">
                            <input type="checkbox" wire:model="restoreDb" class="rounded border-gray-300 text-teal-600 focus:ring-teal-500" />
                            <span class="text-sm font-medium text-gray-800 dark:text-gray-200">Database Tables</span>
                        </label>

                        <label class="flex items-center space-x-2.5 p-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/30 cursor-pointer">
                            <input type="checkbox" wire:model="restoreFiles" class="rounded border-gray-300 text-teal-600 focus:ring-teal-500" />
                            <span class="text-sm font-medium text-gray-800 dark:text-gray-200">Clinical Files & Media</span>
                        </label>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">
                            Custom Decryption Key (Optional):
                        </label>
                        <input
                            type="text"
                            wire:model="restoreCustomKey"
                            placeholder="Leave blank to use system encryption key"
                            class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white text-xs font-mono"
                        />
                    </div>

                    <div class="pt-2">
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">
                            Type <strong class="text-red-600 dark:text-red-400">RESTORE</strong> to confirm:
                        </label>
                        <input
                            type="text"
                            wire:model="restoreConfirmKeyword"
                            placeholder="Type RESTORE"
                            class="w-full px-4 py-2.5 rounded-lg border border-red-300 dark:border-red-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-white font-mono uppercase text-sm tracking-wider focus:ring-2 focus:ring-amber-500"
                        />
                    </div>

                    @if($restoreErrorMessage)
                        <p class="text-xs font-medium text-red-600 dark:text-red-400 flex items-center gap-1 pt-1">
                            <x-heroicon-s-x-circle class="w-4 h-4" /> {{ $restoreErrorMessage }}
                        </p>
                    @endif
                </div>

                <div class="flex items-center justify-end space-x-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                    <x-filament::button wire:click="closeRestoreModal" color="gray" size="md">
                        Cancel
                    </x-filament::button>

                    <x-filament::button
                        wire:click="executeRestore"
                        color="danger"
                        size="md"
                        icon="heroicon-o-arrow-path-rounded-square"
                        wire:loading.attr="disabled"
                    >
                        <span wire:loading.remove wire:target="executeRestore">Proceed with Live Restore</span>
                        <span wire:loading wire:target="executeRestore">Restoring System...</span>
                    </x-filament::button>
                </div>
            </div>
        </div>
    @endif

    {{-- MODAL 3: Upload & Restore Modal --}}
    @if($isUploadModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-gray-200 dark:border-gray-700 space-y-5 animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-center space-x-3 text-teal-600 dark:text-teal-400">
                    <div class="p-3 bg-teal-100 dark:bg-teal-950/60 rounded-xl">
                        <x-heroicon-o-arrow-up-tray class="w-7 h-7" />
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">Upload External Backup</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Upload a backup archive from USB drive or offsite storage</p>
                    </div>
                </div>

                <div class="space-y-3">
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
                        Choose Backup Archive (.enc or .zip):
                    </label>
                    <input
                        type="file"
                        wire:model="uploadedBackup"
                        accept=".enc,.zip"
                        class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white text-sm file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-teal-50 file:text-teal-700 hover:file:bg-teal-100"
                    />
                    <p class="text-xs text-gray-400">
                        Once uploaded, the archive will be placed into the local backups folder and you will be prompted to confirm restoration.
                    </p>
                </div>

                <div class="flex items-center justify-end space-x-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                    <x-filament::button wire:click="closeUploadModal" color="gray" size="md">
                        Cancel
                    </x-filament::button>

                    <x-filament::button
                        wire:click="executeUploadAndRestore"
                        color="primary"
                        size="md"
                        icon="heroicon-o-arrow-up-tray"
                        wire:loading.attr="disabled"
                    >
                        <span wire:loading.remove wire:target="executeUploadAndRestore">Upload & Review</span>
                        <span wire:loading wire:target="executeUploadAndRestore">Uploading...</span>
                    </x-filament::button>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
