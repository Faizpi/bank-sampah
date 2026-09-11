<x-filament-panels::page>
    @if (session('operations_notice'))<div class="mb-6 rounded-lg border border-success-200 bg-success-50 px-4 py-3 text-sm text-success-800" role="status">{{ session('operations_notice') }}</div>@endif
    <section class="rounded-xl border border-warning-200 bg-warning-50 p-5 shadow-sm" aria-labelledby="technical-maintenance-title">
        <h2 id="technical-maintenance-title" class="text-xl font-semibold text-gray-950">Pemeliharaan aplikasi</h2>
        <p class="mt-1 max-w-2xl text-sm leading-6 text-gray-700">Aktifkan hanya saat ada pekerjaan terjadwal. Pengguna dapat kehilangan akses selama status ini aktif.</p>
        <p class="mt-4 text-sm text-gray-700">Status saat ini: <strong>{{ $maintenanceEnabled ? 'aktif' : 'nonaktif' }}</strong>.</p>
        <form x-on:submit.prevent="if ($el.reportValidity()) $dispatch('open-dialog', { id: 'maintenance-dialog', invoker: $event.submitter })" class="mt-4 flex w-full max-w-3xl flex-col gap-3 sm:flex-row sm:items-end">
            <div class="flex-1">
                <label for="maintenance-reason" class="block text-sm font-medium text-gray-800">Alasan perubahan</label>
                <textarea id="maintenance-reason" wire:model="maintenanceReason" required minlength="10" maxlength="1000" rows="3" class="mt-2 backoffice-form-control" @error('maintenanceReason') aria-invalid="true" aria-describedby="maintenance-reason-error" @enderror></textarea>
                @error('maintenanceReason')<p id="maintenance-reason-error" class="mt-2 text-sm text-danger-700" role="alert">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="min-h-11 w-full rounded-lg {{ $maintenanceEnabled ? 'bg-red-700 hover:bg-red-800' : 'bg-emerald-700 hover:bg-emerald-800' }} px-4 text-sm font-semibold text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2 sm:w-auto">{{ $maintenanceEnabled ? 'Nonaktifkan pemeliharaan' : 'Aktifkan pemeliharaan' }}</button>
        </form>
    </section>
    <x-ui.dialog id="maintenance-dialog" name="maintenance" title="{{ $maintenanceEnabled ? 'Nonaktifkan pemeliharaan' : 'Aktifkan pemeliharaan' }}" description="Pekerjaan operasional dapat terganggu selama perubahan ini.">
        <p>Status pemeliharaan akan diterapkan untuk seluruh aplikasi dengan alasan yang telah diisi.</p>
        @error('maintenanceReason')<div role="alert" tabindex="-1" class="mt-4 rounded-lg border border-danger-200 bg-danger-50 p-4 text-sm text-danger-800"><p class="font-semibold">Perubahan belum dapat disimpan</p><p class="mt-1">{{ $message }}</p><a href="#maintenance-reason" class="mt-2 inline-flex font-semibold underline underline-offset-4">Periksa alasan perubahan</a></div>@enderror
        <x-slot:actions><x-ui.button type="button" variant="secondary" x-on:click="closeModal()">Kembali</x-ui.button><x-ui.button type="button" :variant="$maintenanceEnabled ? 'danger' : 'primary'" wire:click="toggleMaintenance" wire:loading.attr="disabled" wire:target="toggleMaintenance">Simpan perubahan</x-ui.button></x-slot:actions>
    </x-ui.dialog>
</x-filament-panels::page>
