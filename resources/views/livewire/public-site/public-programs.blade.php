@section('title', 'Target dan Statistik Program')
@section('description', 'Target pengumpulan dan statistik agregat publik Bank Sampah Digital Desa Sindangheula.')

<div class="public-canvas">
    <section class="border-b border-deep-green bg-deep-green text-surface" aria-labelledby="programs-page-title">
        <div class="public-container grid items-center gap-8 section-space lg:grid-cols-[1fr_auto]">
            <div>
            <p class="text-label font-semibold tracking-wide text-surface/80">Program desa</p>
            <h1 id="programs-page-title" class="mt-2 max-w-3xl text-h1 lg:text-h1-lg text-surface">Bergerak lewat angka agregat.</h1>
            <p class="mt-3 max-w-2xl text-body text-surface/80">Target dan progres ditampilkan tanpa nama, nomor nasabah, alamat, saldo, atau riwayat individu.</p>
            <div class="mt-5 flex flex-wrap gap-2">
                <a href="{{ route('public.announcements') }}" class="inline-flex min-h-touch items-center justify-center gap-2 rounded-md bg-surface px-5 text-label text-deep-green transition duration-180 ease-standard hover:bg-success-bg active:translate-y-px">
                    Lihat pengumuman program
                    <x-public.icon name="megaphone" size="size-5" />
                </a>
                <a href="{{ route('public.mobile-schedule') }}" class="inline-flex min-h-touch items-center justify-center gap-2 rounded-md border border-surface/40 px-5 text-label text-surface transition duration-180 ease-standard hover:border-surface hover:bg-surface/10 active:translate-y-px">
                    Lihat jadwal layanan
                </a>
            </div>
            </div>
            <img src="{{ asset('images/landing/mascot-8.png') }}" alt="Maskot badak menanam bibit sebagai simbol dampak program" class="mx-auto h-40 w-44 object-contain lg:h-48 lg:w-52">
        </div>
    </section>

    <section class="public-section-canvas">
        <div class="public-container section-space">
            <x-public.section-header
                id="programs-title"
                eyebrow="Program berjalan"
                title="Target aktif"
                description="Target yang tampil telah melewati ambang publikasi dan hanya memuat ukuran agregat program."
            />

            @if ($targets->isEmpty())
                <x-ui.empty-state
                    class="mt-8"
                    title="Belum ada target publik"
                    description="Target ditampilkan setelah memenuhi ambang privasi publik."
                    action-label="Lihat pengumuman program"
                    action-href="{{ route('public.announcements') }}"
                />
            @else
                <div class="mt-10 grid gap-4 sm:grid-cols-2">
                    @foreach ($targets as $target)
                        <article class="rounded-lg border border-border bg-surface p-5 md:p-6">
                            <div class="flex items-start gap-3">
                                <span class="grid size-10 shrink-0 place-items-center rounded-md bg-success-bg text-forest-700" aria-hidden="true">
                                    <x-public.icon name="target" size="size-5" />
                                </span>
                                <div class="min-w-0">
                                    <p class="text-body-sm font-semibold text-forest-600">{{ $target['period'] }}</p>
                                    <h3 class="mt-1 text-h3 text-deep-green">{{ $target['name'] }}</h3>
                                </div>
                            </div>
                            <p class="mt-4 text-body-sm leading-6 text-text-secondary">{{ $target['purpose'] }}</p>
                            <dl class="mt-6 grid gap-3 border-t border-border pt-4 text-body-sm">
                                <div class="flex justify-between gap-4">
                                    <dt class="text-text-secondary">Progres bersih</dt>
                                    <dd class="font-semibold tabular-nums text-deep-green">{{ \App\Support\WeightFormatter::format($target['progress_kg']) }} kg</dd>
                                </div>
                                <div class="flex justify-between gap-4">
                                    <dt class="text-text-secondary">Sasaran</dt>
                                    <dd class="font-semibold tabular-nums text-text-primary">{{ \App\Support\WeightFormatter::format($target['target_weight_kg']) }} kg</dd>
                                </div>
                            </dl>
                        </article>
                    @endforeach
                </div>
            @endif

            <section class="mt-12 border-t border-border pt-8" aria-labelledby="public-statistics-title">
                <x-public.section-header
                    id="public-statistics-title"
                    eyebrow="Statistik agregat"
                    title="Ringkasan desa"
                    description="Ringkasan ini hanya menampilkan metrik yang diizinkan untuk publikasi dan telah memenuhi ambang privasi."
                />

                @if ($statistics['suppressed'])
                    <div class="mt-6 flex gap-3 rounded-lg border border-border bg-surface p-5" role="status">
                        <x-public.icon name="circle-alert" size="size-6" class="mt-0.5 text-harvest-gold" />
                        <div>
                            <h3 class="text-title text-deep-green">Ringkasan belum ditampilkan</h3>
                            <p class="mt-1 text-body-sm text-text-secondary">Ringkasan belum ditampilkan karena belum memenuhi ambang privasi publik.</p>
                        </div>
                    </div>
                @else
                    <div class="mt-6 grid gap-4 sm:grid-cols-3">
                        @foreach ($statistics['metrics'] as $metric => $value)
                            <article class="rounded-lg border border-border bg-surface p-5">
                                <div class="flex items-start justify-between gap-3">
                                    <p class="text-body-sm text-text-secondary">{{ str_replace('_', ' ', ucfirst($metric)) }}</p>
                                    <x-public.icon name="bar-chart-3" size="size-5" class="text-forest-600" />
                                </div>
                                <p class="mt-3 text-amount tabular-nums text-deep-green">{{ is_scalar($value) ? $value : '—' }}</p>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </section>
</div>
