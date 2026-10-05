<x-filament-panels::page>
    @if (session('operations_notice'))<div class="mb-6 rounded-lg border border-success-200 bg-success-50 px-4 py-3 text-sm text-success-800" role="status">{{ session('operations_notice') }}</div>@endif

    <section class="rounded-xl border border-danger-200 bg-danger-50 p-5 shadow-sm" aria-labelledby="technical-media-retention-title">
        <h2 id="technical-media-retention-title" class="text-xl font-semibold text-gray-950">Pembersihan foto penjemputan</h2>
        <p class="mt-1 max-w-3xl text-sm leading-6 text-gray-700">Hanya foto privat dari pengajuan berstatus selesai, ditolak, atau dibatalkan yang dibuat sebelum batas tanggal. Foto pengajuan aktif, bukti pembayaran, foto setoran, gambar master sampah, dan ekspor laporan tidak ikut dihapus.</p>

        <div class="mt-4 rounded-lg border border-danger-200 bg-white/70 p-4 text-sm leading-6 text-gray-700">
            <p class="font-semibold text-gray-950">Pengaman penghapusan</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                <li>Usia foto minimal {{ $mediaRetentionMinimumAgeDays }} hari.</li>
                <li>Maksimal {{ $mediaRetentionBatchLimit }} foto per eksekusi agar aman pada layanan hosting dengan sumber daya terbatas.</li>
                <li>Pratinjau wajib dijalankan ulang setelah 10 menit atau saat batas tanggal berubah.</li>
            </ul>
        </div>

        <div class="mt-5 flex max-w-3xl flex-col gap-3 sm:flex-row sm:items-end">
            <label class="block flex-1 text-sm font-medium text-gray-800">Hapus foto yang dibuat sebelum tanggal
                <input wire:model="mediaRetentionBefore" type="date" max="{{ $mediaRetentionLatestCutoff }}" required class="mt-2 backoffice-form-control">
                @error('mediaRetentionBefore')<span class="mt-2 block text-sm font-medium text-danger-700">{{ $message }}</span>@enderror
            </label>
            <button wire:click="previewMediaRetention" wire:loading.attr="disabled" type="button" class="min-h-11 rounded-lg border border-gray-300 px-4 text-sm font-semibold text-gray-800 hover:bg-gray-50 disabled:cursor-wait disabled:opacity-60">Pratinjau</button>
            <button wire:click="executeMediaRetention" wire:loading.attr="disabled" type="button" @disabled($mediaRetentionPreviewBefore === '' || $mediaRetentionCandidates === []) wire:confirm="Hapus foto penjemputan yang tampil pada pratinjau terbaru? File dan catatan medianya akan dihapus permanen dari aplikasi." class="min-h-11 rounded-lg bg-red-700 px-4 text-sm font-semibold text-white hover:bg-red-800 disabled:cursor-not-allowed disabled:opacity-50">Hapus batch</button>
        </div>

        @if ($mediaRetentionResult !== '')<p class="mt-3 text-sm font-medium text-gray-800" role="status">{{ $mediaRetentionResult }}</p>@endif
    </section>

    @if ($mediaRetentionPreviewBefore !== '')
        <section class="mt-6 rounded-xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="technical-media-preview-title">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 id="technical-media-preview-title" class="text-lg font-semibold text-gray-950">Kandidat batch berikutnya</h2>
                    <p class="mt-1 text-sm leading-6 text-gray-600">{{ number_format($mediaRetentionCandidateCount, 0, ',', '.') }} foto memenuhi batas retensi ({{ $mediaRetentionCandidateSize }}). Eksekusi ini memuat {{ count($mediaRetentionCandidates) }} foto.</p>
                </div>
                @if ($mediaRetentionMissingFiles > 0)
                    <span class="inline-flex w-fit rounded-full bg-warning-100 px-3 py-1 text-xs font-semibold text-warning-800">{{ $mediaRetentionMissingFiles }} file sudah tidak ada</span>
                @endif
            </div>

            @if ($mediaRetentionCandidates === [])
                <p class="mt-5 rounded-lg bg-gray-50 px-4 py-5 text-sm text-gray-600">Tidak ada foto yang memenuhi batas retensi ini.</p>
            @else
                <div class="mt-5 grid gap-3 lg:grid-cols-2">
                    @foreach ($mediaRetentionCandidates as $candidate)
                        <article class="rounded-lg border border-gray-200 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-gray-950">{{ $candidate['original_name'] }}</p>
                                    <p class="mt-1 text-xs text-gray-600">{{ $candidate['pickup_number'] }} · {{ str_replace('_', ' ', $candidate['pickup_status']) }}</p>
                                </div>
                                <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold {{ $candidate['file_exists'] ? 'bg-success-50 text-success-800' : 'bg-warning-100 text-warning-800' }}">{{ $candidate['file_exists'] ? 'File tersedia' : 'File hilang' }}</span>
                            </div>
                            <dl class="mt-4 grid grid-cols-2 gap-3 text-xs">
                                <div><dt class="text-gray-500">Ukuran</dt><dd class="mt-1 font-medium text-gray-800">{{ number_format($candidate['size'] / 1024, 1, ',', '.') }} KB</dd></div>
                                <div><dt class="text-gray-500">Dibuat</dt><dd class="mt-1 font-medium text-gray-800">{{ $candidate['created_at'] }}</dd></div>
                            </dl>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    @endif
</x-filament-panels::page>
