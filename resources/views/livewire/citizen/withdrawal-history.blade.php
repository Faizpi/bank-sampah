<x-slot:title>Riwayat pencairan</x-slot:title>

<section aria-labelledby="withdrawal-history-title" class="grid gap-6">
    <x-ui.page-heading id="withdrawal-history-title" title="Riwayat Pencairan" description="Lihat pengajuan dan hasil pencairan saldo Anda." mascot="6" />

    <nav aria-label="Jenis riwayat transaksi" class="grid grid-cols-3 gap-2 rounded-xl border border-border bg-surface p-2">
        <a href="{{ route('citizen.deposit-history') }}" class="app-row-action inline-flex min-h-touch items-center justify-center rounded-lg px-3 text-center text-label font-semibold text-text-secondary">Setoran</a>
        <a href="{{ route('citizen.withdrawal-history') }}" aria-current="page" class="inline-flex min-h-touch items-center justify-center rounded-lg bg-success-bg px-3 text-center text-label font-bold text-forest-700">Pencairan</a>
        <a href="{{ route('citizen.grocery-history') }}" class="app-row-action inline-flex min-h-touch items-center justify-center rounded-lg px-3 text-center text-label font-semibold text-text-secondary">Sembako</a>
    </nav>

    <x-ui.panel>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            @if ($digitalAvailable)
                <div role="group" aria-label="Metode pencairan" class="grid grid-cols-2 gap-2">
                    <x-ui.button :variant="$method === 'tunai' ? 'primary' : 'secondary'" wire:click="selectMethod('tunai')" :aria-pressed="$method === 'tunai' ? 'true' : 'false'" wire:loading.attr="disabled" wire:target="selectMethod">
                        <x-public.icon name="banknote" size="size-5" />Tunai
                    </x-ui.button>
                    <x-ui.button :variant="$method === 'digital' ? 'primary' : 'secondary'" wire:click="selectMethod('digital')" :aria-pressed="$method === 'digital' ? 'true' : 'false'" wire:loading.attr="disabled" wire:target="selectMethod">
                        <x-public.icon name="wallet-cards" size="size-5" />Digital (simulasi)
                    </x-ui.button>
                </div>
            @endif
            <a href="{{ route('citizen.withdrawal.create') }}" class="app-row-action inline-flex min-h-touch shrink-0 items-center justify-center gap-2 rounded-md px-3 font-semibold text-forest-600">
                <x-public.icon name="plus" size="size-5" />Cairkan saldo
            </a>
        </div>

        <details class="group mt-4 border-y border-border" @if ($hasFilters) open @endif>
            <summary class="app-row-action flex min-h-touch cursor-pointer list-none items-center justify-between gap-3 rounded-md py-3 font-semibold text-deep-green">
                <span class="flex items-center gap-2"><x-public.icon name="search" size="size-5" />Cari dan filter</span>
                <x-public.icon name="chevron-down" size="size-5" class="transition-transform group-open:rotate-180 motion-reduce:transition-none" />
            </summary>
            <div class="grid gap-4 pb-5 sm:grid-cols-2">
                <x-ui.input name="requestNumber" label="Nomor pengajuan" placeholder="Cari nomor pencairan" :error="$errors->first('requestNumber')" wire:model.live.debounce.300ms="requestNumber" />
                <x-ui.select name="status" label="Status" placeholder="Semua status" :error="$errors->first('status')" wire:model.live="status">
                    @foreach ($statusOptions as $filterStatus => $label)<option value="{{ $filterStatus }}">{{ $label }}</option>@endforeach
                </x-ui.select>
                <x-ui.input name="dateFrom" label="Dari tanggal" type="date" :error="$errors->first('dateFrom')" wire:model.live="dateFrom" />
                <x-ui.input name="dateUntil" label="Sampai tanggal" type="date" :error="$errors->first('dateUntil')" wire:model.live="dateUntil" />
            </div>
        </details>

        <div class="mt-3" wire:loading.class="opacity-50" wire:target="selectMethod,requestNumber,status,dateFrom,dateUntil" aria-live="polite">
            @forelse ($records as $record)
                @php
                    $isDigital = $record instanceof \App\Domain\Payouts\Models\SandboxPayout;
                    $recordStatus = $isDigital ? $record->status : $record->status->value;
                    $badgeStatus = match ($recordStatus) {
                        'succeeded', \App\Domain\Withdrawals\Enums\WithdrawalStatus::Paid->value => 'success',
                        'failed' => 'error',
                        'cancelled', 'reversed', \App\Domain\Withdrawals\Enums\WithdrawalStatus::Cancelled->value => 'cancelled',
                        'dispatching', 'pending', \App\Domain\Withdrawals\Enums\WithdrawalStatus::Processing->value => 'in_progress',
                        default => 'pending',
                    };
                    $detailUrl = $isDigital ? route('citizen.payout.show', $record) : route('citizen.withdrawal.show', $record);
                @endphp
                <div wire:key="history-{{ $method }}-{{ $record->id }}">
                    <x-ui.transaction-row
                        :type="$isDigital ? $channels[$record->channel]['name'].' (simulasi)' : 'Tunai'"
                        :reference="$record->request_number"
                        :status="$badgeStatus"
                        :status-label="$isDigital ? $record->label() : \App\Support\StatusLabel::for($record->status)"
                        :time="$record->created_at->translatedFormat('d M Y, H:i')"
                        :method="! $isDigital && $record->pickup_date ? 'Ambil '.$record->pickup_date->translatedFormat('d M Y') : null"
                        :value="'Rp '.number_format($record->amount, 0, ',', '.')"
                        :href="$detailUrl"
                        class="app-row-action"
                    />
                    @if (! $isDigital && $record->status === \App\Domain\Withdrawals\Enums\WithdrawalStatus::Paid)
                        <a href="{{ route('citizen.withdrawal.receipt', $record) }}" class="app-row-action inline-flex min-h-touch items-center gap-2 rounded-md px-2 font-semibold text-forest-600"><x-public.icon name="receipt" size="size-5" />Bukti pencairan</a>
                    @endif
                </div>
            @empty
                <x-ui.empty-state :kind="$hasFilters ? 'no-results' : 'no-data'"
                    :title="$hasFilters ? 'Pencairan tidak ditemukan' : 'Belum ada pencairan '.($method === 'digital' ? 'digital' : 'tunai')"
                    :description="$hasFilters ? 'Coba ubah kata pencarian atau filter.' : 'Pengajuan Anda akan tampil di sini.'" />
            @endforelse
        </div>
        @if ($records->hasPages())<div class="mt-4">{{ $records->links('components.ui.pagination') }}</div>@endif
    </x-ui.panel>
</section>
