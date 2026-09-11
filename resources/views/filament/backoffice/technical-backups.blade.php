<x-filament-panels::page>
    @if (session('operations_notice'))<div class="mb-6 rounded-lg border border-success-200 bg-success-50 px-4 py-3 text-sm text-success-800" role="status">{{ session('operations_notice') }}</div>@endif
    @if ($canRunBackup)
        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="technical-backup-title">
            <h2 id="technical-backup-title" class="text-xl font-semibold text-gray-950">Catat metadata cadangan</h2>
            <p class="mt-1 max-w-2xl text-sm leading-6 text-gray-600">Catat lokasi, kode pemeriksaan berkas, ukuran, status, dan masa simpan. Berkas cadangan dibuat melalui prosedur penerapan.</p>
            <form x-on:submit.prevent="if ($el.reportValidity()) $dispatch('open-dialog', { id: 'backup-metadata-dialog', invoker: $event.submitter })" class="mt-6 grid w-full max-w-3xl gap-4 sm:grid-cols-2">
                @foreach ([
                    ['backup-database-alias', 'backupDatabaseAlias', 'Lokasi basis data', 'text', 'Contoh: backup-db-20260811'],
                    ['backup-media-alias', 'backupMediaAlias', 'Lokasi media', 'text', 'Contoh: backup-media-20260811'],
                    ['backup-database-sha256', 'backupDatabaseSha256', 'Kode pemeriksaan cadangan basis data', 'text', '64 karakter heksadesimal'],
                    ['backup-media-sha256', 'backupMediaSha256', 'Kode pemeriksaan cadangan media', 'text', '64 karakter heksadesimal'],
                    ['backup-database-size-bytes', 'backupDatabaseSizeBytes', 'Ukuran basis data (byte)', 'text', ''],
                    ['backup-media-size-bytes', 'backupMediaSizeBytes', 'Ukuran media (byte)', 'text', ''],
                    ['backup-retention-until', 'backupRetentionUntil', 'Pertahankan sampai', 'datetime-local', ''],
                    ['backup-operator-key', 'backupOperatorKey', 'Kunci operasi', 'text', 'Kunci unik minimal 16 karakter'],
                ] as [$id, $property, $label, $type, $placeholder])
                    <div>
                        <label for="{{ $id }}" class="block text-sm font-medium text-gray-800">{{ $label }}</label>
                        <input id="{{ $id }}" wire:model="{{ $property }}" type="{{ $type }}" @if ($placeholder !== '') placeholder="{{ $placeholder }}" @endif @if (str_contains($property, 'Sha256')) minlength="64" maxlength="64" @elseif ($property === 'backupOperatorKey') minlength="16" maxlength="191" @endif @if (str_contains($property, 'SizeBytes')) inputmode="numeric" @endif required class="mt-2 backoffice-form-control" @error($property) aria-invalid="true" aria-describedby="{{ $id }}-error" @enderror>
                        @error($property)<p id="{{ $id }}-error" class="mt-2 text-sm text-danger-700" role="alert">{{ $message }}</p>@enderror
                    </div>
                @endforeach
                <div class="sm:col-span-2"><button type="submit" class="min-h-11 w-full rounded-lg bg-emerald-700 px-4 text-sm font-semibold text-white hover:bg-emerald-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2 sm:w-auto">Catat metadata cadangan</button></div>
            </form>
        </section>
    @endif

    @if ($canRestoreBackup)
        <section class="mt-6 rounded-xl border border-info-200 bg-info-50 p-5 shadow-sm" aria-labelledby="technical-restore-title">
            <h2 id="technical-restore-title" class="text-xl font-semibold text-gray-950">Verifikasi pemulihan</h2>
            <p class="mt-1 max-w-2xl text-sm leading-6 text-gray-700">Verifikasi di lingkungan terisolasi. Halaman ini hanya mencatat bukti dan hasil, bukan menjalankan pemulihan.</p>
            <form x-on:submit.prevent="if ($el.reportValidity()) $dispatch('open-dialog', { id: 'restore-verification-dialog', invoker: $event.submitter })" class="mt-6 grid w-full max-w-3xl gap-4 sm:grid-cols-2">
                @foreach ([
                    ['restore-backup-id', 'restoreBackupId', 'ID cadangan'],
                    ['restore-target-alias', 'restoreTargetAlias', 'Alias lingkungan verifikasi'],
                    ['restore-evidence-reference', 'restoreEvidenceReference', 'Referensi bukti verifikasi'],
                ] as [$id, $property, $label])
                    <div>
                        <label for="{{ $id }}" class="block text-sm font-medium text-gray-800">{{ $label }}</label>
                        <input id="{{ $id }}" wire:model="{{ $property }}" @if ($property === 'restoreBackupId') inputmode="numeric" @elseif ($property === 'restoreEvidenceReference') minlength="43" maxlength="43" @endif required class="mt-2 backoffice-form-control" @error($property) aria-invalid="true" aria-describedby="{{ $id }}-error" @enderror>
                        @error($property)<p id="{{ $id }}-error" class="mt-2 text-sm text-danger-700" role="alert">{{ $message }}</p>@enderror
                    </div>
                @endforeach
                <div>
                    <label for="restore-result" class="block text-sm font-medium text-gray-800">Hasil verifikasi</label>
                    <select id="restore-result" wire:model="restoreResult" required class="mt-2 backoffice-form-control" @error('restoreResult') aria-invalid="true" aria-describedby="restore-result-error" @enderror><option value="">Pilih hasil</option><option value="passed">Lulus</option><option value="failed">Gagal</option></select>
                    @error('restoreResult')<p id="restore-result-error" class="mt-2 text-sm text-danger-700" role="alert">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2"><button type="submit" class="min-h-11 w-full rounded-lg bg-sky-700 px-4 text-sm font-semibold text-white hover:bg-sky-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2 sm:w-auto">Simpan hasil verifikasi</button></div>
            </form>
        </section>
    @endif

    <x-ui.dialog id="backup-metadata-dialog" name="backup-metadata" title="Catat metadata cadangan" description="Berkas cadangan tidak dibuat oleh formulir ini.">
        <p>Lokasi, kode pemeriksaan, ukuran, status, dan retensi yang terlihat akan dicatat.</p>
        @if ($errors->hasAny(['backupDatabaseAlias', 'backupMediaAlias', 'backupDatabaseSha256', 'backupMediaSha256', 'backupDatabaseSizeBytes', 'backupMediaSizeBytes', 'backupRetentionUntil', 'backupOperatorKey']))
            <div role="alert" tabindex="-1" class="mt-4 rounded-lg border border-danger-200 bg-danger-50 p-4 text-sm text-danger-800"><p class="font-semibold">Metadata belum dapat dicatat</p><ul class="mt-2 list-disc space-y-1 pl-5">@foreach (['backupDatabaseAlias', 'backupMediaAlias', 'backupDatabaseSha256', 'backupMediaSha256', 'backupDatabaseSizeBytes', 'backupMediaSizeBytes', 'backupRetentionUntil', 'backupOperatorKey'] as $field)@error($field)<li>{{ $message }}</li>@enderror @endforeach</ul><p class="mt-2 font-medium">Tutup dialog dan periksa kembali kolom metadata yang ditandai.</p></div>
        @endif
        <x-slot:actions><x-ui.button type="button" variant="secondary" x-on:click="closeModal()">Kembali</x-ui.button><x-ui.button type="button" wire:click="recordBackupMetadata" wire:loading.attr="disabled" wire:target="recordBackupMetadata">Catat metadata</x-ui.button></x-slot:actions>
    </x-ui.dialog>
    <x-ui.dialog id="restore-verification-dialog" name="restore-verification" title="Simpan hasil verifikasi pemulihan" description="Hasil akan dikaitkan dengan cadangan dan lingkungan yang dipilih.">
        <p>Pastikan referensi bukti dan hasil verifikasi sudah benar.</p>
        @if ($errors->hasAny(['restoreBackupId', 'restoreTargetAlias', 'restoreEvidenceReference', 'restoreResult']))
            <div role="alert" tabindex="-1" class="mt-4 rounded-lg border border-danger-200 bg-danger-50 p-4 text-sm text-danger-800"><p class="font-semibold">Verifikasi belum dapat disimpan</p><ul class="mt-2 list-disc space-y-1 pl-5">@foreach (['restoreBackupId', 'restoreTargetAlias', 'restoreEvidenceReference', 'restoreResult'] as $field)@error($field)<li>{{ $message }}</li>@enderror @endforeach</ul><p class="mt-2 font-medium">Tutup dialog dan periksa kembali data pemulihan yang ditandai.</p></div>
        @endif
        <x-slot:actions><x-ui.button type="button" variant="secondary" x-on:click="closeModal()">Kembali</x-ui.button><x-ui.button type="button" wire:click="recordRestoreVerification" wire:loading.attr="disabled" wire:target="recordRestoreVerification">Simpan hasil</x-ui.button></x-slot:actions>
    </x-ui.dialog>

    @if ($canViewBackups)
        <section class="mt-6 rounded-xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="backup-history-title">
            <h2 id="backup-history-title" class="text-xl font-semibold text-gray-950">Metadata cadangan terakhir</h2>
            @if ($backups->isEmpty())
                <p class="mt-3 text-sm text-gray-600">Belum ada metadata cadangan yang dapat ditampilkan.</p>
            @else
                <div class="mt-4 space-y-3">
                    @foreach ($backups as $backup)
                        <details class="group rounded-lg border border-gray-200 p-4">
                            <summary class="flex min-h-11 cursor-pointer list-none items-start justify-between gap-4 rounded-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2">
                                <span class="min-w-0">
                                    <span class="block font-semibold text-gray-950">Cadangan #{{ $backup->id }}</span>
                                    <span class="mt-1 block text-sm text-gray-600">{{ \App\Support\StatusLabel::for($backup->status) }}</span>
                                    <span class="mt-1 block text-xs text-gray-500">Dibuat {{ $backup->created_at?->setTimezone('Asia/Jakarta')->format('d M Y H:i') ?? 'tanggal tidak tersedia' }}</span>
                                </span>
                                <svg data-disclosure-chevron viewBox="0 0 24 24" class="mt-2 size-5 shrink-0 text-primary-700 transition-transform group-open:rotate-180 motion-reduce:transition-none" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                            </summary>
                            <dl class="mt-4 grid gap-3 border-t border-gray-200 pt-4 text-sm sm:grid-cols-2">
                                <div>
                                    <dt class="text-gray-600">Basis data</dt>
                                    <dd class="mt-1 text-gray-950">{{ $backup->database_location_alias }} · {{ $backup->humanSize($backup->database_size_bytes) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-gray-600">Media</dt>
                                    <dd class="mt-1 text-gray-950">{{ $backup->media_location_alias }} · {{ $backup->humanSize($backup->media_size_bytes) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-gray-600">Pemulihan</dt>
                                    <dd class="mt-1 text-gray-950">{{ $backup->restore_verification_result?->value ? \App\Support\StatusLabel::for($backup->restore_verification_result) : 'Belum diuji' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-gray-600">Retensi</dt>
                                    <dd class="mt-1 text-gray-950">{{ $backup->retention_until->setTimezone('Asia/Jakarta')->format('d M Y H:i') }}</dd>
                                </div>
                            </dl>
                        </details>
                    @endforeach
                </div>
            @endif
        </section>
    @endif
</x-filament-panels::page>
