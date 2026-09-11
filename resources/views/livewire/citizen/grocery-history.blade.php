<x-slot:title>Riwayat penukaran sembako</x-slot:title>
<x-slot:context>Transaksi Anda</x-slot:context>

<section aria-labelledby="grocery-history-title" class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-label font-semibold text-forest-600">Riwayat Warga</p>
            <h1 id="grocery-history-title" class="mt-2 text-h1 font-bold text-deep-green">Riwayat Penukaran Sembako</h1>
            <p class="mt-3 text-body text-text-secondary">Pantau paket, nilai penukaran, status, dan bukti penyerahan Anda.</p>
        </div>
        <a href="{{ route('citizen.grocery.create') }}" class="inline-flex min-h-touch shrink-0 items-center justify-center rounded-xl bg-forest-600 px-5 text-label font-bold text-white transition hover:bg-forest-700">Ajukan penukaran</a>
    </div>

    <nav aria-label="Jenis riwayat transaksi" class="grid grid-cols-3 gap-2 rounded-xl border border-border bg-surface p-2">
        <a href="{{ route('citizen.deposit-history') }}" class="inline-flex min-h-touch items-center justify-center rounded-lg px-3 text-center text-label font-semibold text-text-secondary transition hover:bg-warm-canvas hover:text-deep-green">Setoran</a>
        <a href="{{ route('citizen.withdrawal-history') }}" class="inline-flex min-h-touch items-center justify-center rounded-lg px-3 text-center text-label font-semibold text-text-secondary transition hover:bg-warm-canvas hover:text-deep-green">Pencairan</a>
        <a href="{{ route('citizen.grocery-history') }}" aria-current="page" class="inline-flex min-h-touch items-center justify-center rounded-lg bg-success-bg px-3 text-center text-label font-bold text-forest-700">Sembako</a>
    </nav>

    <x-ui.panel title="Penukaran sembako" description="Paket, nilai, status, detail, dan bukti penyerahan.">
        <form class="grid gap-4 border-b border-border pb-4 sm:grid-cols-2">
            <x-ui.input name="requestNumber" label="Nomor pengajuan" placeholder="Cari nomor penukaran" wire:model.live.debounce.300ms="requestNumber" />
            <x-ui.select name="status" label="Status" placeholder="Semua status" wire:model.live="status">
                @foreach ($statuses as $filterStatus)
                    <option value="{{ $filterStatus }}">{{ \App\Support\StatusLabel::for($filterStatus) }}</option>
                @endforeach
            </x-ui.select>
            <x-ui.input name="dateFrom" label="Dari tanggal" type="date" :error="$errors->first('dateFrom')" wire:model.live="dateFrom" />
            <x-ui.input name="dateUntil" label="Sampai tanggal" type="date" :error="$errors->first('dateUntil')" wire:model.live="dateUntil" />
        </form>

        <div class="divide-y divide-border pt-4">
            @forelse ($redemptions as $redemption)
                @php
                    $badgeStatus = match ($redemption->status) {
                        \App\Domain\Groceries\Enums\GroceryStatus::Completed => 'success',
                        \App\Domain\Groceries\Enums\GroceryStatus::Rejected => 'error',
                        \App\Domain\Groceries\Enums\GroceryStatus::Cancelled => 'cancelled',
                        \App\Domain\Groceries\Enums\GroceryStatus::Expired => 'expired',
                        \App\Domain\Groceries\Enums\GroceryStatus::Preparing, \App\Domain\Groceries\Enums\GroceryStatus::ReadyForPickup => 'info',
                        default => 'pending',
                    };
                @endphp
                <article class="py-4 first:pt-0 last:pb-0" wire:key="grocery-{{ $redemption->id }}">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex min-w-0 flex-wrap items-center gap-2">
                                <a href="{{ route('citizen.grocery.show', $redemption) }}" class="min-w-0 break-words text-label font-bold text-deep-green hover:text-forest-600">{{ $redemption->request_number }}</a>
                                <x-ui.status-badge :status="$badgeStatus">{{ \App\Support\StatusLabel::for($redemption->status) }}</x-ui.status-badge>
                            </div>
                            <p class="mt-1 break-words text-body-sm font-semibold text-deep-green">{{ $redemption->package_snapshot['name'] ?? 'Paket sembako' }}</p>
                            <p class="mt-0.5 text-body-sm text-text-secondary">{{ $redemption->created_at->translatedFormat('d F Y, H:i') }}</p>
                        </div>
                        <div class="flex w-full flex-col gap-2 sm:w-auto sm:items-end">
                            <p class="amount-tabular break-words text-title font-bold text-deep-green">Rp {{ number_format($redemption->value_snapshot, 0, ',', '.') }}</p>
                            <div class="flex w-full flex-wrap items-center gap-2 sm:w-auto sm:justify-end">
                                @if ($redemption->status === \App\Domain\Groceries\Enums\GroceryStatus::Completed)
                                    <a href="{{ route('citizen.grocery.receipt', $redemption) }}" class="inline-flex min-h-touch items-center px-2 text-label font-bold text-forest-600 hover:text-forest-700">Bukti</a>
                                @endif
                                <a href="{{ route('citizen.grocery.show', $redemption) }}" class="inline-flex min-h-touch items-center px-2 text-label font-bold text-forest-600 hover:text-forest-700">Detail</a>
                            </div>
                        </div>
                    </div>
                </article>
            @empty
                <div class="py-8 text-center">
                    <p class="text-label font-semibold text-deep-green">Belum ada riwayat</p>
                    <p class="mt-1 text-body-sm text-text-secondary">
                        @if ($requestNumber || $status || $dateFrom || $dateUntil)
                            Tidak ada penukaran yang sesuai dengan filter.
                        @else
                            Pengajuan penukaran sembako Anda akan muncul di sini.
                        @endif
                    </p>
                </div>
            @endforelse
        </div>

        @if ($redemptions->hasPages())
            <div class="mt-4">{{ $redemptions->links('components.ui.pagination') }}</div>
        @endif
    </x-ui.panel>
</section>
