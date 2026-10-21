<x-slot:title>Setoran baru</x-slot:title>
<x-slot:date>{{ now()->translatedFormat('d F Y') }}</x-slot:date>
<x-slot:connectivity><x-ui.connectivity-status /></x-slot:connectivity>

<section aria-labelledby="deposit-title" class="space-y-6">
    {{-- Page header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-label font-semibold text-forest-600">Operasional Petugas</p>
            <h1 id="deposit-title" class="mt-2 text-h1 font-bold text-deep-green">Setoran Baru</h1>
            <p class="mt-3 text-body text-text-secondary">
                Tambahkan berat aktual. Harga dan subtotal selalu dihitung ulang server saat pencatatan.
            </p>
        </div>
        <x-ui.mascot variant="11" bubble="Timbang dengan akurat!" bubblePosition="top" class="h-28 w-auto shrink-0" />
    </div>

    @php
        $depositComplete = $draft?->isFinal();
    @endphp

    <x-ui.panel title="Konteks setoran" description="Identitas warga dan metode ini tetap digunakan sampai setoran selesai.">
        <dl class="grid gap-4 text-body-sm sm:grid-cols-2">
            <div>
                <dt class="text-text-secondary">Warga</dt>
                <dd class="mt-1 text-title font-bold text-deep-green">{{ $customer->name }}</dd>
                <dd class="text-text-secondary">{{ $customer->customerProfile?->customer_number ?? 'Nomor nasabah tidak tersedia' }}</dd>
            </div>
            <div>
                <dt class="text-text-secondary">Metode</dt>
                <dd class="mt-1 font-bold text-deep-green">{{ $mobileService ? 'Layanan keliling' : 'Setoran langsung' }}</dd>
                @if ($mobileService)
                    <dd class="text-text-secondary">{{ $mobileService->point }} · {{ $mobileService->starts_at->translatedFormat('d M, H:i') }}–{{ $mobileService->ends_at->format('H:i') }}</dd>
                @else
                    <dd class="text-text-secondary">Dilayani di area tugas petugas.</dd>
                @endif
            </div>
        </dl>
    </x-ui.panel>


    @if ($depositComplete)
        <x-ui.success-state
            :title="'Setoran berhasil dicatat'"
            :reference="(string) $draft->deposit_number"
            :value="'Rp '.number_format((int) $draft->total_value, 0, ',', '.')"
            :time="$draft->occurred_at->setTimezone('Asia/Jakarta')->translatedFormat('d F Y, H:i')"
            :status="(string) $draft->status"
            :description="session('success')"
        >
            <x-slot:actions>
                <div class="flex flex-col items-end">
                    <button type="button" wire:click="startNewDeposit" class="inline-flex min-h-touch items-center justify-center rounded-md border border-border bg-surface px-5 text-label text-deep-green transition hover:border-forest-600 hover:bg-success-bg active:translate-y-px">Buat setoran baru</button>
                </div>
            </x-slot:actions>
        </x-ui.success-state>
    @elseif (session('success'))
        <div role="status" class="flex items-center gap-3 rounded-xl border border-forest-600/25 bg-success-bg px-4 py-3.5 text-body text-deep-green">
            <svg viewBox="0 0 24 24" class="size-5 shrink-0 text-forest-600" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 6.477 10 12 10Z"/><path d="m9 12 2 2 4-4"/>
            </svg>
            <div>{{ session('success') }}</div>
        </div>
    @endif


    @unless ($depositComplete)
    <x-ui.panel title="Item setoran" description="Berat dalam kilogram dengan maksimal tiga angka desimal.">
        <div class="space-y-3">
            @forelse ($items as $index => $item)
                <fieldset class="grid grid-cols-2 gap-3 border-b border-border pb-4 md:grid-cols-[1fr_1fr_1fr_auto] md:items-end"
                    wire:key="deposit-item-{{ $index }}">
                    <legend class="sr-only">Item setoran {{ $index + 1 }}</legend>
                    <div class="col-span-2 md:col-span-1">
                    <x-ui.select name="items.{{ $index }}.waste_type_id" label="Jenis sampah"
                         wire:model.live="items.{{ $index }}.waste_type_id"
                        :error="$errors->first('items.'.$index.'.waste_type_id')">
                        <option value="">Pilih jenis</option>
                        @foreach ($types as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </x-ui.select>
                    </div>

                    <x-ui.select name="items.{{ $index }}.condition_id" label="Kondisi"
                         wire:model.live="items.{{ $index }}.condition_id"
                        :error="$errors->first('items.'.$index.'.condition_id')">
                        <option value="">Pilih kondisi</option>
                        @foreach ($conditionsByType->get((int) $item['waste_type_id'], collect()) as $condition)
                            <option value="{{ $condition->id }}">{{ $condition->name }}</option>
                        @endforeach
                    </x-ui.select>

                    <x-ui.input name="items.{{ $index }}.weight_kg" label="Berat (kg)"
                         inputmode="decimal" wire:model.live.debounce.400ms="items.{{ $index }}.weight_kg"
                        :error="$errors->first('items.'.$index.'.weight_kg')" />

                    <button type="button" wire:click="removeItem({{ $index }})" aria-label="Hapus item setoran {{ $index + 1 }}"
                        class="col-span-2 inline-flex min-h-touch items-center justify-center justify-self-end rounded-md px-4 text-label font-semibold text-terracotta transition hover:bg-danger-bg md:col-span-1">
                        Hapus
                    </button>
                </fieldset>
            @empty
                <div class="rounded-xl border border-dashed border-border bg-warm-canvas p-6 text-center">
                    <x-ui.mascot variant="9" class="mx-auto h-16 w-auto" />
                    <p class="mt-2 text-label font-semibold text-deep-green">Belum ada item</p>
                    <p class="mt-1 text-caption text-text-secondary">Tambahkan minimal satu jenis sampah.</p>
                </div>
            @endforelse

            <div class="rounded-md border border-border bg-surface p-4" aria-live="polite">
                <div class="flex flex-col gap-1 sm:flex-row sm:items-baseline sm:justify-between">
                    <div>
                        <h3 class="text-label font-bold text-deep-green">Ringkasan nilai setoran</h3>
                        <p class="text-body-sm text-text-secondary">Harga mengikuti waktu pembuatan draf jika ada; subtotal dihitung ulang server saat pencatatan.</p>
                    </div>
                    @if ($pricePreview['complete'])
                        <strong class="amount-tabular text-title text-forest-700">Rp {{ number_format($pricePreview['total'], 0, ',', '.') }}</strong>
                    @else
                        <span class="text-body-sm font-semibold text-harvest-gold">Lengkapi item untuk melihat nilai</span>
                    @endif
                </div>
                @if ($pricePreview['lines'] !== [])
                    <dl class="mt-3 divide-y divide-border border-t border-border text-body-sm">
                        @foreach ($pricePreview['lines'] as $line)
                            <div class="flex items-center justify-between gap-3 py-2">
                                <dt class="min-w-0"><span class="block font-semibold text-deep-green">{{ $line['name'] }}</span><span class="text-text-secondary">{{ $line['condition'] }} · {{ \App\Support\WeightFormatter::format($line['weight']) }} kg</span></dt>
                                <dd class="shrink-0 amount-tabular font-semibold text-deep-green">Rp {{ number_format($line['subtotal'], 0, ',', '.') }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </div>

            <div class="flex flex-col items-end gap-3 pt-2 sm:flex-row sm:justify-end">
                <x-ui.button type="button" wire:click="addItem" variant="secondary">
                    <svg viewBox="0 0 24 24" class="mr-2 size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                    Tambah Item
                </x-ui.button>
                <x-ui.button type="button" wire:click="saveDraft" wire:loading.attr="disabled" data-photo-picker-action>
                    <span wire:loading.remove wire:target="saveDraft">Simpan Draf</span>
                    <span wire:loading wire:target="saveDraft">Menyimpan...</span>
                </x-ui.button>
             </div>
         </div>
     </x-ui.panel>

     <details class="rounded-xl border border-border bg-surface p-4" @if ($evidence || $errors->has('evidence')) open @endif>
     <summary class="min-h-touch cursor-pointer font-semibold">Tambahkan lampiran setoran (opsional)</summary>
     <div class="mt-3">
         <x-ui.media-picker
             id="deposit-evidence"
             property="evidence"
             label="Bukti transaksi"
             hint="Foto JPEG, PNG, atau WebP dikompres menjadi JPEG maksimal 1 MB. PDF diterima maksimal 5 MB."
             :allow-pdf="true"
             remove-method="clearEvidence"
             confirm-method="confirmEvidenceUpload"
         />
         @error('evidence')
             <p class="mt-2 text-body-sm font-semibold text-terracotta">{{ $message }}</p>
         @enderror
      </div>
      </details>
      <div class="rounded-xl border border-border bg-warning-bg p-4">
          <p class="text-body-sm text-deep-green">Periksa nama warga, jenis, berat, nilai, dan bukti pada halaman ini sebelum mencatat. Harga mengikuti waktu pembuatan draf jika Anda melanjutkan draf tersimpan.</p>
          <div class="mt-4 flex justify-end">
              <x-ui.button type="button" wire:click="finalize" wire:loading.attr="disabled" wire:target="finalize" data-photo-picker-action>
                  <span wire:loading.remove wire:target="finalize">{{ 'Data sudah benar - catat setoran' }}</span>
                  <span wire:loading wire:target="finalize">Mencatat...</span>
              </x-ui.button>
          </div>
      </div>
    @endunless
 </section>
