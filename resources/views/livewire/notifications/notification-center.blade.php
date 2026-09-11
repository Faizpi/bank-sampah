<x-slot:title>Notifikasi</x-slot:title>
<x-slot:context>Pemberitahuan</x-slot:context>

<section aria-labelledby="notification-center-title" aria-live="polite" class="grid gap-6">
    {{-- Page header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-label font-semibold text-forest-600">Pusat Pemberitahuan</p>
            <h1 id="notification-center-title" class="mt-2 text-h1 font-bold text-deep-green">Notifikasi</h1>
            <p class="mt-3 text-body text-text-secondary">Pembaruan untuk akun dan aktivitas Anda.</p>
        </div>
        <x-ui.mascot variant="13" class="h-24 w-auto shrink-0" />
    </div>

    @if ($notifications->isEmpty())
        <div class="rounded-xl border border-border bg-surface p-8 text-center shadow-xs">
            <x-ui.mascot variant="9" bubble="Belum ada notifikasi" bubblePosition="bottom" class="mx-auto h-24 w-auto" />
            <p class="mt-8 text-label font-semibold text-deep-green">Semua bersih!</p>
            <p class="mt-1.5 text-body-sm text-text-secondary">Pembaruan untuk akun dan aktivitas Anda akan muncul di sini.</p>
        </div>
    @else
        <div class="grid gap-3" role="list" aria-label="Daftar notifikasi">
            @foreach ($notifications as $notification)
                <x-ui.panel
                    role="listitem"
                    :state="$notification['read'] ? 'default' : 'success'"
                    class="relative">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                @if (!$notification['read'])
                                    <span class="size-2 shrink-0 rounded-full bg-forest-600" aria-hidden="true"></span>
                                @endif
                                <h2 class="text-title font-bold text-deep-green">{{ $notification['title'] }}</h2>
                                <span class="text-caption text-text-secondary">
                                    {{ $notification['read'] ? 'Sudah dibaca' : 'Belum dibaca' }}
                                </span>
                            </div>
                            <p class="mt-2 text-body text-text-primary">{{ $notification['body'] }}</p>
                        </div>

                        @if (!$notification['read'])
                            <x-ui.button type="button" variant="quiet"
                                wire:click="markAsRead({{ $notification['id'] }})"
                                wire:loading.attr="disabled"
                                wire:target="markAsRead({{ $notification['id'] }})"
                                aria-label="Tandai {{ $notification['title'] }} sebagai sudah dibaca">
                                Tandai dibaca
                            </x-ui.button>
                        @endif
                    </div>

                    @if ($notification['reference'])
                        <a href="{{ $notification['reference'] }}"
                            class="mt-4 inline-flex min-h-touch items-center gap-1.5 text-label font-semibold text-forest-600 underline decoration-transparent underline-offset-4 hover:decoration-current focus-visible:rounded-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-forest-600">
                            Lihat detail
                            <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                        </a>
                    @endif
                </x-ui.panel>
            @endforeach
        </div>
    @endif
</section>