<x-slot:title>Cairkan Saldo</x-slot:title>

<section aria-labelledby="withdrawal-request-title" class="grid gap-6">
    <x-ui.page-heading id="withdrawal-request-title" :title="$choosingMethod && $digitalAvailable ? 'Cairkan Saldo' : 'Pencairan tunai'" :description="$choosingMethod && $digitalAvailable ? 'Pilih metode pencairan.' : 'Saldo disisihkan sampai uang tunai diterima.'" mascot="5" />

    @if ($choosingMethod && $digitalAvailable)
        <div class="grid gap-3" aria-label="Metode pencairan">
            <x-ui.action-card icon="banknote" title="Tunai" description="Ambil uang tunai melalui Bendahara." wire:click="chooseCash" wire:loading.attr="disabled" />
            <x-ui.action-card :href="route('citizen.payout.create')" icon="wallet-cards" title="Digital (simulasi)" description="Pilihan bank dan dompet digital." />
        </div>
        @error('request')<p role="alert" class="font-semibold text-text-primary">{{ $message }}</p>@enderror
    @else
    @if ($digitalAvailable)
        <div><x-ui.button variant="quiet" wire:click="changeMethod"><x-public.icon name="arrow-left" size="size-5" />Ganti metode</x-ui.button></div>
    @endif

    @if (session('success'))
        <x-ui.success-state title="Berhasil" :description="session('success')" />
    @endif

    @php
        $requestedAmount = ctype_digit($amount) ? (int) $amount : null;
        $isAmountOverBalance = $requestedAmount !== null && $requestedAmount > $availableBalance;
    @endphp

    {{-- Balance Info --}}
    @isset($availableBalance)
        <x-ui.amount-display label="Saldo tersedia" :amount="'Rp '.number_format($availableBalance, 0, ',', '.')" />
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
    @endif
</section>
