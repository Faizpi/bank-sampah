<div
    x-data="{
        online: navigator.onLine,
        init() {
            window.addEventListener('online', () => this.online = true);
            window.addEventListener('offline', () => this.online = false);
        },
    }"
    data-connectivity-status
    class="shrink-0"
    role="status"
    aria-live="polite"
    aria-atomic="true"
>
    <span
        x-show="online"
        x-cloak
        class="inline-flex min-h-8 items-center gap-1.5 rounded-full border border-success-bg bg-success-bg px-2.5 text-caption font-semibold text-forest-700"
    >
        <svg viewBox="0 0 24 24" class="size-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M8.5 16.05a6 6 0 0 1 7 0"/><path d="M12 19.55h.01"/>
        </svg>
        Terhubung
    </span>
    <span
        x-show="! online"
        x-cloak
        class="inline-flex min-h-8 items-center gap-1.5 rounded-full border border-harvest-gold bg-warning-bg px-2.5 text-caption font-semibold text-deep-green"
    >
        <svg viewBox="0 0 24 24" class="size-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M1 1l22 22"/><path d="M16.72 11.06A10.94 10.94 0 0 1 19 12.55"/><path d="M5 12.55a10.94 10.94 0 0 1 3.07-1.57"/><path d="M8.5 16.05a6 6 0 0 1 2.12-.92"/><path d="M13.38 15.38a6 6 0 0 1 2.12.67"/><path d="M12 19.55h.01"/>
        </svg>
        Offline — koneksi diperlukan
    </span>
</div>
