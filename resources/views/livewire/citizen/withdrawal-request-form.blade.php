<x-slot:title>Ajukan pencairan</x-slot:title>
<x-slot:context>Layanan warga</x-slot:context>

<section aria-labelledby="withdrawal-request-title" class="grid gap-6">
    {{-- Page header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-label font-semibold text-forest-600">Pencairan Saldo</p>
            <h1 id="withdrawal-request-title" class="mt-2 text-h1 font-bold text-deep-green">Ajukan Pencairan</h1>
            <p class="mt-3 text-body text-text-secondary">
                Saldo akan ditahan sementara. Nominal tidak dapat diubah setelah pengajuan dan baru menjadi saldo keluar setelah pembayaran sah.
            </p>
        </div>
        <x-ui.mascot variant="5" bubble="Cairkan saldo dengan aman!" bubblePosition="top" class="h-28 w-auto shrink-0" />
    </div>

    @if (session('success'))
        <x-ui.success-state title="Berhasil" :description="session('success')" />
    @endif

    @php
        $requestedAmount = ctype_digit($amount) ? (int) $amount : null;
        $isAmountOverBalance = $requestedAmount !== null && $requestedAmount > $availableBalance;
    @endphp

    {{-- Balance Info --}}
    @isset($availableBalance)
        <div class="flex items-center gap-3 rounded-xl border border-forest-600 bg-success-bg px-4 py-3.5">
            <svg viewBox="0 0 24 24" class="size-5 shrink-0 text-forest-600" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect width="20" height="14" x="2" y="5" rx="2"/><path d="M2 10h20"/>
            </svg>
            <div>
                <p class="text-caption text-forest-700">Saldo tersedia</p>
                <p class="amount-tabular text-label font-bold text-forest-700">
                    Rp{{ number_format($availableBalance, 0, ',', '.') }}
                </p>
            </div>
        </div>
    @endisset

    {{-- Withdrawal Form --}}
    <x-ui.panel title="Detail pengambilan" description="Pastikan lokasi dan tanggal mudah diverifikasi petugas.">
        <div class="grid gap-4 md:grid-cols-2">
            @if ($serviceAreas->count() > 1)
                <label class="block md:col-span-2">
                    <span class="text-label font-semibold text-deep-green">Area layanan</span>
                    <select wire:model.live="serviceAreaId" class="mt-2 block w-full rounded-xl border-border bg-warm-canvas text-body text-deep-green">
                        <option value="">Pilih area layanan</option>
                        @foreach ($serviceAreas as $serviceArea)
                            <option value="{{ $serviceArea->id }}">{{ $serviceArea->name }}</option>
                        @endforeach
                    </select>
                    @error('serviceAreaId')
                        <p role="alert" class="mt-2 text-body-sm text-terracotta">{{ $message }}</p>
                    @enderror
                </label>
            @endif
            <x-ui.input wire:model.live.debounce.300ms="amount" label="Nominal (rupiah)" name="amount"
                inputmode="numeric" placeholder="Minimal Rp10.000"
                :hint="$isAmountOverBalance ? 'Nominal melebihi saldo tersedia.' : 'Nominal final tidak dapat diubah setelah diajukan.'"
                :error="$errors->first('amount')" />
            <x-ui.input wire:model="pickupDate" label="Tanggal pengambilan" name="pickupDate"
                type="date"
                :error="$errors->first('pickupDate')" />
            <x-ui.textarea wire:model="pickupLocation" label="Lokasi pengambilan" name="pickupLocation"
                rows="3" class="md:col-span-2"
                hint="Contoh: Rumah Ibu Siti, RT 03 RW 01"
                :error="$errors->first('pickupLocation')" />
        </div>
    </x-ui.panel>

    @error('request')
        <p role="alert" class="text-body-sm font-semibold text-terracotta">{{ $message }}</p>
    @enderror

    <div class="flex justify-end">
        <x-ui.button type="button" wire:click="submit" wire:loading.attr="disabled" wire:target="submit" :disabled="$isAmountOverBalance">
            <span wire:loading.remove wire:target="submit">Ajukan Pencairan</span>
            <span wire:loading wire:target="submit">Memproses...</span>
        </x-ui.button>
    </div>
</section>
