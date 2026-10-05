@props([
    'id' => 'public-offline-status',
    'label' => 'Status koneksi',
])

<div
    x-data="{
        online: navigator.onLine,
        actionMessage: '',
        init() {
            window.addEventListener('online', () => {
                this.online = true;
                this.actionMessage = '';
            });
            window.addEventListener('offline', () => {
                this.online = false;
            });
        },
    }"
    x-on:public:offline-action-blocked.window="actionMessage = $event.detail?.message ?? 'Koneksi diperlukan untuk mengirim perubahan ini.'"
    x-show="! online || actionMessage"
    x-cloak
    class="border-b border-harvest-gold bg-warning-bg text-deep-green"
>
    <span
        id="{{ $id }}"
        data-public-offline-status
        class="public-live-region"
        role="status"
        aria-live="polite"
        aria-atomic="true"
        aria-label="{{ $label }}"
    ></span>

    <div class="mx-auto flex w-full max-w-public items-start gap-3 px-4 py-3 sm:px-5">
        <x-public.icon name="circle-alert" size="size-5" class="mt-0.5 text-deep-green" />
        <div class="min-w-0">
            <p class="text-label font-bold" x-text="actionMessage ? 'Aksi belum dikirim' : 'Mode offline aktif'"></p>
            <p x-show="! actionMessage" class="mt-1 text-body-sm leading-5">
                Informasi publik yang pernah dibuka mungkin tetap tersedia, tetapi formulir dan pembaruan data memerlukan koneksi.
            </p>
            <p x-show="actionMessage" x-cloak class="mt-1 text-body-sm leading-5">
                Tidak ada data yang disimpan atau diantrikan. Sambungkan internet, lalu coba lagi.
            </p>
        </div>
    </div>
</div>
