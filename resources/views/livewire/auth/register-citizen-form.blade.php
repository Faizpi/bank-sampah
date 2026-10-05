<div class="container-citizen py-8 sm:py-12">
    <section class="overflow-hidden rounded-2xl border border-border bg-surface shadow-md" aria-labelledby="registration-title">
        <div class="relative isolate overflow-hidden border-b border-border bg-warm-canvas p-6 sm:p-8 text-deep-green">
            <div class="flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
                <div class="w-full min-w-0 max-w-lg">
                    <span class="inline-flex rounded-full border border-forest-600 bg-success-bg px-3 py-1 text-xs font-semibold text-forest-700">Pendaftaran Akun Baru</span>
                    <h1 id="registration-title" class="mt-2 text-pretty text-h2 font-extrabold text-deep-green lg:text-h1">Daftar sebagai Warga Bank Sampah</h1>
                    <p class="mt-2 text-body-sm text-text-secondary">Data pendaftaran akan diperiksa admin. Akun dapat digunakan untuk masuk setelah verifikasi selesai.</p>
                </div>
                <div class="flex items-center justify-between gap-4 md:flex-col md:items-end">
                    <x-ui.mascot variant="5" bubble="Ayo gabung bersama kami!" bubblePosition="top" class="h-28 w-auto filter drop-shadow-lg" />
                </div>
            </div>
            
            <nav class="mt-6 flex flex-wrap items-center gap-4 border-t border-border pt-4 text-xs font-medium text-text-secondary" aria-label="Navigasi akses">
                <a href="{{ route('home') }}" class="inline-flex items-center gap-1 hover:text-deep-green transition-colors">
                    <x-public.icon name="arrow-right" size="size-3.5" class="rotate-180" />
                    Kembali ke beranda
                </a>
                <span class="text-text-secondary/40">•</span>
                <a href="{{ route('login') }}" class="inline-flex items-center gap-1 font-bold text-forest-600 hover:text-forest-700 transition-colors">
                    Sudah punya akun? Masuk
                    <x-public.icon name="arrow-right" size="size-3.5" />
                </a>
            </nav>
        </div>

        <div class="bg-surface px-5 py-6 sm:p-8 md:p-8">
            @if ($registered)
                <div id="registration-success" class="mx-auto max-w-form rounded-2xl border border-forest-600/20 bg-success-bg p-6 text-center shadow-sm" role="status" aria-live="polite" tabindex="-1" x-init="$nextTick(() => $el.focus())">
                    <x-ui.mascot variant="12" bubble="Pendaftaran Berhasil!" bubblePosition="top" class="mx-auto h-28 w-auto mb-3" />
                    <h2 class="text-h3 font-extrabold text-deep-green">Pendaftaran Diterima!</h2>
                    <p class="mt-2 text-body-sm text-text-secondary max-w-md mx-auto">Terima kasih sudah mendaftar! Akun Anda sedang dalam proses verifikasi oleh pengurus RT / bank sampah desa.</p>
                    
                    <div class="mt-6 flex flex-col gap-3 justify-center sm:flex-row">
                        <a href="{{ route('home') }}" class="inline-flex min-h-touch items-center justify-center rounded-xl border border-border bg-surface px-5 text-label font-bold text-deep-green transition duration-180 hover:bg-warm-canvas">Kembali ke Beranda</a>
                        <a href="{{ route('login') }}" class="inline-flex min-h-touch items-center justify-center gap-2 rounded-xl bg-forest-600 px-5 text-label font-bold text-surface transition duration-180 hover:bg-forest-700">Ke Halaman Masuk <x-public.icon name="arrow-right" size="size-5" /></a>
                    </div>
                </div>
            @else
                @if ($errors->any())
                    @php
                        $errorFieldLabels = [
                            'name' => 'Nama lengkap',
                            'phone' => 'Nomor telepon',
                            'password' => 'Kata sandi',
                            'password_confirmation' => 'Konfirmasi kata sandi',
                            'rt_id' => 'RT domisili',
                            'address' => 'Alamat lengkap',
                            'terms_accepted' => 'Persetujuan ketentuan dan kebijakan privasi',
                        ];
                    @endphp
                    <div id="registration-errors" class="mx-auto max-w-form rounded-xl border border-terracotta/40 bg-danger-bg p-4 shadow-xs" role="alert" tabindex="-1" x-on:registration-invalid.window="$nextTick(() => $el.focus())">
                        <div class="flex items-start gap-3">
                            <x-public.icon name="circle-alert" size="size-5" class="mt-0.5 shrink-0 text-terracotta" />
                            <div class="min-w-0">
                                <h2 class="text-title text-deep-green font-bold">Periksa data pendaftaran</h2>
                                <p class="mt-1 break-words text-body-sm text-text-secondary">Perbaiki field yang diberi keterangan sebelum mengirim pendaftaran.</p>
                                <ul class="mt-3 flex flex-wrap gap-x-4 gap-y-2 text-body-sm" aria-label="Field yang perlu diperbaiki">
                                    @foreach ($errors->keys() as $errorKey)
                                        @if (isset($errorFieldLabels[$errorKey]))
                                            <li>
                                                <a href="#{{ $errorKey }}" class="inline-flex min-h-touch items-center rounded-md font-semibold text-forest-600 underline underline-offset-4 hover:text-forest-700 focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2" x-on:click="$nextTick(() => document.getElementById('{{ $errorKey }}')?.focus())">{{ $errorFieldLabels[$errorKey] }}</a>
                                            </li>
                                        @endif
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                @endif

                <form class="mx-auto mt-4 max-w-form space-y-6" wire:submit="register" wire:loading.attr="aria-busy" wire:target="register" @if ($errors->any()) aria-describedby="registration-errors" @endif>
                    <section class="space-y-4" aria-labelledby="registration-personal-title">
                        <div class="flex items-center gap-2 border-b border-border pb-2">
                            <x-public.icon name="user" size="size-4" class="text-forest-600" />
                            <h2 id="registration-personal-title" class="text-title font-bold text-deep-green">Data Diri Warga</h2>
                        </div>
                        <div class="grid gap-5 md:grid-cols-2">
                            <x-ui.input name="name" label="Nama lengkap" wire:model.blur="name" autocomplete="name" required :error="$errors->first('name')" />
                            <x-ui.input name="phone" label="Nomor telepon" type="tel" wire:model.blur="phone" autocomplete="tel" inputmode="tel" hint="Gunakan nomor Indonesia yang aktif." required :error="$errors->first('phone')" />
                        </div>
                    </section>

                    <section class="space-y-4" aria-labelledby="registration-security-title">
                        <div class="flex items-center gap-2 border-b border-border pb-2">
                            <x-public.icon name="lock" size="size-4" class="text-forest-600" />
                            <h2 id="registration-security-title" class="text-title font-bold text-deep-green">Keamanan Akun</h2>
                        </div>
                        <div class="grid gap-5 md:grid-cols-2">
                            <div class="space-y-2" x-data="{ showPassword: false }">
                                <label for="password" class="block text-label font-semibold text-text-primary">Kata sandi</label>
                                <div class="relative">
                                    <input
                                        id="password"
                                        name="password"
                                        type="password"
                                        wire:model="password"
                                        x-bind:type="showPassword ? 'text' : 'password'"
                                        autocomplete="new-password"
                                        required
                                        aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                                        @if ($errors->has('password')) aria-describedby="password-hint password-error" @else aria-describedby="password-hint" @endif
                                        class="min-h-touch w-full rounded-xl border {{ $errors->has('password') ? 'border-terracotta' : 'border-border' }} bg-surface px-4 pr-14 text-body text-text-primary shadow-xs transition duration-180 placeholder:text-text-secondary hover:border-forest-600 focus:border-focus focus:outline-none focus:ring-2 focus:ring-focus/30 disabled:cursor-not-allowed disabled:border-border disabled:bg-disabled-bg disabled:text-text-secondary"
                                    >
                                    <button type="button" class="absolute inset-y-0 right-0 inline-flex min-h-touch min-w-touch items-center justify-center rounded-r-xl text-text-secondary transition duration-180 hover:bg-emerald-50 hover:text-deep-green focus-visible:z-10 focus-visible:ring-2 focus-visible:ring-focus" x-on:click="showPassword = !showPassword" x-bind:aria-label="showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'" x-bind:aria-pressed="showPassword.toString()">
                                        <x-public.icon name="eye" size="size-5" x-show="!showPassword" />
                                        <x-public.icon name="eye-off" size="size-5" x-show="showPassword" />
                                    </button>
                                </div>
                                <p id="password-hint" class="text-caption text-text-secondary">Minimal 10 karakter.</p>
                                @error('password')
                                    <p id="password-error" class="flex items-start gap-2 break-words text-body-sm font-semibold text-terracotta"><x-public.icon name="circle-alert" size="size-4" class="mt-0.5 shrink-0" />{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="space-y-2" x-data="{ showPassword: false }">
                                <label for="password_confirmation" class="block text-label font-semibold text-text-primary">Konfirmasi kata sandi</label>
                                <div class="relative">
                                    <input
                                        id="password_confirmation"
                                        name="password_confirmation"
                                        type="password"
                                        wire:model="password_confirmation"
                                        x-bind:type="showPassword ? 'text' : 'password'"
                                        autocomplete="new-password"
                                        required
                                        aria-invalid="{{ $errors->has('password_confirmation') ? 'true' : 'false' }}"
                                        @if ($errors->has('password_confirmation')) aria-describedby="password_confirmation-error" @endif
                                        class="min-h-touch w-full rounded-xl border {{ $errors->has('password_confirmation') ? 'border-terracotta' : 'border-border' }} bg-surface px-4 pr-14 text-body text-text-primary shadow-xs transition duration-180 placeholder:text-text-secondary hover:border-forest-600 focus:border-focus focus:outline-none focus:ring-2 focus:ring-focus/30 disabled:cursor-not-allowed disabled:border-border disabled:bg-disabled-bg disabled:text-text-secondary"
                                    >
                                    <button type="button" class="absolute inset-y-0 right-0 inline-flex min-h-touch min-w-touch items-center justify-center rounded-r-xl text-text-secondary transition duration-180 hover:bg-warm-canvas hover:text-deep-green focus-visible:z-10 focus-visible:ring-2 focus-visible:ring-focus" x-on:click="showPassword = !showPassword" x-bind:aria-label="showPassword ? 'Sembunyikan konfirmasi kata sandi' : 'Tampilkan konfirmasi kata sandi'" x-bind:aria-pressed="showPassword.toString()">
                                        <x-public.icon name="eye" size="size-5" x-show="!showPassword" />
                                        <x-public.icon name="eye-off" size="size-5" x-show="showPassword" />
                                    </button>
                                </div>
                                @error('password_confirmation')
                                    <p id="password_confirmation-error" class="flex items-start gap-2 break-words text-body-sm font-semibold text-terracotta"><x-public.icon name="circle-alert" size="size-4" class="mt-0.5 shrink-0" />{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </section>

                    <section class="space-y-4" aria-labelledby="registration-residence-title">
                        <div class="flex items-center gap-2 border-b border-border pb-2">
                            <x-public.icon name="map-pin" size="size-4" class="text-forest-600" />
                            <h2 id="registration-residence-title" class="text-title font-bold text-deep-green">Domisili Warga</h2>
                        </div>
                        <div class="space-y-5">
                            <div class="space-y-2">
                                <label for="rt_id" class="block text-label font-semibold text-text-primary">RT domisili</label>
                                <select id="rt_id" name="rt_id" wire:model.blur="rt_id" required aria-invalid="{{ $errors->has('rt_id') ? 'true' : 'false' }}" @if ($errors->has('rt_id')) aria-describedby="rt_id-error" @endif class="min-h-touch w-full rounded-xl border {{ $errors->has('rt_id') ? 'border-terracotta' : 'border-border' }} bg-surface px-4 text-body text-text-primary shadow-xs transition duration-180 hover:border-forest-600 focus:border-focus focus:outline-none focus:ring-2 focus:ring-focus/30">
                                    <option value="">Pilih RT domisili</option>
                                    @foreach ($rts as $rt)
                                        <option value="{{ $rt->id }}">{{ $rt->rw->dusun->name }} — {{ $rt->rw->name }} — {{ $rt->name }}</option>
                                    @endforeach
                                </select>
                                @error('rt_id')
                                    <p id="rt_id-error" class="flex items-start gap-2 break-words text-body-sm font-semibold text-terracotta"><x-public.icon name="circle-alert" size="size-4" class="mt-0.5 shrink-0" />{{ $message }}</p>
                                @enderror
                            </div>

                            <x-ui.textarea name="address" label="Alamat lengkap" wire:model.blur="address" autocomplete="street-address" required :error="$errors->first('address')" />
                        </div>
                    </section>

                    <section class="space-y-4" aria-labelledby="registration-consent-title">
                        <div class="flex items-center gap-2 border-b border-border pb-2">
                            <x-public.icon name="file-text" size="size-4" class="text-forest-600" />
                            <h2 id="registration-consent-title" class="text-title font-bold text-deep-green">Persetujuan Ketentuan</h2>
                        </div>
                        <div class="space-y-2">
                            <div class="flex items-start gap-3 rounded-xl border border-border bg-warm-canvas p-4">
                                <input id="terms_accepted" name="terms_accepted" type="checkbox" wire:model="terms_accepted" value="1" class="mt-1 size-5 shrink-0 rounded border-forest-600 text-forest-600 focus:ring-focus" aria-invalid="{{ $errors->has('terms_accepted') ? 'true' : 'false' }}" @if ($errors->has('terms_accepted')) aria-describedby="terms_accepted-error" @endif>
                                <label for="terms_accepted" class="text-body-sm text-text-primary leading-relaxed">Saya telah membaca dan menyetujui <a class="font-bold text-forest-600 underline underline-offset-4 hover:text-forest-700" href="{{ route('terms-and-privacy') }}" target="_blank" rel="noopener noreferrer">Ketentuan Operasional v1.0 dan Kebijakan Privasi Ringkas v1.0 <span class="sr-only">(terbuka di tab baru)</span></a>.</label>
                            </div>
                            @error('terms_accepted')
                                <p id="terms_accepted-error" class="flex items-start gap-2 break-words text-body-sm font-semibold text-terracotta"><x-public.icon name="circle-alert" size="size-4" class="mt-0.5 shrink-0" />Persetujuan terhadap Ketentuan Operasional v1.0 dan Kebijakan Privasi Ringkas v1.0 wajib diberikan.</p>
                            @enderror
                        </div>
                    </section>

                    <div class="space-y-3 border-t border-border pt-6">
                        <div class="flex min-h-touch items-center gap-3 rounded-xl bg-info-bg px-4 py-3 border border-sky-100" wire:loading wire:target="register" aria-live="polite">
                            <x-public.icon name="loader-circle" size="size-5" class="shrink-0 animate-spin text-sky-blue motion-reduce:animate-none" />
                            <p class="text-body-sm font-semibold text-text-secondary">Memproses pendaftaran. Mohon tunggu.</p>
                        </div>

                        <x-ui.button type="submit" class="w-full shadow-md bg-forest-600 text-surface font-bold py-3 rounded-xl transition duration-200 hover:bg-forest-700" wire:loading.attr="disabled" wire:target="register">
                            <span wire:loading.remove wire:target="register">Kirim Pendaftaran Warga</span>
                            <span wire:loading wire:target="register">Memproses...</span>
                        </x-ui.button>
                    </div>
                </form>
            @endif
        </div>
    </section>
</div>
