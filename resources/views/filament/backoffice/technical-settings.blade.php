<x-filament-panels::page>
    <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="technical-settings-title">
        <h2 id="technical-settings-title" class="text-xl font-semibold text-gray-950">Pengaturan teknis</h2>
        <p class="mt-1 max-w-2xl text-sm leading-6 text-gray-600">Atur ambang operasional yang aman. Rahasia aplikasi, kata sandi, token, dan data login tetap tersimpan di server aplikasi.</p>
        <form wire:submit="saveSettings" class="mt-6 grid gap-4 sm:max-w-2xl sm:grid-cols-2">
            <label class="block text-sm font-medium text-gray-800">Ambang antrean pekerjaan<input wire:model="settings.queue_backlog_threshold" type="number" min="1" max="8760" required class="mt-2 backoffice-form-control"></label>
            <label class="block text-sm font-medium text-gray-800">Usia maksimum cadangan terverifikasi (jam)<input wire:model="settings.backup_max_age_hours" type="number" min="1" max="8760" required class="mt-2 backoffice-form-control"></label>
            <div class="sm:col-span-2"><button type="submit" class="min-h-11 rounded-lg bg-emerald-700 px-4 text-sm font-semibold text-white hover:bg-emerald-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:ring-offset-2">Simpan pengaturan</button></div>
        </form>
    </section>
</x-filament-panels::page>
