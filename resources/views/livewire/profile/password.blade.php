<x-slot:title>Profil</x-slot:title>

<section aria-labelledby="profile-password-title" class="grid gap-6">
    <x-ui.page-heading id="profile-password-title" title="Profil dan Keamanan" description="Perbarui data kontak atau kata sandi Anda." mascot="6" />
    @if (\App\Domain\Telegram\Services\TelegramAccess::configured() && auth()->user()?->hasRole('warga', 'petugas', 'bendahara'))
        <x-ui.action-card :href="route('telegram.settings')" icon="log-in" title="Telegram" description="Hubungkan akun dan atur notifikasi." />
    @endif

    <x-ui.panel title="Data profil" :description="auth()->user()?->hasRole('warga') ? 'Wilayah dan RT dikelola oleh Admin.' : null">
        @if ($profileChanged)
            <div class="mb-4 rounded-xl border border-forest-600 bg-success-bg px-4 py-3.5 text-body text-forest-700" role="status" aria-live="polite">
                Data profil berhasil diperbarui.
            </div>
        @endif

        <form class="grid gap-4 sm:grid-cols-2" wire:submit="updateProfile">
            <x-ui.input name="profile_name" label="Nama" placeholder="Contoh: Siti Aminah" wire:model="name" autocomplete="name" :error="$errors->first('name')" />
            <x-ui.input name="profile_phone" label="Nomor telepon" placeholder="Contoh: 081234567890" wire:model="phone" inputmode="tel" autocomplete="tel" :error="$errors->first('phone')" />
            @if (auth()->user()?->hasRole('warga'))
            <x-ui.input name="profile_region" label="Wilayah" wire:model="region" readonly />
            <x-ui.input name="profile_rt" label="RT" wire:model="rt" readonly />
            @if ($canUpdateAddress)
                <x-ui.textarea name="profile_address" label="Alamat" placeholder="Contoh: Kp. Sukamaju RT 01/RW 02, dekat masjid" wire:model="address" rows="3" wrapper-class="sm:col-span-2" :error="$errors->first('address')" />
            @else
                <div class="sm:col-span-2">
                    <p class="text-label font-semibold text-deep-green">Alamat</p>
                    <p class="mt-1 rounded-xl border border-border bg-disabled-bg px-4 py-3 text-body text-text-secondary">Hubungi Admin untuk memperbarui alamat.</p>
                </div>
            @endif
            @endif
            <div class="flex justify-end sm:col-span-2">
                <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="updateProfile">
                    <span wire:loading.remove wire:target="updateProfile">Simpan Profil</span>
                    <span wire:loading wire:target="updateProfile">Menyimpan...</span>
                </x-ui.button>
            </div>
        </form>
    </x-ui.panel>

    <x-ui.panel title="Kata sandi akun" description="Gunakan kata sandi baru yang kuat dan tidak dipakai pada layanan lain.">
        @if ($passwordChanged)
            <div class="mb-4 flex items-center gap-3 rounded-xl border border-forest-600 bg-success-bg px-4 py-3.5 text-body text-deep-green"
                role="status" aria-live="polite">
                <svg viewBox="0 0 24 24" class="size-5 shrink-0 text-forest-600" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10Z"/><path d="m9 12 2 2 4-4"/>
                </svg>
                <span>Kata sandi berhasil diubah. Sesi lain pada akun ini telah diakhiri.</span>
            </div>
        @endif

        @if ($errors->hasAny(['current_password', 'password', 'password_confirmation']))
            <div id="password-change-errors"
                class="mb-4 flex items-center gap-3 rounded-xl border border-terracotta bg-danger-bg px-4 py-3.5"
                role="alert" tabindex="-1"
                x-on:password-change-invalid.window="$nextTick(() => $el.focus())">
                <svg viewBox="0 0 24 24" class="size-5 shrink-0 text-terracotta" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                <p class="text-body-sm text-terracotta">Periksa kata sandi yang dimasukkan, lalu coba lagi.</p>
            </div>
        @endif

        <form class="space-y-5" wire:submit="changePassword"
            @if ($errors->any()) aria-describedby="password-change-errors" @endif>
            <x-ui.input name="current_password" label="Kata sandi saat ini" type="password" placeholder="Masukkan kata sandi saat ini"
                wire:model="current_password" autocomplete="current-password" required
                :error="$errors->first('current_password')" />
            <x-ui.input name="password" label="Kata sandi baru" type="password" placeholder="Buat kata sandi minimal 10 karakter"
                wire:model="password" autocomplete="new-password"
                hint="Minimal 10 karakter." required
                :error="$errors->first('password')" />
            <x-ui.input name="password_confirmation" label="Konfirmasi kata sandi baru" type="password" placeholder="Ulangi kata sandi baru"
                wire:model="password_confirmation" autocomplete="new-password" required />
            <div class="flex justify-end">
                <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="changePassword">
                    <span wire:loading.remove wire:target="changePassword">Simpan Kata Sandi Baru</span>
                    <span wire:loading wire:target="changePassword">Menyimpan...</span>
                </x-ui.button>
            </div>
        </form>
    </x-ui.panel>
</section>
