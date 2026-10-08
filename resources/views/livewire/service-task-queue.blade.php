<x-slot:title>{{ $cash ? 'Pembayaran tunai' : 'Layanan sembako' }}</x-slot:title>
<section class="space-y-6">
    <h1 class="text-h1 font-bold text-deep-green">{{ $cash ? 'Pembayaran tunai' : 'Layanan sembako' }}</h1>
    <p class="text-body text-text-secondary">{{ $cash ? 'Ambil pembayaran hanya jika uang tunai fisik tersedia. Tidak perlu persetujuan Admin.' : 'Ambil tugas hanya jika paket fisik tersedia. Paket dapat langsung diserahkan atau ditandai siap diambil.' }}</p>
    @if (session('success'))<p role="status" class="rounded-xl bg-forest-50 p-4">{{ session('success') }}</p>@endif
    @if ($errors->any())<div role="alert" class="rounded-xl bg-red-50 p-4"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @if ($receipt)
        <x-ui.panel title="Penyerahan tercatat">
            <p class="font-bold">{{ $receipt['number'] }} · Rp {{ number_format($receipt['value'], 0, ',', '.') }}</p>
            <p>{{ $receipt['time'] }} · {{ str_replace('_', ' ', $receipt['status']) }}</p>
            <p>{{ $cash ? 'Jangan serahkan uang lagi untuk transaksi ini.' : 'Jangan serahkan paket lagi untuk transaksi ini.' }}</p>
        </x-ui.panel>
    @endif
    <x-ui.input name="queueSearch" label="Cari nama atau nomor transaksi" wire:model.live.debounce.300ms="queueSearch" />
    <x-ui.select name="queueTab" label="Tampilkan tugas" wire:model.live="queueTab" :options="['waiting' => 'Menunggu diambil', 'mine' => 'Tugas saya']" />
    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.panel title="Antrean layanan">
            <div class="space-y-3">
                @forelse ($requests as $request)
                    <button type="button" wire:click="select({{ $request->id }})" class="w-full rounded-xl border border-border p-4 text-left focus-visible:ring-2 focus-visible:ring-focus" wire:key="task-{{ $request->id }}">
                        <span class="block font-bold">{{ $request->customer->name }}</span>
                        <span class="block">{{ $request->request_number }} · Rp {{ number_format($cash ? $request->amount : $request->value_snapshot, 0, ',', '.') }}</span>
                        <span class="block text-body-sm">{{ ucfirst(str_replace('_', ' ', $request->status->value)) }} · {{ $request->serviceArea?->name }}</span>
                        @if ($request->exception_reported_at && ! $request->exception_resolved_at)<span class="font-bold text-terracotta">{{ $request->getAttribute('exception_kind') === 'cancellation_request' ? 'Warga meminta batal — perlu konfirmasi pelaksana' : 'Dikunci untuk pemeriksaan Admin' }}</span>@endif
                    </button>
                @empty<p>Belum ada tugas yang dapat dikerjakan.</p>@endforelse
            </div>
            @if ($requests->hasPages())<div class="mt-4">{{ $requests->links('components.ui.pagination') }}</div>@endif
        </x-ui.panel>
        @if ($selected)
            @php($claimant = $cash ? $selected->payer_id : $selected->claimed_by_id)
            @php($exception = $selected->exception_reported_at && ! $selected->exception_resolved_at)
            <x-ui.panel title="Ringkasan sebelum penyerahan">
                <div class="space-y-4">
                    <p class="text-h2 font-bold">{{ $selected->customer->name }}</p>
                    <p>{{ $selected->customer->customerProfile?->customer_number }} · {{ $selected->request_number }}</p>
                    <p class="text-h2 font-bold">Rp {{ number_format($cash ? $selected->amount : $selected->value_snapshot, 0, ',', '.') }}</p>
                    <p>{{ $cash ? $selected->pickup_location : data_get($selected->package_snapshot, 'name') }}</p>
                    @if ($exception)
                        <p role="alert">Penyerahan dikunci. Jangan serahkan uang/paket ulang.</p>
                        @if ($selected->cancellation_requested_at && $selected->getAttribute('exception_kind') === 'cancellation_request' && $claimant === auth()->id())
                            <p>Warga meminta batal. Konfirmasi hanya jika uang/paket dipastikan belum diserahkan. Jika sudah atau tidak pasti, tunggu pemeriksaan Admin.</p>
                            <x-ui.button variant="secondary" wire:click="confirmCancellation" wire:loading.attr="disabled">Belum diserahkan — konfirmasi pembatalan</x-ui.button>
                        @else<p>Tunggu keputusan Admin.</p>@endif
                    @elseif ($claimant === null)
                        <x-ui.button wire:click="claim" wire:loading.attr="disabled">{{ $cash ? 'Ambil pembayaran' : 'Ambil sembako' }}</x-ui.button>
                        @if (! $cash)
                            <x-ui.input name="reason" label="Alasan jika paket tidak tersedia" wire:model="reason" />
                            <x-ui.button variant="secondary" wire:click="unavailable" wire:loading.attr="disabled">Tidak tersedia</x-ui.button>
                        @endif
                    @elseif ($claimant === auth()->id())
                        <p>{{ $cash ? 'Pastikan nama penerima dan nominal sesuai. Catat hanya setelah uang tunai diserahkan.' : 'Pastikan nama warga dan paket sesuai. Catat hanya setelah paket diserahkan.' }}</p>
                        <details class="border-t border-border pt-3" @if ($proof || $errors->has('proof')) open @endif>
                        <summary class="min-h-touch cursor-pointer font-semibold">Tambahkan foto (opsional)</summary>
                        <x-ui.media-picker id="service-proof" property="proof" label="Lampiran foto (opsional)" hint="Tidak wajib. Foto diperkecil sebelum dikirim dan disimpan privat." remove-method="clearProof" confirm-method="confirmProofUpload" />
                        </details>
                        <x-ui.button wire:click="complete" wire:loading.attr="disabled">{{ $cash ? 'Uang tunai sudah diserahkan — catat pembayaran' : 'Paket sudah diserahkan' }}</x-ui.button>
                        @if (! $cash && $selected->status->value !== 'siap_diambil')<x-ui.button variant="secondary" wire:click="ready" wire:loading.attr="disabled">Paket siap diambil (opsional)</x-ui.button>@endif
                        <details class="border-t border-border pt-4" @if ($errors->has('reason') || $errors->has('workflow')) open @endif>
                            <summary class="cursor-pointer font-semibold">Ada kendala?</summary>
                            <div class="mt-3 space-y-3">
                                <x-ui.input name="reason" label="Alasan (minimal 10 karakter)" wire:model="reason" />
                                <x-ui.button variant="secondary" wire:click="release" wire:loading.attr="disabled">Belum diserahkan — lepas tugas</x-ui.button>
                                <x-ui.button variant="secondary" wire:click="reportException" wire:loading.attr="disabled">Penyerahan tidak pasti — minta pemeriksaan Admin</x-ui.button>
                            </div>
                        </details>
                    @endif
                </div>
            </x-ui.panel>
        @endif
    </div>
</section>
