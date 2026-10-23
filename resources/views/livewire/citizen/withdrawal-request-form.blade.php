<x-slot:title>Ajukan pencairan</x-slot:title>

<section aria-labelledby="withdrawal-request-title" class="grid gap-6">
    <x-ui.page-heading id="withdrawal-request-title" title="Ajukan Pencairan" description="Saldo disisihkan sampai uang tunai diterima." mascot="5" />

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
    <x-ui.panel title="Detail pengambilan">
        <div class="grid gap-4 md:grid-cols-2">
            @if ($serviceAreas->count() > 1)
                <x-ui.select name="serviceAreaId" label="Area layanan" wire:model.live="serviceAreaId" :options="$serviceAreas->pluck('name', 'id')->all()" placeholder="Pilih area layanan" wrapper-class="md:col-span-2" :error="$errors->first('serviceAreaId')" />
            @endif
            <x-ui.input wire:model.live.debounce.300ms="amount" label="Nominal (rupiah)" name="amount"
                inputmode="numeric" placeholder="Contoh: 25000"
                :hint="$isAmountOverBalance ? 'Nominal melebihi saldo tersedia.' : 'Nominal final tidak dapat diubah setelah diajukan.'"
                :error="$errors->first('amount')" />
            <x-ui.input wire:model="pickupDate" label="Tanggal pengambilan" name="pickupDate"
                type="date"
                :error="$errors->first('pickupDate')" />
            <x-ui.textarea wire:model="pickupLocation" label="Lokasi pengambilan" name="pickupLocation"
                rows="3" wrapper-class="md:col-span-2" placeholder="Contoh: Kantor Desa Sindangheula"
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
