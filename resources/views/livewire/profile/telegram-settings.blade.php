<x-slot:title>Telegram</x-slot:title>
<section class="grid gap-6" aria-labelledby="telegram-title">
    <x-ui.page-heading id="telegram-title" title="Hubungkan Telegram" description="Satu akun Bank Sampah untuk satu Telegram aktif." mascot="6" />
    @if (session('telegram_success'))<p role="status" class="rounded-lg border border-forest-600 bg-success-bg p-4">{{ session('telegram_success') }}</p>@endif
    @if ($errors->any())<div role="alert" class="rounded-lg border border-terracotta bg-danger-bg p-4">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <x-ui.panel :title="$link ? 'Telegram sudah tertaut' : 'Tautkan akun Anda'">
        <div class="grid gap-4">
            @if ($link)
                <p>Telegram berakhiran {{ substr($link->telegram_id, -4) }}. Terhubung tanpa batas waktu selama akun dan hak akses tetap aktif.</p>
                @if ($needsVerification)<p role="status">Data keamanan akun berubah. Verifikasi ulang untuk menggunakan Telegram kembali.</p>@endif
            @endif
            @if (! $link || $needsVerification)<p>Masukkan kata sandi hanya di website ini. Setelah membuka bot, kembali ke halaman ini untuk mengonfirmasi Telegram Anda.</p>@endif
            <p>Data layanan dikirim ke chat Telegram yang tertaut; pesan yang sudah terkirim tetap berada di riwayat Telegram.</p>
            <x-ui.input name="telegram_password" type="password" label="Kata sandi akun saat ini" placeholder="Masukkan kata sandi di website, bukan di chat" wire:model="password" autocomplete="current-password" :error="$errors->first('password')" />
            <div class="flex flex-wrap gap-3">
                @if (! $link || $needsVerification)<x-ui.button wire:click="begin" wire:loading.attr="disabled">{{ $link ? 'Verifikasi ulang akun' : 'Buat tautan sekali pakai' }}</x-ui.button>@endif
                @if ($link)<x-ui.button variant="danger" type="button" x-on:click="$dispatch('open-dialog', { id: 'telegram-revoke', invoker: $el })">Putuskan Telegram</x-ui.button>@endif
            </div>
            @if ($challenge && $linkUrl)
                <div class="grid gap-3 border-t border-border pt-4" wire:poll.5s>
                    <a href="{{ $linkUrl }}" target="_blank" rel="noopener noreferrer" class="app-interactive inline-flex min-h-touch items-center justify-center rounded-md border border-forest-600 px-4 font-semibold text-forest-700">Buka bot Telegram</a>
                    @if ($challenge->telegram_id)
                        <p>Telegram yang membuka tautan: <strong>{{ $challenge->display_name }}</strong>, ID berakhiran {{ substr($challenge->telegram_id, -4) }}. Pastikan ini Telegram Anda.</p>
                        <x-ui.button wire:click="approve" wire:loading.attr="disabled">Ya, hubungkan Telegram ini</x-ui.button>
                    @else<p role="status">Buka tautan di Telegram lalu tekan Mulai. Halaman ini akan memperbarui statusnya.</p>@endif
                    <p class="text-body-sm text-text-secondary">Berlaku sampai {{ $challenge->expires_at->format('H:i') }}.</p>
                </div>
            @elseif ($challengeId)<p role="status">Tautan kedaluwarsa. Buat ulang untuk melanjutkan.</p>@endif
        </div>
    </x-ui.panel>
    <x-ui.panel title="Pilihan notifikasi">
        <div class="grid gap-4">
            <p>Pemberitahuan, pengingat, ringkasan, dan pengumuman tidak aktif sampai Anda memilihnya.</p>
            @foreach (['transaction_notifications' => 'Terima informasi transaksi', 'reminders' => 'Ingatkan tugas/pengajuan yang belum selesai', 'summaries' => 'Kirim ringkasan tabungan berkala', 'announcements' => 'Saya ingin berlangganan pengumuman'] as $field => $label)
                <label class="flex min-h-touch items-center gap-3"><input type="checkbox" wire:model="{{ $field }}" class="size-5 accent-forest-600"><span>{{ $label }}</span></label>
            @endforeach
            <x-ui.select name="summary_frequency" label="Frekuensi ringkasan" wire:model="summary_frequency" :options="['daily' => 'Harian', 'weekly' => 'Mingguan (Senin)', 'monthly' => 'Bulanan (tanggal 1)']" />
            @if ($link)<x-ui.button wire:click="savePreferences" wire:loading.attr="disabled">Simpan pilihan</x-ui.button>@endif
        </div>
    </x-ui.panel>
    <x-ui.dialog id="telegram-revoke" name="telegram-revoke" title="Putuskan Telegram?">
        <p>Telegram ini tidak lagi dapat mengakses akun atau menerima notifikasi. Pengajuan dan saldo tidak dibatalkan. Isi kata sandi akun pada halaman ini sebelum mengonfirmasi.</p>
        <x-slot:actions><x-ui.button variant="secondary" x-on:click="closeModal()">Periksa lagi</x-ui.button><x-ui.button variant="danger" wire:click="revoke" wire:loading.attr="disabled" x-on:click="closeModal()">Ya, putuskan tautan</x-ui.button></x-slot:actions>
    </x-ui.dialog>
</section>
