<x-slot:title>Tugas sembako</x-slot:title>
<x-slot:date>{{ now()->translatedFormat('d F Y') }}</x-slot:date>
<x-slot:connectivity>Terhubung</x-slot:connectivity>

<section aria-labelledby="grocery-tasks-title" class="grid gap-6">
    {{-- Page header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-label font-semibold text-forest-600">Petugas</p>
            <h1 id="grocery-tasks-title" class="mt-2 text-h1 font-bold text-deep-green">Tugas Sembako</h1>
            <p class="mt-3 text-body text-text-secondary">
                Ikuti urutan persiapan. Handover memerlukan verifikasi penerima dan bukti privat.
            </p>
        </div>
        <x-ui.mascot variant="4" bubble="Siapkan paket sembako!" bubblePosition="top" class="h-28 w-auto shrink-0" />
    </div>

    @if (session('success'))
        <x-ui.success-state title="Berhasil" :description="session('success')" />
    @endif

    {{-- Free Aid Form --}}
    @if ($canCreateFreeAid)
        <x-ui.panel title="Catat bantuan gratis" description="Bantuan gratis tidak membuat hold dan tidak menghasilkan saldo keluar. Persetujuan dan handover tetap mengikuti state machine.">
            <div class="grid gap-4 md:grid-cols-2">
                <x-ui.select wire:model="freeAidCustomerId" label="Warga penerima" name="freeAidCustomerId"
                    :options="$customerOptions"
                    :error="$errors->first('freeAidCustomerId')" />
                <x-ui.select wire:model="freeAidPackageId" label="Paket" name="freeAidPackageId"
                    :options="$packageOptions"
                    :error="$errors->first('freeAidPackageId')" />
                <x-ui.button type="button" wire:click="createFreeAid" wire:loading.attr="disabled" class="md:col-span-2 md:justify-self-start">
                    <span wire:loading.remove>Catat Bantuan Gratis</span>
                    <span wire:loading>Memproses...</span>
                </x-ui.button>
            </div>
        </x-ui.panel>
    @endif

    {{-- Redemption Tasks --}}
    @forelse ($redemptions as $redemption)
        <x-ui.panel :title="$redemption->request_number" :description="$redemption->customer?->name ?? 'Warga'">
            <div class="grid gap-5">
                {{-- Status + Details --}}
                <dl class="grid gap-3 text-body sm:grid-cols-3">
                    <div class="rounded-lg bg-warm-canvas px-3 py-2">
                        <dt class="text-caption font-medium text-text-secondary">Paket</dt>
                        <dd class="mt-0.5 font-semibold text-deep-green">{{ $redemption->package?->name ?? 'Paket' }}</dd>
                    </div>
                    <div class="rounded-lg bg-warm-canvas px-3 py-2">
                        <dt class="text-caption font-medium text-text-secondary">Nilai Snapshot</dt>
                        <dd class="mt-0.5 amount-tabular font-semibold text-deep-green">Rp{{ number_format($redemption->value_snapshot, 0, ',', '.') }}</dd>
                    </div>
                    <div class="rounded-lg bg-warm-canvas px-3 py-2">
                        <dt class="text-caption font-medium text-text-secondary">Status</dt>
                        <dd class="mt-0.5 font-semibold text-deep-green">{{ str_replace('_', ' ', ucfirst($redemption->status->value)) }}</dd>
                    </div>
                </dl>

                {{-- Actions --}}
                <div class="flex flex-col gap-3 sm:flex-row">
                    @if ($redemption->status->value === 'disetujui')
                        <button type="button" wire:click="prepare({{ $redemption->id }})" wire:loading.attr="disabled"
                            class="inline-flex min-h-touch items-center justify-center gap-2 rounded-xl bg-forest-600 px-5 text-label font-bold text-white transition hover:bg-forest-700 disabled:cursor-wait disabled:opacity-60">
                            <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18M16 10a4 4 0 0 1-8 0"/></svg>
                            Mulai Siapkan
                        </button>
                    @elseif ($redemption->status->value === 'sedang_disiapkan')
                        <button type="button" wire:click="ready({{ $redemption->id }})" wire:loading.attr="disabled"
                            class="inline-flex min-h-touch items-center justify-center gap-2 rounded-xl bg-sky-blue px-5 text-label font-bold text-white transition hover:opacity-90 disabled:cursor-wait disabled:opacity-60">
                            <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10Z"/><path d="m9 12 2 2 4-4"/></svg>
                            Tandai Siap Diambil
                        </button>
                    @elseif ($redemption->status->value === 'siap_diambil')
                        <button type="button" wire:click="select({{ $redemption->id }})"
                            class="inline-flex min-h-touch items-center justify-center gap-2 rounded-xl bg-harvest-gold px-5 text-label font-bold text-deep-green transition hover:opacity-90">
                            <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                            Proses Handover
                        </button>
                    @endif
                </div>
            </div>
        </x-ui.panel>
    @empty
        <div class="rounded-xl border border-border bg-surface p-8 text-center shadow-xs">
            <x-ui.mascot variant="9" class="mx-auto h-24 w-auto" />
            <p class="mt-3 text-label font-bold text-deep-green">Belum ada tugas sembako</p>
            <p class="mt-1.5 text-body-sm text-text-secondary">Penukaran dalam scope Anda akan muncul setelah ada keputusan operasional.</p>
        </div>
    @endforelse

    {{-- Handover Verification --}}
    @if ($selectedRedemptionId !== null)
        <x-ui.panel title="Verifikasi penerima dan bukti" description="Nomor nasabah wajib cocok dengan warga pada record. Bukti disimpan privat." state="success">
            <div class="grid gap-4">
                <x-ui.select wire:model="recipientVerification" label="Metode verifikasi penerima" name="recipientVerification"
                    :options="['kartu_nasabah' => 'Kartu nasabah', 'nomor_nasabah' => 'Nomor nasabah']" />
                <x-ui.input wire:model="recipientReference" label="Nomor/referensi penerima" name="recipientReference"
                    placeholder="Contoh CST-00000001" />
                <div class="space-y-1.5">
                    <label for="grocery-proof" class="block text-label font-semibold text-deep-green">Bukti handover</label>
                    <input id="grocery-proof" wire:model="proof" type="file"
                        accept="image/jpeg,image/png,image/webp,application/pdf"
                        class="block min-h-touch w-full rounded-xl border-2 border-dashed border-border bg-warm-canvas p-4 text-body text-text-secondary transition hover:border-forest-600 focus:outline-none focus:ring-2 focus:ring-focus">
                    @error('proof')
                        <p class="text-body-sm text-terracotta">{{ $message }}</p>
                    @enderror
                </div>
                <x-ui.button type="button" wire:click="handover" wire:loading.attr="disabled">
                    <span wire:loading.remove>Konfirmasi Handover</span>
                    <span wire:loading>Memproses...</span>
                </x-ui.button>
            </div>
        </x-ui.panel>
    @endif
</section>

