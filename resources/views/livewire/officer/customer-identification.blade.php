<x-slot:title>Identifikasi warga</x-slot:title>
<x-slot:date>{{ now()->translatedFormat('d F Y') }}</x-slot:date>
<x-slot:connectivity>Terhubung</x-slot:connectivity>

<section aria-labelledby="customer-identification-title" class="space-y-6">
    {{-- Page header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-label font-semibold text-forest-600">Layanan Petugas</p>
            <h1 id="customer-identification-title" class="mt-2 text-h1 font-bold text-deep-green">Identifikasi Warga</h1>
            <p class="mt-3 text-body text-text-secondary">
                Pindai QR atau masukkan nomor nasabah. Hasil scan hanya kandidat sampai nama dikonfirmasi.
            </p>
        </div>
        <x-ui.mascot variant="6" bubble="Cari warga dengan mudah!" bubblePosition="top" class="h-28 w-auto shrink-0" />
    </div>

    {{-- Search Form --}}
    <x-ui.panel title="Cari dengan nomor nasabah" description="Gunakan nomor kartu sebagai alternatif ketika pemindaian tidak tersedia.">
        <form wire:submit="find" class="space-y-4" aria-describedby="identification-help">
            <x-ui.input
                name="search"
                label="Nomor atau nama warga"
                hint="Masukkan awalan nomor, misalnya CST-1234."
                wire:model="search"
                autocomplete="off"
                :error="$errors->first('search')"
            />
            <p id="identification-help" class="text-body-sm text-text-secondary">
                Data warga hanya tampil bila berada dalam scope tugas Anda.
            </p>
            <x-ui.button type="submit" wire:loading.attr="disabled">
                <span wire:loading.remove>Cari Nasabah</span>
                <span wire:loading>Mencari...</span>
            </x-ui.button>
        </form>
    </x-ui.panel>

    {{-- Candidate Result --}}
    @if ($candidate)
        <x-ui.panel title="Kandidat identitas" state="{{ $confirmed ? 'success' : 'default' }}">
            @if ($confirmed)
                <div role="status" class="space-y-1">
                    <div class="flex items-center gap-2">
                        <svg viewBox="0 0 24 24" class="size-5 text-forest-600" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10Z"/><path d="m9 12 2 2 4-4"/>
                        </svg>
                        <p class="text-label font-bold text-forest-600">Identitas warga terkonfirmasi</p>
                    </div>
                    <p class="text-h2 font-bold text-deep-green">{{ $candidate->name }}</p>
                    <p class="text-body-sm text-text-secondary">Nomor referensi: {{ $candidate->maskedNumber() }}</p>
                    <div class="mt-4">
                        <a href="{{ route('officer.deposit-form', ['customerId' => $candidate->userId]) }}"

                            class="inline-flex min-h-touch items-center gap-2 rounded-xl bg-forest-600 px-5 text-label font-bold text-white transition hover:bg-forest-700">
                            <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                            Mulai Setoran
                        </a>
                    </div>
                </div>
            @else
                <div class="space-y-4">
                    <div class="rounded-xl bg-warm-canvas px-4 py-3.5">
                        <p class="text-caption font-medium text-text-secondary">Konfirmasi nama warga</p>
                        <p class="mt-0.5 text-title font-bold text-deep-green">{{ $candidate->name }}</p>
                        <p class="mt-0.5 text-body-sm text-text-secondary">Nomor referensi: {{ $candidate->maskedNumber() }}</p>
                    </div>
                    <x-ui.button type="button" wire:click="confirm">Konfirmasi Nama</x-ui.button>
                </div>
            @endif
        </x-ui.panel>
    @elseif ($search !== '' && ! $errors->has('search'))
        <div class="rounded-xl border border-border bg-surface p-6 text-center shadow-xs">
            <x-ui.mascot variant="9" class="mx-auto h-20 w-auto" />
            <p class="mt-3 text-label font-bold text-deep-green">Nasabah tidak ditemukan</p>
            <p class="mt-1.5 text-body-sm text-text-secondary">Periksa nomor alternatif atau minta warga menunjukkan kartunya kembali.</p>
        </div>
    @endif
</section>

