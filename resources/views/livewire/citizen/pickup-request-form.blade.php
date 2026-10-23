<x-slot:title>Minta penjemputan</x-slot:title>
<section aria-labelledby="pickup-request-title" class="grid gap-5" x-data x-on:focus-pickup-errors.window="$nextTick(() => $refs.pickupErrorSummary?.focus())">
    <x-ui.page-heading id="pickup-request-title" title="Minta penjemputan sampah" description="Tentukan tanggal, lokasi, dan jenis sampah." mascot="8" />
    @if ($errors->any())
        <div x-ref="pickupErrorSummary" id="pickup-error-summary" tabindex="-1" role="alert" class="rounded-xl border border-terracotta bg-danger-bg p-4">
            <h2 class="font-bold">Periksa pengajuan Anda</h2>
            @foreach ($errors->all() as $message)<p>{{ $message }}</p>@endforeach
        </div>
    @endif
    <form wire:submit="submit" class="grid gap-5">
        <x-ui.panel title="Lokasi dan tanggal">
            <div class="grid gap-4 sm:grid-cols-2">
                @if ($areas->count() === 1)<p>Area pelayanan: {{ $areas->first()->name }}</p>@else
                    <x-ui.select name="serviceAreaId" label="Area pelayanan" wire:model="serviceAreaId" :options="$areas->pluck('name', 'id')->all()" placeholder="Pilih area" :error="$errors->first('serviceAreaId')" />
                @endif
                <x-ui.input name="selectedDate" label="Tanggal pilihan" type="date" wire:model="selectedDate" :min="today()->toDateString()" :max="today()->addDays((int) config('app.pickup_booking_horizon_days', 30))->toDateString()" :error="$errors->first('selectedDate')" hint="Pilih hari ini atau maksimal 30 hari ke depan." />
                <x-ui.textarea name="address" label="Alamat penjemputan" placeholder="Contoh: Kp. Sukamaju RT 01/RW 02, dekat masjid" wire:model="address" rows="3" wrapper-class="sm:col-span-2" :error="$errors->first('address')" />
            </div>
        </x-ui.panel>
        <x-ui.panel title="Jenis sampah">
            <div class="space-y-3">
                @foreach ($items as $index => $item)
                    <div wire:key="pickup-item-{{ $index }}" class="grid gap-3 border-b border-border pb-4">
                        <x-ui.select name="items.{{ $index }}.waste_type_id" label="Jenis sampah" wire:model="items.{{ $index }}.waste_type_id" :options="$types->pluck('name', 'id')->all()" placeholder="Pilih jenis sampah" :error="$errors->first('items.'.$index.'.waste_type_id')" />
                        <details @if ($errors->has('items.'.$index.'.estimated_weight_kg') || $errors->has('items.'.$index.'.estimated_quantity')) open @endif>
                            <summary class="min-h-touch cursor-pointer font-semibold">Tambahkan perkiraan (opsional)</summary>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <x-ui.input name="items.{{ $index }}.estimated_weight_kg" label="Perkiraan berat (kg)" placeholder="Contoh: 1,25" inputmode="decimal" wire:model="items.{{ $index }}.estimated_weight_kg" :error="$errors->first('items.'.$index.'.estimated_weight_kg')" />
                                <x-ui.input name="items.{{ $index }}.estimated_quantity" label="Jumlah kantong/karung" placeholder="Contoh: 2" inputmode="numeric" wire:model="items.{{ $index }}.estimated_quantity" :error="$errors->first('items.'.$index.'.estimated_quantity')" />
                            </div>
                        </details>
                        @if (count($items) > 1)<x-ui.button type="button" variant="secondary" wire:click="removeItem({{ $index }})">Hapus jenis ini</x-ui.button>@endif
                    </div>
                @endforeach
                <x-ui.button type="button" variant="secondary" wire:click="addItem">Tambah jenis sampah</x-ui.button>
            </div>
        </x-ui.panel>
        <details class="rounded-xl border border-border bg-surface p-4" @if ($errors->has('notes') || $errors->has('photos') || $errors->has('photos.*') || count($photos)) open @endif>
            <summary class="min-h-touch cursor-pointer font-semibold">Tambahkan catatan atau foto (opsional)</summary>
            <div class="mt-3 space-y-4">
                <x-ui.textarea name="notes" label="Catatan akses (opsional)" placeholder="Contoh: masuk gang sebelah masjid" wire:model="notes" rows="3" :error="$errors->first('notes')" />
                <x-ui.media-picker id="pickup-photos" property="photos" label="Foto sampah (opsional)" hint="Boleh tanpa foto. Maksimal dua foto." :max="2" :multiple="true" remove-method="removePhoto" confirm-method="confirmPhotoUploads" />
            </div>
        </details>
        <p class="text-body text-text-secondary">Saldo ditambahkan setelah sampah ditimbang.</p>
        <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="submit" data-photo-picker-action>
            <span wire:loading.remove wire:target="submit">Kirim pengajuan</span><span wire:loading wire:target="submit">Mengirim...</span>
        </x-ui.button>
    </form>
</section>
