@section('title', 'Pengumuman')
@section('description', 'Pengumuman resmi Bank Sampah Digital Desa Sindangheula.')

<div class="public-canvas">
    <section class="border-b border-deep-green bg-deep-green text-surface" aria-labelledby="announcements-page-title">
        <div class="public-container grid items-center gap-8 section-space lg:grid-cols-[1fr_auto]">
            <div>
            <p class="text-label font-semibold tracking-wide text-surface/80">Informasi resmi</p>
            <h1 id="announcements-page-title" class="mt-2 max-w-3xl text-h1 lg:text-h1-lg text-surface">Pengumuman untuk warga.</h1>
            <p class="mt-3 max-w-2xl text-body text-surface/80">Perubahan layanan, kegiatan, dan informasi penting yang sedang berlaku.</p>
            <div class="mt-5 flex flex-wrap gap-2">
                <a href="{{ route('public.mobile-schedule') }}" class="inline-flex min-h-touch items-center justify-center gap-2 rounded-md bg-surface px-5 text-label text-deep-green transition duration-180 ease-standard hover:bg-success-bg active:translate-y-px">
                    Lihat jadwal layanan
                    <x-public.icon name="calendar-days" size="size-5" />
                </a>
                <a href="{{ route('register') }}" class="inline-flex min-h-touch items-center justify-center gap-2 rounded-md border border-surface/40 px-5 text-label text-surface transition duration-180 ease-standard hover:border-surface hover:bg-surface/10 active:translate-y-px">
                    Daftar untuk menggunakan layanan
                </a>
            </div>
            </div>
            <img src="{{ asset('images/landing/mascot-11.png') }}" alt="Maskot badak beristirahat di dekat material daur ulang" class="mx-auto h-40 w-44 object-contain lg:h-48 lg:w-52">
        </div>
    </section>

    <section class="public-section-canvas">
        <div class="public-container section-space">
            <x-public.section-header
                id="announcements-title"
                eyebrow="Terbaru"
                title="Pengumuman aktif"
                description="Baca informasi resmi sebelum menggunakan layanan atau datang ke titik layanan."
            />

            @if ($announcements->isEmpty())
                <x-ui.empty-state
                    class="mt-8"
                    title="Belum ada pengumuman"
                    description="Informasi baru akan muncul di halaman ini setelah diterbitkan."
                    action-label="Lihat jadwal layanan"
                    action-href="{{ route('public.mobile-schedule') }}"
                />
            @else
                <div class="mx-auto mt-10 max-w-3xl divide-y divide-border border-y border-border bg-surface">
                    @foreach ($announcements as $announcement)
                        <article class="p-5 sm:p-7">
                            <div class="flex items-start gap-3">
                                <span class="grid size-10 shrink-0 place-items-center rounded-md bg-info-bg text-sky-blue" aria-hidden="true">
                                    <x-public.icon name="megaphone" size="size-5" />
                                </span>
                                <div class="min-w-0">
                                    <p class="text-body-sm font-semibold text-forest-600">{{ $announcement->publish_start->format('d M Y, H:i') }}</p>
                                    <h2 class="mt-1 text-h3 text-deep-green">{{ $announcement->title }}</h2>
                                </div>
                            </div>
                            <div class="prose prose-sm mt-4 max-w-none text-text-secondary">{!! $announcement->body !!}</div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
</div>
