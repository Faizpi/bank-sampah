<x-filament-panels::page>
    @if (session('operations_notice'))<div class="mb-6 rounded-lg border border-success-200 bg-success-50 px-4 py-3 text-sm text-success-800" role="status">{{ session('operations_notice') }}</div>@endif
    <section class="rounded-xl border border-danger-200 bg-danger-50 p-5 shadow-sm" aria-labelledby="technical-retention-title">
        <h2 id="technical-retention-title" class="text-xl font-semibold text-gray-950">Retensi audit</h2>
        <p class="mt-1 max-w-2xl text-sm leading-6 text-gray-700">Tinjau dulu jumlah data yang akan dihapus. Bukti operasional yang dilindungi tidak ikut dihapus, dan preview kedaluwarsa setelah 10 menit.</p>
        <div class="mt-5 flex max-w-3xl flex-col gap-3 sm:flex-row sm:items-end">
            <label class="block flex-1 text-sm font-medium text-gray-800">Hapus sebelum tanggal<input wire:model="retentionBefore" type="date" required class="mt-2 backoffice-form-control"></label>
            <button wire:click="previewRetention" type="button" class="min-h-11 rounded-lg border border-gray-300 px-4 text-sm font-semibold text-gray-800 hover:bg-gray-50">Pratinjau</button>
            <button type="button" x-on:click="$dispatch('open-dialog', { id: 'audit-retention-dialog', invoker: $el })" class="min-h-11 rounded-lg bg-red-700 px-4 text-sm font-semibold text-white hover:bg-red-800">Jalankan retensi</button>
        </div>
        @if ($retentionResult !== '')<p class="mt-3 text-sm text-gray-700" role="status">{{ $retentionResult }}</p>@endif
    </section>
    <x-ui.dialog id="audit-retention-dialog" name="audit-retention" title="Jalankan retensi audit" description="Data yang memenuhi batas pratinjau terbaru akan dihapus dan tidak dapat dipulihkan dari aplikasi." state="error">
        <p>Bukti operasional yang dilindungi tetap dipertahankan sesuai kebijakan retensi.</p>
        @error('retentionBefore')<div role="alert" tabindex="-1" class="mt-4 rounded-lg border border-danger-200 bg-danger-50 p-4 text-sm text-danger-800"><p class="font-semibold">Retensi belum dapat dijalankan</p><p class="mt-1">{{ $message }}</p><p class="mt-2 font-medium">Tutup dialog, periksa batas tanggal, lalu jalankan pratinjau kembali.</p></div>@enderror
        <x-slot:actions><x-ui.button type="button" variant="secondary" x-on:click="closeModal()">Kembali</x-ui.button><x-ui.button type="button" variant="danger" wire:click="executeRetention" wire:loading.attr="disabled" wire:target="executeRetention">Jalankan retensi</x-ui.button></x-slot:actions>
    </x-ui.dialog>
</x-filament-panels::page>
