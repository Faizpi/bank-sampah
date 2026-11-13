<x-filament-panels::page>
    <section class="backoffice-page-intro" aria-labelledby="reconciliation-title">
        <h2 id="reconciliation-title" class="text-2xl font-bold text-deep-green">Pemeriksaan saldo harian</h2>
        <p class="mt-2 text-body">Cocokkan transaksi dan saldo dengan uang tunai yang dibayarkan.</p>
        <p class="mt-2 text-body-sm">Pemeriksaan tidak membayar transaksi atau mengubah saldo. Kesalahan uang diperbaiki melalui koreksi resmi.</p>
    </section>
    @if (session('reconciliation-success'))<p role="status" class="rounded-xl bg-success-bg p-4">{{ session('reconciliation-success') }}</p>@endif
    @if ($errors->any())<div role="alert" class="rounded-xl border border-terracotta bg-danger-bg p-4">@foreach ($errors->all() as $message)<p>{{ $message }}</p>@endforeach</div>@endif
    @if ($canCreate)
        <section class="rounded-xl border border-border bg-surface p-5" aria-labelledby="snapshot-title">
            <h2 id="snapshot-title" class="text-title font-bold">Periksa tanggal</h2>
            <form wire:submit="createSnapshot" class="mt-4 grid gap-4 sm:grid-cols-2">
                <label class="block"><span>Tanggal bisnis</span><input wire:model="businessDate" type="date" class="mt-2 backoffice-form-control" required></label>
                <label class="block"><span>Total tunai yang sudah dibayarkan (Rp)</span><input wire:model="cashTotal" inputmode="numeric" type="text" class="mt-2 backoffice-form-control" placeholder="Contoh: 20.000"><span class="mt-1 block text-body-sm">Isi 0 jika tidak ada pembayaran. Boleh dilengkapi pada draf.</span></label>
                <label class="block sm:col-span-2"><span>Catatan (opsional)</span><textarea wire:model="notes" rows="3" class="mt-2 backoffice-form-control" maxlength="2000"></textarea></label>
                <button type="submit" wire:loading.attr="disabled" class="min-h-11 justify-self-start rounded-md bg-primary-700 px-5 font-semibold text-white">Tampilkan pembanding</button>
            </form>
        </section>
    @endif
    <section aria-labelledby="history-title" class="space-y-4">
        <h2 id="history-title" class="text-title font-bold">Riwayat pemeriksaan</h2>
        @forelse ($reconciliations as $reconciliation)
            <article class="rounded-xl border border-border bg-surface p-5" wire:key="reconciliation-{{ $reconciliation->id }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="font-bold">{{ $reconciliation->business_date->translatedFormat('d M Y') }} · Versi {{ $reconciliation->version }}</h3>
                        <p class="mt-1 text-body-sm">Dibuat oleh {{ $reconciliation->creator?->name ?? 'Admin' }}</p>
                        @if ($reconciliation->approved_at)<p class="text-body-sm">Dicatat oleh {{ $reconciliation->approver?->name ?? 'Admin' }} · {{ $reconciliation->approved_at->translatedFormat('d M Y H:i') }}</p>@endif
                    </div>
                    <span class="rounded-md bg-warm-canvas px-3 py-2 font-semibold">{{ \App\Support\StatusLabel::for($reconciliation->status) }}</span>
                </div>
                <p class="mt-3">Saldo tersedia: Rp {{ number_format($reconciliation->opening_total, 0, ',', '.') }} → Rp {{ number_format($reconciliation->closing_total, 0, ',', '.') }}</p>
                <details class="mt-4 border-t border-border pt-3">
                    <summary class="min-h-11 cursor-pointer font-semibold">Lihat pembanding dan selisih</summary>
                    <div class="divide-y divide-border">
                        @foreach ($reconciliation->items as $item)
                            <div class="py-3">
                                <h4 class="font-semibold">{{ match ($item->item_type) { 'deposit_ledger' => 'Setoran', 'withdrawal_ledger' => 'Pencairan tunai', 'grocery_ledger' => 'Sembako', 'cash_disbursement' => 'Uang tunai dibayarkan', 'available_balance' => 'Saldo tersedia', default => $item->item_type } }}</h4>
                                <dl class="mt-2 grid gap-2 sm:grid-cols-3">
                                    <div><dt>Seharusnya</dt><dd class="amount-tabular">Rp {{ number_format($item->expected_total, 0, ',', '.') }}</dd></div>
                                    <div><dt>Tercatat / dihitung</dt><dd class="amount-tabular">Rp {{ number_format($item->actual_total, 0, ',', '.') }}</dd></div>
                                    <div><dt>Selisih · {{ ucfirst($item->status) }}</dt><dd class="amount-tabular font-semibold">Rp {{ number_format($item->difference, 0, ',', '.') }}</dd></div>
                                </dl>
                            </div>
                        @endforeach
                    </div>
                </details>
                @if ($reconciliation->notes)<p class="mt-3 whitespace-pre-line">{{ $reconciliation->notes }}</p>@endif
                @if (in_array($reconciliation->status, ['draf', 'diajukan'], true) && $canComplete)
                    <div class="mt-4 grid gap-3 border-t border-border pt-4">
                        @if ($reconciliation->status === 'draf' && $reconciliation->created_by === auth()->id())
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                                <label class="block"><span>Lengkapi/perbarui total tunai (Rp) · {{ $reconciliation->business_date->translatedFormat('d M Y') }} v{{ $reconciliation->version }}</span><input wire:model="cashCounts.{{ $reconciliation->id }}" inputmode="numeric" class="mt-2 backoffice-form-control"></label>
                                <button type="button" wire:click="saveCashCount({{ $reconciliation->id }})" wire:loading.attr="disabled" class="min-h-11 rounded-md border border-primary-700 px-4 font-semibold">Simpan hitungan</button>
                            </div>
                        @endif
                        <label class="block"><span>Hasil pemeriksaan (wajib jika ada selisih)</span><textarea wire:model="decisionReasons.{{ $reconciliation->id }}" rows="3" maxlength="1000" class="mt-2 backoffice-form-control" placeholder="Jelaskan selisih dan tindak lanjutnya."></textarea></label>
                        <button type="button" wire:click="completeSnapshot({{ $reconciliation->id }})" wire:loading.attr="disabled" class="min-h-11 justify-self-start rounded-md bg-primary-700 px-5 font-semibold text-white">Catat hasil pemeriksaan</button>
                    </div>
                @endif
            </article>
        @empty<p>Belum ada pemeriksaan. Pilih tanggal untuk melihat pembanding.</p>@endforelse
    </section>
    <nav aria-label="Rincian keuangan" class="flex flex-wrap gap-3 border-t border-border pt-4">
        @if ($canReviewDeposits)<a class="min-h-11 rounded-md border border-border px-4 py-3" href="{{ \App\Filament\Resources\Deposits\Models\Deposits\DepositResource::getUrl('index') }}">Koreksi setoran</a>@endif
        @if ($canViewLedger)<a class="min-h-11 rounded-md border border-border px-4 py-3" href="{{ \App\Filament\Resources\Ledger\Models\LedgerEntries\LedgerEntryResource::getUrl('index') }}">Perubahan saldo</a>@endif
        @if ($canViewHolds)<a class="min-h-11 rounded-md border border-border px-4 py-3" href="{{ \App\Filament\Resources\Ledger\Models\BalanceHolds\BalanceHoldResource::getUrl('index') }}">Dana disisihkan</a>@endif
        @if ($canViewAudit)<a class="min-h-11 rounded-md border border-border px-4 py-3" href="{{ \App\Filament\Resources\AuditReconciliation\Models\AuditLogs\AuditLogResource::getUrl('index') }}">Riwayat aktivitas</a>@endif
    </nav>
</x-filament-panels::page>
