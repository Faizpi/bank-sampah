<x-filament-panels::page>
    <section class="backoffice-page-intro" aria-labelledby="reconciliation-title">
        <p class="text-sm font-semibold text-forest-700">Pengawasan keuangan</p>
        <h2 id="reconciliation-title" class="mt-1 text-2xl font-bold text-deep-green">Rekonsiliasi saldo harian</h2>
        <p class="mt-3 max-w-3xl text-base leading-7 text-text-secondary">Bandingkan transaksi, saldo tersedia, dan kas pencairan sebelum penutupan hari kerja.</p>
    </section>

    @if (session('reconciliation-success'))
        <div class="mt-6 rounded-xl border border-success-200 bg-success-50 px-5 py-4 text-base font-medium leading-6 text-success-800" role="status">{{ session('reconciliation-success') }}</div>
    @endif

    <details class="group mt-6 rounded-xl border border-primary-100 bg-primary-50 p-5 sm:p-6">
        <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-4 rounded-md text-lg font-bold text-primary-950 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2">
            <span>Cara melakukan rekonsiliasi</span>
            <svg data-disclosure-chevron viewBox="0 0 24 24" class="size-5 shrink-0 text-primary-700 transition-transform group-open:rotate-180 motion-reduce:transition-none" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
        </summary>
        <ol class="mt-5 grid gap-5 text-base leading-7 text-primary-950 sm:grid-cols-3">
            <li class="grid grid-cols-[2rem_minmax(0,1fr)] items-start gap-3">
                <span aria-hidden="true" class="flex size-8 items-center justify-center rounded-sm bg-surface text-sm font-bold text-primary-800">1</span>
                <div><p class="font-bold">Simpan kondisi saldo</p><p class="mt-1 text-sm leading-6">Pilih tanggal bisnis. Isi kas pencairan bila uang fisik sudah dihitung.</p></div>
            </li>
            <li class="grid grid-cols-[2rem_minmax(0,1fr)] items-start gap-3">
                <span aria-hidden="true" class="flex size-8 items-center justify-center rounded-sm bg-surface text-sm font-bold text-primary-800">2</span>
                <div><p class="font-bold">Periksa pembanding</p><p class="mt-1 text-sm leading-6">Pastikan setoran, pencairan, penukaran sembako, kas, dan saldo tersedia semuanya sesuai.</p></div>
            </li>
            <li class="grid grid-cols-[2rem_minmax(0,1fr)] items-start gap-3">
                <span aria-hidden="true" class="flex size-8 items-center justify-center rounded-sm bg-surface text-sm font-bold text-primary-800">3</span>
                <div><p class="font-bold">Ajukan dan setujui</p><p class="mt-1 text-sm leading-6">Pembuat mengajukan. Pengguna lain yang berwenang memeriksa lalu menyetujui atau menolak.</p></div>
            </li>
        </ol>
    </details>

    @if ($canCreate)
        <section class="mt-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm sm:p-7" aria-labelledby="snapshot-title">
            <div class="max-w-3xl">
                <h2 id="snapshot-title" class="text-xl font-bold text-gray-950">Simpan kondisi saldo harian</h2>
                <p class="mt-2 text-base leading-7 text-gray-600">Catatan ini menyimpan kondisi saldo saat dibuat. Riwayat sebelumnya tetap tersimpan dan tidak dapat diubah.</p>
            </div>

            <form wire:submit="createSnapshot" class="mt-7 grid max-w-3xl gap-x-6 gap-y-6 sm:grid-cols-2">
                <label class="block text-base font-semibold leading-6 text-gray-800">
                    <span>Tanggal bisnis</span>
                    <input wire:model="businessDate" type="date" class="mt-2 backoffice-form-control">
                    @error('businessDate') <span class="mt-2 block text-sm font-medium text-danger-700">{{ $message }}</span> @enderror
                </label>

                <label class="block text-base font-semibold leading-6 text-gray-800">
                    <span>Kas pencairan (Rp)</span>
                    <input wire:model="cashTotal" inputmode="numeric" type="text" placeholder="Contoh: 150000" class="mt-2 backoffice-form-control">
                    <span class="mt-2 block text-sm font-normal leading-6 text-gray-600">Kosongkan bila uang fisik belum dihitung.</span>
                    @error('cashTotal') <span class="mt-2 block text-sm font-medium text-danger-700">{{ $message }}</span> @enderror
                </label>

                <label class="block text-base font-semibold leading-6 text-gray-800 sm:col-span-2">
                    <span>Catatan awal</span>
                    <textarea wire:model="notes" rows="4" maxlength="2000" placeholder="Contoh: Penghitungan dilakukan bersama bendahara dan petugas kas." class="mt-2 backoffice-form-control"></textarea>
                    <span class="mt-2 block text-sm font-normal leading-6 text-gray-600">Opsional. Gunakan untuk konteks pemeriksaan, bukan untuk data rahasia.</span>
                    @error('notes') <span class="mt-2 block text-sm font-medium text-danger-700">{{ $message }}</span> @enderror
                </label>

                <div class="sm:col-span-2">
                    <button type="submit" class="inline-flex min-h-11 items-center rounded-lg bg-primary-700 px-5 text-base font-semibold text-white transition hover:bg-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60" wire:loading.attr="disabled" wire:target="createSnapshot">Simpan kondisi saldo</button>
                </div>
            </form>
        </section>
    @endif

    <section class="mt-8" aria-labelledby="history-title">
        <div class="max-w-3xl">
            <h2 id="history-title" class="text-xl font-bold text-gray-950">Riwayat rekonsiliasi</h2>
            <p class="mt-2 text-base leading-7 text-gray-600">Hanya snapshot dengan seluruh pembanding sesuai yang dapat diajukan dan disetujui.</p>
        </div>

        <div class="mt-5 space-y-5">
            @forelse ($reconciliations as $reconciliation)
                <article class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm sm:p-7">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <h3 class="text-lg font-bold text-gray-950">{{ $reconciliation->business_date->format('d M Y') }} <span class="font-medium text-gray-500">· Versi {{ $reconciliation->version }}</span></h3>
                            <p class="mt-2 text-base leading-7 text-gray-600">Dibuat oleh {{ $reconciliation->creator?->name ?? '—' }}. Saldo tersedia: Rp {{ number_format($reconciliation->opening_total, 0, ',', '.') }} → Rp {{ number_format($reconciliation->closing_total, 0, ',', '.') }}.</p>
                        </div>
                        <span @class([
                            'inline-flex shrink-0 items-center rounded-md px-3 py-1.5 text-sm font-bold',
                            'bg-success-50 text-success-800' => $reconciliation->status === 'disetujui',
                            'bg-danger-50 text-danger-800' => $reconciliation->status === 'ditolak',
                            'bg-primary-50 text-primary-800' => $reconciliation->status === 'diajukan',
                            'bg-warning-50 text-warning-800' => $reconciliation->status === 'draf',
                        ])>{{ ucfirst(str_replace('_', ' ', $reconciliation->status)) }}</span>
                    </div>

                    <div class="mt-6 overflow-x-auto rounded-lg border border-gray-200" role="region" aria-label="Rincian pembanding rekonsiliasi {{ $reconciliation->business_date->format('d M Y') }}">
                        <table class="min-w-[46rem] w-full text-left text-sm">
                            <thead class="bg-warm-canvas text-xs font-bold uppercase tracking-wide text-gray-600">
                                <tr>
                                    <th scope="col" class="px-4 py-3">Pembanding</th>
                                    <th scope="col" class="px-4 py-3 text-right">Harapan</th>
                                    <th scope="col" class="px-4 py-3 text-right">Aktual</th>
                                    <th scope="col" class="px-4 py-3 text-right">Selisih</th>
                                    <th scope="col" class="px-4 py-3">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white text-gray-800">
                                @foreach ($reconciliation->items as $item)
                                    <tr>
                                        <td class="max-w-sm px-4 py-4 font-semibold">{{ str_replace('_', ' ', $item->item_type) }}<span class="mt-1 block text-xs font-normal leading-5 text-gray-600">{{ $item->note }}</span></td>
                                        <td class="px-4 py-4 text-right font-medium tabular-nums">Rp {{ number_format($item->expected_total, 0, ',', '.') }}</td>
                                        <td class="px-4 py-4 text-right font-medium tabular-nums">Rp {{ number_format($item->actual_total, 0, ',', '.') }}</td>
                                        <td @class(['px-4 py-4 text-right font-bold tabular-nums', 'text-success-700' => $item->difference === 0, 'text-danger-700' => $item->difference !== 0])>Rp {{ number_format($item->difference, 0, ',', '.') }}</td>
                                        <td class="px-4 py-4 text-sm font-semibold">{{ ucfirst($item->status) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if ($reconciliation->notes)
                        <p class="mt-5 whitespace-pre-line border-t border-gray-200 pt-4 text-base leading-7 text-gray-600">{{ $reconciliation->notes }}</p>
                    @endif

                    @if ($reconciliation->status === 'draf' && $canCreate)
                        <div class="mt-6 border-t border-gray-200 pt-6">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
                                <label class="block w-full max-w-sm text-base font-semibold leading-6 text-gray-800">
                                    <span>Perbarui kas pencairan (Rp)</span>
                                    <input wire:model="cashTotal" inputmode="numeric" type="text" class="mt-2 backoffice-form-control">
                                </label>
                                <div class="flex flex-wrap gap-3">
                                    <button wire:click="saveCashCount({{ $reconciliation->id }})" type="button" class="min-h-11 rounded-lg border border-primary-700 px-5 text-base font-semibold text-primary-800 transition hover:bg-primary-50">Simpan kas</button>
                                    <button wire:click="submitSnapshot({{ $reconciliation->id }})" type="button" class="min-h-11 rounded-lg bg-primary-700 px-5 text-base font-semibold text-white transition hover:bg-primary-800">Ajukan pemeriksaan</button>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if ($reconciliation->status === 'diajukan' && $canApprove)
                        <div class="mt-6 border-t border-gray-200 pt-6">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
                                <label class="block w-full flex-1 text-base font-semibold leading-6 text-gray-800">
                                    <span>Catatan keputusan</span>
                                    <textarea wire:model="decisionReason" rows="3" maxlength="1000" placeholder="Tulis dasar persetujuan atau penolakan." class="mt-2 backoffice-form-control"></textarea>
                                </label>
                                <div class="flex flex-wrap gap-3">
                                    <button wire:click="approveSnapshot({{ $reconciliation->id }})" type="button" class="min-h-11 rounded-lg bg-success-700 px-5 text-base font-semibold text-white transition hover:bg-success-800">Setujui</button>
                                    <button wire:click="rejectSnapshot({{ $reconciliation->id }})" type="button" class="min-h-11 rounded-lg bg-danger-700 px-5 text-base font-semibold text-white transition hover:bg-danger-800">Tolak</button>
                                </div>
                            </div>
                        </div>
                    @endif
                </article>
            @empty
                <div class="rounded-xl border border-dashed border-gray-300 bg-white p-6 text-base leading-7 text-gray-600">Belum ada catatan kondisi saldo. Buat pemeriksaan untuk menutup rekonsiliasi hari ini.</div>
            @endforelse
        </div>
    </section>

    <nav class="mt-8 overflow-x-auto border-b border-gray-200" aria-label="Bagian rekonsiliasi">
        <div class="flex min-w-max gap-6">
            @if ($canReviewDeposits)
                <a href="{{ \App\Filament\Resources\Deposits\Models\Deposits\DepositResource::getUrl('index') }}" class="inline-flex min-h-12 items-center border-b-2 border-transparent px-1 text-sm font-semibold text-gray-700 hover:border-primary-500 hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2">Koreksi setoran</a>
            @endif
            @if ($canViewLedger)
                <a href="{{ \App\Filament\Resources\Ledger\Models\LedgerEntries\LedgerEntryResource::getUrl('index') }}" class="inline-flex min-h-12 items-center border-b-2 border-transparent px-1 text-sm font-semibold text-gray-700 hover:border-primary-500 hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2">Perubahan saldo</a>
            @endif
            @if ($canViewHolds)
                <a href="{{ \App\Filament\Resources\Ledger\Models\BalanceHolds\BalanceHoldResource::getUrl('index') }}" class="inline-flex min-h-12 items-center border-b-2 border-transparent px-1 text-sm font-semibold text-gray-700 hover:border-primary-500 hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2">Dana ditahan</a>
            @endif
            @if ($canViewAudit)
                <a href="{{ \App\Filament\Resources\AuditReconciliation\Models\AuditLogs\AuditLogResource::getUrl('index') }}" class="inline-flex min-h-12 items-center border-b-2 border-transparent px-1 text-sm font-semibold text-gray-700 hover:border-primary-500 hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2">Audit log</a>
            @endif
        </div>
    </nav>
</x-filament-panels::page>
