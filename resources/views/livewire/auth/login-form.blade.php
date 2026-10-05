<div class="container-citizen py-8 sm:py-12">
    <section class="overflow-hidden rounded-2xl border border-border bg-surface shadow-md" aria-labelledby="login-title">
        <div class="relative isolate overflow-hidden border-b border-border bg-warm-canvas p-6 sm:p-8 text-deep-green">
            <div class="flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
                <div class="w-full min-w-0 max-w-lg">
                    <span class="inline-flex rounded-full border border-forest-600 bg-success-bg px-3 py-1 text-xs font-semibold text-forest-700">Akses Akun Layanan</span>
                    <h1 id="login-title" class="mt-2 text-pretty text-h2 font-extrabold text-deep-green lg:text-h1">Masuk ke Layanan Bank Sampah</h1>
                    <p class="mt-2 text-body-sm text-text-secondary">Gunakan nomor telepon terdaftar dan kata sandi untuk membuka layanan sesuai peran Anda.</p>
                </div>
                <div class="flex items-center justify-between gap-4 md:flex-col md:items-end">
                    <x-ui.mascot variant="2" bubble="Halo! Yuk masuk" bubblePosition="top" class="h-28 w-auto filter drop-shadow-lg" />
                </div>
            </div>
            
            <nav class="mt-6 flex flex-wrap items-center gap-4 border-t border-border pt-4 text-xs font-medium text-text-secondary" aria-label="Navigasi akses">
                <a href="{{ route('home') }}" class="inline-flex items-center gap-1 hover:text-deep-green transition-colors">
                    <x-public.icon name="arrow-right" size="size-3.5" class="rotate-180" />
                    Kembali ke beranda
                </a>
                <span class="text-text-secondary/40">•</span>
                <a href="{{ route('register') }}" class="inline-flex items-center gap-1 font-bold text-forest-600 hover:text-forest-700 transition-colors">
                    Daftar sebagai warga baru
                    <x-public.icon name="arrow-right" size="size-3.5" />
                </a>
            </nav>
        </div>

        <div class="bg-surface px-5 py-6 sm:p-8 md:p-8">
            @if ($errors->any())
                @php
                    $errorFieldLabels = [
                        'phone' => 'Nomor telepon',
                        'password' => 'Kata sandi',
                    ];
                @endphp
                <div id="login-errors" class="mx-auto max-w-form rounded-xl border border-terracotta/40 bg-danger-bg p-4 shadow-xs" role="alert" tabindex="-1" x-on:login-invalid.window="$nextTick(() => $el.focus())">
                    <div class="flex items-start gap-3">
                        <x-public.icon name="circle-alert" size="size-5" class="mt-0.5 shrink-0 text-terracotta" />
                        <div class="min-w-0">
                            <h2 class="text-title text-deep-green font-bold">Tidak dapat masuk</h2>
                            <p class="mt-1 break-words text-body-sm text-text-secondary">Kredensial tidak valid atau akun tidak dapat digunakan.</p>
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

            <form class="mx-auto mt-4 max-w-form space-y-5" wire:submit="login" wire:loading.attr="aria-busy" wire:target="login" @if ($errors->any()) aria-describedby="login-errors" @endif>
                <x-ui.input name="phone" label="Nomor telepon" type="tel" wire:model.blur="phone" autocomplete="username" inputmode="tel" required :error="$errors->first('phone')" />

                <div class="space-y-2" x-data="{ showPassword: false }">
                    <label for="password" class="block text-label font-semibold text-text-primary">Kata sandi</label>
                    <div class="relative">
                        <input
                            id="password"
                            name="password"
                            type="password"
                            wire:model="password"
                            x-bind:type="showPassword ? 'text' : 'password'"
                            autocomplete="current-password"
                            required
                            aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                            @if ($errors->has('password')) aria-describedby="password-error" @endif
                            class="min-h-touch w-full rounded-xl border {{ $errors->has('password') ? 'border-terracotta' : 'border-border' }} bg-surface px-4 pr-14 text-body text-text-primary shadow-xs transition duration-180 placeholder:text-text-secondary hover:border-forest-600 focus:border-focus focus:outline-none focus:ring-2 focus:ring-focus/30 disabled:cursor-not-allowed disabled:border-border disabled:bg-disabled-bg disabled:text-text-secondary"
                        >
                        <button type="button" class="absolute inset-y-0 right-0 inline-flex min-h-touch min-w-touch items-center justify-center rounded-r-xl text-text-secondary transition duration-180 hover:bg-warm-canvas hover:text-deep-green focus-visible:z-10 focus-visible:ring-2 focus-visible:ring-focus" x-on:click="showPassword = !showPassword" x-bind:aria-label="showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'" x-bind:aria-pressed="showPassword.toString()">
                            <x-public.icon name="eye" size="size-5" x-show="!showPassword" />
                            <x-public.icon name="eye-off" size="size-5" x-show="showPassword" />
                        </button>
                    </div>
                    @error('password')
                        <p id="password-error" class="flex items-start gap-2 break-words text-body-sm font-semibold text-terracotta"><x-public.icon name="circle-alert" size="size-4" class="mt-0.5 shrink-0" />{{ $message }}</p>
                    @enderror
                </div>

                <div class="space-y-3 pt-2">
                    <div class="flex min-h-touch items-center gap-3 rounded-xl bg-info-bg px-4 py-3 border border-sky-100" wire:loading wire:target="login" aria-live="polite">
                        <x-public.icon name="loader-circle" size="size-5" class="shrink-0 animate-spin text-sky-blue motion-reduce:animate-none" />
                        <p class="text-body-sm font-semibold text-text-secondary">Memproses masuk. Mohon tunggu.</p>
                    </div>

                    <x-ui.button type="submit" class="w-full" wire:loading.attr="disabled" wire:target="login">
                        <span wire:loading.remove wire:target="login">Masuk ke Akun</span>
                        <span wire:loading wire:target="login">Memproses...</span>
                    </x-ui.button>
                </div>
            </form>

            @if ($demoAccounts)
                <div class="mx-auto mt-8 max-w-form border-t border-border pt-6">
                    <h2 class="text-label font-bold text-deep-green">Isi Cepat Akun Demo</h2>
                    <p class="mt-1 text-body-sm text-text-secondary">Klik salah satu peran di bawah untuk mencoba sistem secara instan.</p>

                    <ul class="mt-4 grid gap-3 sm:grid-cols-2" aria-label="Akun demo untuk pengisian cepat">
                        @foreach ($demoAccounts as $role => $account)
                            <li>
                                <button
                                    type="button"
                                    wire:click="fillDemo('{{ $role }}')"
                                    class="group flex w-full min-h-touch items-center justify-between gap-3 rounded-xl border border-border bg-warm-canvas px-4 py-3 text-left shadow-xs transition duration-200 hover:border-forest-600 hover:bg-success-bg hover:shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                                >
                                    <span class="min-w-0">
                                        <span class="block text-label font-bold text-deep-green">{{ $account['label'] }}</span>
                                        <span class="block truncate text-body-sm text-text-secondary">{{ $account['phone'] }}</span>
                                    </span>
                                    <x-public.icon name="arrow-right" size="size-4" class="shrink-0 text-forest-600 transition duration-200 group-hover:translate-x-1" />
                                </button>
                            </li>
                        @endforeach
                    </ul>

                    <p class="mt-5 text-caption text-text-secondary">
                        Masuk menggunakan alamat email + kata sandi di
                        <a href="{{ route('filament.backoffice.auth.login') }}" class="inline-flex items-center rounded-md font-bold text-forest-600 underline underline-offset-4 hover:text-forest-700">panel admin</a>
                        (admin &amp; superadmin).
                    </p>
                </div>
            @endif
        </div>
    </section>
</div>
