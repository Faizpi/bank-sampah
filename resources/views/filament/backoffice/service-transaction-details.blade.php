<section aria-label="Fakta transaksi sebelum keputusan" class="space-y-4">
    <h3 class="text-title font-bold">{{ $request->request_number }} · {{ \App\Support\StatusLabel::for($request->status) }}</h3>
    <dl class="grid gap-3 sm:grid-cols-2">
        <div><dt>Warga</dt><dd class="font-semibold">{{ $request->customer?->name }} · {{ $request->customer?->customerProfile?->customer_number }}</dd></div>
        <div><dt>Nilai</dt><dd>Rp {{ number_format($cash ? $request->amount : $request->value_snapshot, 0, ',', '.') }}</dd></div>
        <div><dt>Area saat pengajuan</dt><dd>{{ $request->serviceArea?->name ?? 'Tidak tersedia' }}</dd></div>
        <div><dt>Pelaksana klaim</dt><dd>{{ $cash ? $request->payer?->name : $request->claimant?->name }}</dd></div>
        <div><dt>Diambil pada</dt><dd>{{ $request->claimed_at?->translatedFormat('d M Y H:i') ?? 'Belum diklaim' }}</dd></div>
        <div><dt>Dana disisihkan</dt><dd>{{ \App\Support\StatusLabel::for($request->balanceHold?->status) }} · Rp {{ number_format($request->balanceHold?->amount ?? 0, 0, ',', '.') }}</dd></div>
        <div><dt>{{ $cash ? 'Uang dicatat diserahkan' : 'Paket dicatat diserahkan' }}</dt><dd>{{ ($cash ? $request->paid_at : $request->handed_over_at)?->translatedFormat('d M Y H:i') ?? 'Belum tercatat' }}</dd></div>
        <div><dt>Mutasi keluar</dt><dd>{{ $request->receiptLedgerEntry?->entry_number ?? 'Belum ada' }}</dd></div>
    </dl>
    @if (! $cash)<p><strong>Paket saat pengajuan:</strong> {{ data_get($request->package_snapshot, 'name') }} · {{ data_get($request->package_snapshot, 'contents') }}</p>@else<p><strong>Lokasi/tanggal pengambilan:</strong> {{ $request->pickup_location }} · {{ $request->pickup_date?->translatedFormat('d M Y') }}</p>@endif
    @if ($request->cancellation_requested_at)<p class="rounded-md bg-warning-bg p-3"><strong>Warga meminta batal:</strong> {{ $request->cancellation_requested_at->translatedFormat('d M Y H:i') }} · {{ $request->cancellation_request_reason }}. Jika dipastikan belum diserahkan, keputusan membatalkan dan melepas dana yang disisihkan, bukan kembali ke antrean.</p>@endif
    @if ($request->exception_reason)<p><strong>Kendala:</strong> {{ $request->exception_reason }}</p>@endif
    @if ($request->exception_resolution)<p><strong>Hasil sebelumnya:</strong> {{ $request->exception_resolution }}</p>@endif
    <details><summary class="min-h-11 cursor-pointer font-semibold">Riwayat status</summary>
        @foreach ($request->statusHistory->sortBy('occurred_at') as $history)<p class="border-t border-border py-2">{{ $history->occurred_at->translatedFormat('d M Y H:i') }} · {{ \App\Support\StatusLabel::for($history->new_status) }} · {{ $history->actor?->name }} · {{ $history->reason }}</p>@endforeach
    </details>
    <p class="text-body-sm">Riwayat sistem bukan bukti independen bahwa uang/paket telah diserahkan. Pastikan fakta dengan warga dan pelaksana.</p>
</section>
