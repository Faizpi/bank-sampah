<x-slot:title>Ajukan sembako</x-slot:title>
<x-slot:context>Layanan warga</x-slot:context>

<section aria-labelledby="grocery-request-title" class="grid gap-6">
    {{-- Page header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-label font-semibold text-forest-600">Penukaran Saldo</p>
            <h1 id="grocery-request-title" class="mt-2 text-h1 font-bold text-deep-green">Pilih Paket Sembako</h1>
            <p class="mt-3 text-body text-text-secondary">
                Nilai paket akan di-snapshot. Saldo ditahan sementara dan baru menjadi saldo keluar setelah paket diserahkan secara sah.
            </p>
        </div>
        <x-ui.mascot variant="3" bubble="Tukar saldo dengan sembako!" bubblePosition="top" class="h-28 w-auto shrink-0" />
    </div>

    @if (session('success'))
        <x-ui.success-state title="Berhasil" :description="session('success')" />
    @endif

    {{-- Package Selection --}}
    <x-ui.panel title="Paket aktif" description="Ketersediaan rinci dikonfirmasi manual oleh petugas. Tidak ada stok rinci yang dikelola sistem.">
        <div class="grid gap-4">
            <x-ui.select wire:model="packageId" label="Paket sembako" name="packageId"
                :options="$packages->pluck('name', 'id')->all()"
                :error="$errors->first('packageId')" />

            <div class="rounded-xl border border-info-bg bg-info-bg px-4 py-3.5">
                <div class="flex items-start gap-2.5">
                    <svg viewBox="0 0 24 24" class="mt-0.5 size-4 shrink-0 text-sky-blue" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>
                    </svg>
                    <div>
                        <p class="text-label font-semibold text-deep-green">Sumber bantuan</p>
                        <p class="mt-0.5 text-body-sm text-text-secondary">Penukaran warga menggunakan saldo. Jalur bantuan gratis diproses terpisah oleh petugas tanpa hold dan tanpa saldo keluar.</p>
                    </div>
                </div>
            </div>
        </div>
    </x-ui.panel>

    <x-ui.button type="button" wire:click="submit" wire:loading.attr="disabled">
        <span wire:loading.remove>Ajukan Paket</span>
        <span wire:loading>Memproses...</span>
    </x-ui.button>
</section>
