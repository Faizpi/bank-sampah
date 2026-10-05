<x-filament-panels::page>
    <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="technical-settings-title">
        <h2 id="technical-settings-title" class="text-xl font-semibold text-gray-950">Pengaturan teknis</h2>
        <p class="mt-1 max-w-2xl text-sm leading-6 text-gray-600">Atur ambang operasional yang aman. Rahasia aplikasi, kata sandi, token, dan data login tetap tersimpan di server aplikasi.</p>
        <form wire:submit="saveSettings" class="mt-6 grid w-full max-w-3xl gap-4 sm:grid-cols-2">
            <div>
                <label for="settings-queue-backlog-threshold" class="block text-sm font-medium text-gray-800">Ambang antrean pekerjaan</label>
                <input id="settings-queue-backlog-threshold" wire:model="settings.queue_backlog_threshold" type="number" min="1" max="8760" required class="mt-2 backoffice-form-control" @error('settings.queue_backlog_threshold') aria-invalid="true" aria-describedby="settings-queue-backlog-threshold-error" @enderror>
                @error('settings.queue_backlog_threshold')<p id="settings-queue-backlog-threshold-error" class="mt-2 text-sm text-danger-700" role="alert">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="settings-backup-max-age-hours" class="block text-sm font-medium text-gray-800">Usia maksimum cadangan terverifikasi (jam)</label>
                <input id="settings-backup-max-age-hours" wire:model="settings.backup_max_age_hours" type="number" min="1" max="8760" required class="mt-2 backoffice-form-control" @error('settings.backup_max_age_hours') aria-invalid="true" aria-describedby="settings-backup-max-age-hours-error" @enderror>
                @error('settings.backup_max_age_hours')<p id="settings-backup-max-age-hours-error" class="mt-2 text-sm text-danger-700" role="alert">{{ $message }}</p>@enderror
            </div>
            <div class="sm:col-span-2"><button type="submit" wire:loading.attr="disabled" wire:target="saveSettings" class="min-h-11 w-full rounded-lg bg-emerald-700 px-4 text-sm font-semibold text-white hover:bg-emerald-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:ring-offset-2 disabled:cursor-wait disabled:bg-gray-500 sm:w-auto"><span wire:loading.remove wire:target="saveSettings">Simpan pengaturan</span><span wire:loading wire:target="saveSettings">Memproses</span></button></div>
        </form>
    </section>
</x-filament-panels::page>
