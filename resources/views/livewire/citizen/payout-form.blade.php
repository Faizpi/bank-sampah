<x-slot:title>Pencairan digital (simulasi)</x-slot:title>
<section class="grid gap-6" aria-labelledby="payout-title">
    <x-ui.page-heading id="payout-title" title="Pencairan digital (simulasi)" description="Ajukan langsung dari akun Anda." mascot="6" />
    <p role="note" class="rounded-xl border border-border bg-warning-bg p-4 font-semibold">Mode uji coba. Saldo riset akan diproses, tetapi tidak ada uang yang dikirim ke bank atau dompet digital asli.</p>
    <nav aria-label="Metode pencairan" class="flex flex-wrap gap-3">
        <a class="app-row-action inline-flex min-h-touch items-center rounded-lg border border-border px-4 font-semibold" href="{{ route('citizen.withdrawal.create') }}">Tunai</a>
        <span aria-current="page" class="inline-flex min-h-touch items-center rounded-lg bg-success-bg px-4 font-bold text-forest-700">Digital (simulasi)</span>
    </nav>
    @if ($errors->any())<div role="alert" class="rounded-lg border border-terracotta bg-danger-bg p-4">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    @if ($reviewHash === null)
        <p>Saldo tersedia: <strong class="amount-tabular">Rp {{ number_format($balance, 0, ',', '.') }}</strong></p>
        <fieldset class="grid gap-3">
            <legend class="mb-3 font-bold">Pilih tujuan uji</legend>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                @foreach ($channels as $id => $row)
                    <label class="app-interactive flex min-h-touch cursor-pointer flex-col gap-3 rounded-xl border p-4 {{ $channel === $id ? 'border-forest-600 bg-success-bg' : 'border-border bg-surface' }}">
                        <img src="{{ asset($row['logo']) }}" alt="Logo {{ $row['name'] }}" class="h-7 w-24 max-w-full object-contain object-left" />
                        <span class="flex items-center gap-2"><input type="radio" aria-label="{{ $row['name'] }}" wire:model.live="channel" value="{{ $id }}" @disabled(! $row['ready']) class="size-5 accent-forest-600"><span class="font-semibold">{{ $row['name'] }}</span></span>
                        <span class="text-body-sm text-text-secondary">{{ $row['ready'] ? 'Simulasi' : 'Belum dikonfigurasi' }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.input name="recipientName" label="Nama pemilik tujuan uji" placeholder="Contoh: Siti Aminah" wire:model="recipientName" :error="$errors->first('name')" />
            <x-ui.input name="accountNumber" :label="in_array($channel, ['dana', 'gopay'], true) ? 'Nomor dompet digital uji' : 'Nomor rekening uji'" :placeholder="in_array($channel, ['dana', 'gopay'], true) ? 'Contoh: 081234567890' : 'Contoh: 0123456789'" inputmode="numeric" wire:model="accountNumber" :error="$errors->first('number')" />
            <x-ui.input name="amount" label="Nominal pencairan (Rp)" placeholder="Contoh: 20000" inputmode="numeric" wire:model="amount" :error="$errors->first('amount')" />
            @if ($serviceAreas->count() > 1)<x-ui.select name="serviceAreaId" label="Area layanan" wire:model="serviceAreaId">@foreach ($serviceAreas as $area)<option value="{{ $area->id }}">{{ $area->name }}</option>@endforeach</x-ui.select>@endif
        </div>
        <p class="text-body-sm text-text-secondary">Nama dan nomor tujuan uji akan dikirim ke Xendit Test Mode. Gunakan data uji, bukan nomor pribadi peserta.</p>
        <x-ui.button wire:click="review" wire:loading.attr="disabled">Periksa pengajuan</x-ui.button>
    @else
        <x-ui.panel title="Periksa tujuan dan nominal">
            <dl class="grid gap-4">
                <div><dt>Tujuan</dt><dd class="font-bold">{{ $channels[$channel]['name'] }} (simulasi)</dd></div>
                <div><dt>Nama pemilik</dt><dd class="break-words font-semibold">{{ $recipientName }}</dd></div>
                <div><dt>Nomor tujuan uji</dt><dd class="break-all font-semibold">{{ $accountNumber }}</dd></div>
                <div><dt>Nominal</dt><dd class="amount-tabular text-h2 font-bold">Rp {{ number_format((int) $amount, 0, ',', '.') }}</dd></div>
            </dl>
            <p class="mt-4">Saldo disisihkan saat diajukan. Hasil provider menentukan penyelesaian simulasi, tanpa pembayaran oleh Bendahara.</p>
        </x-ui.panel>
        <div class="flex flex-wrap gap-3"><x-ui.button variant="secondary" wire:click="edit">Periksa lagi</x-ui.button><x-ui.button wire:click="submit" wire:loading.attr="disabled">Konfirmasi pencairan simulasi</x-ui.button></div>
    @endif
</section>
