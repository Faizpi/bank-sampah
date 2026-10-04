@php
    $statusCode = trim((string) $__env->yieldContent('code'));
    $statusCode = $statusCode !== '' ? $statusCode : (string) ($exception?->getStatusCode() ?? 500);

    $copy = match ($statusCode) {
        '400' => [
            'title' => 'Permintaan tidak dapat diproses',
            'message' => 'Ada bagian dari permintaan yang tidak dapat kami pahami. Coba kembali dari halaman sebelumnya.',
        ],
        '401' => [
            'title' => 'Silakan masuk terlebih dahulu',
            'message' => 'Halaman ini memerlukan sesi masuk yang aktif. Silakan masuk kembali untuk melanjutkan.',
        ],
        '403' => [
            'title' => 'Akses tidak diizinkan',
            'message' => 'Akun Anda tidak memiliki izin untuk membuka halaman atau menjalankan tindakan ini.',
        ],
        '404' => [
            'title' => 'Halaman tidak ditemukan',
            'message' => 'Alamat yang Anda buka mungkin sudah berubah, dihapus, atau tidak pernah tersedia.',
        ],
        '405' => [
            'title' => 'Tindakan tidak dapat dilakukan',
            'message' => 'Cara membuka halaman ini tidak sesuai. Kembali ke beranda dan coba melalui menu yang tersedia.',
        ],
        '419' => [
            'title' => 'Sesi sudah berakhir',
            'message' => 'Demi keamanan, sesi Anda telah berakhir. Kembali ke beranda lalu masuk kembali bila diperlukan.',
        ],
        '429' => [
            'title' => 'Terlalu banyak permintaan',
            'message' => 'Tunggu sebentar sebelum mencoba lagi. Sistem sedang melindungi layanan agar tetap nyaman digunakan bersama.',
        ],
        '503' => [
            'title' => 'Layanan sedang dalam perawatan',
            'message' => 'Kami sedang menyiapkan layanan agar kembali optimal. Silakan coba lagi beberapa saat lagi.',
        ],
        default => str_starts_with($statusCode, '5')
            ? [
                'title' => 'Terjadi gangguan sementara',
                'message' => 'Kami sedang memeriksa kendala ini. Silakan kembali ke beranda atau coba lagi beberapa saat lagi.',
            ]
            : [
                'title' => 'Permintaan tidak dapat diproses',
                'message' => 'Halaman ini belum dapat kami tampilkan. Kembali ke beranda untuk melanjutkan.',
            ],
    };
@endphp
<!doctype html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">
        <title>{{ $statusCode }} · {{ $copy['title'] }} · Bank Sampah Digital Sindangheula</title>
        <style>
            :root {
                color-scheme: light;
                --canvas: #f7f8f4;
                --surface: #ffffff;
                --ink: #102d22;
                --muted: #52665c;
                --line: #d9e3d8;
                --forest: #217345;
                --forest-dark: #155331;
                --soft-green: #eaf4e8;
                --warm: #fff8e7;
            }

            * { box-sizing: border-box; }
            html { min-height: 100%; background: var(--canvas); }
            body {
                min-height: 100vh;
                margin: 0;
                color: var(--ink);
                background: var(--canvas);
                font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                -webkit-font-smoothing: antialiased;
            }
            a { color: inherit; }
            .shell {
                width: min(100% - 2rem, 72rem);
                margin: 0 auto;
                padding: clamp(1rem, 3vw, 2rem) 0;
            }
            .brand {
                display: inline-flex;
                align-items: center;
                gap: .75rem;
                text-decoration: none;
            }
            .brand:focus-visible,
            .button:focus-visible,
            .back-link:focus-visible {
                outline: 3px solid #92c7a4;
                outline-offset: 3px;
            }
            .brand img { width: 2.75rem; height: 2.75rem; object-fit: contain; }
            .brand strong { display: block; font-size: .95rem; line-height: 1.2; }
            .brand span { display: block; margin-top: .15rem; color: var(--muted); font-size: .8rem; }
            main {
                display: grid;
                align-items: center;
                min-height: calc(100vh - 7.5rem);
                padding: clamp(2.5rem, 7vw, 5.5rem) 0;
            }
            .recovery {
                display: grid;
                grid-template-columns: minmax(0, 1.05fr) minmax(15rem, .95fr);
                overflow: hidden;
                border: 1px solid var(--line);
                border-radius: 1rem;
                background: var(--surface);
                box-shadow: 0 1.25rem 3rem rgba(18, 63, 38, .10);
            }
            .content { padding: clamp(1.75rem, 5vw, 4.5rem); }
            .code {
                margin: 0;
                color: var(--forest);
                font-size: clamp(3rem, 9vw, 6rem);
                font-weight: 800;
                line-height: .9;
                letter-spacing: -.04em;
            }
            h1 {
                max-width: 17ch;
                margin: 1.35rem 0 .75rem;
                font-size: clamp(1.7rem, 3.5vw, 2.65rem);
                line-height: 1.08;
                letter-spacing: -.03em;
            }
            .message {
                max-width: 58ch;
                margin: 0;
                color: var(--muted);
                font-size: 1rem;
                line-height: 1.7;
                overflow-wrap: anywhere;
            }
            .actions { display: flex; flex-wrap: wrap; align-items: center; gap: .9rem 1.25rem; margin-top: 2rem; }
            .button {
                display: inline-flex;
                min-height: 2.9rem;
                align-items: center;
                justify-content: center;
                border-radius: .6rem;
                padding: .72rem 1rem;
                color: #fff;
                background: var(--forest);
                font-size: .95rem;
                font-weight: 700;
                text-decoration: none;
                transition: background-color .15s ease, transform .15s ease;
            }
            .button:hover { background: var(--forest-dark); transform: translateY(-1px); }
            .back-link { color: var(--forest-dark); font-size: .95rem; font-weight: 700; text-underline-offset: .22rem; }
            .visual {
                display: grid;
                min-height: 20rem;
                place-items: center;
                padding: clamp(2rem, 5vw, 4rem);
                background: var(--soft-green);
            }
            .mascot-wrap { width: min(100%, 20rem); text-align: center; }
            .mascot-wrap img {
                width: min(100%, 15rem);
                height: auto;
                filter: drop-shadow(0 .85rem 1rem rgba(24, 91, 50, .18));
            }
            .mascot-note {
                display: inline-block;
                max-width: 20rem;
                margin: 1rem 0 0;
                padding: .55rem .75rem;
                border: 1px solid #cde0cb;
                border-radius: .5rem;
                color: var(--forest-dark);
                background: var(--warm);
                font-size: .85rem;
                font-weight: 600;
                line-height: 1.45;
            }
            @media (max-width: 44rem) {
                .recovery { grid-template-columns: 1fr; }
                .visual { order: -1; min-height: 14rem; padding: 1.75rem; }
                .mascot-wrap img { width: 9rem; }
                .content { padding: 2rem 1.5rem 2.25rem; }
                main { min-height: auto; padding: 2rem 0 3rem; }
            }
            @media (prefers-reduced-motion: reduce) {
                *, *::before, *::after { scroll-behavior: auto !important; transition-duration: .01ms !important; animation-duration: .01ms !important; }
            }
        </style>
    </head>
    <body>
        <div class="shell">
            <a class="brand" href="{{ route('home') }}" aria-label="Kembali ke beranda Bank Sampah Digital Sindangheula">
                <img src="{{ asset('images/landing/mascot-3.png') }}" alt="">
                <span>
                    <strong>Bank Sampah Digital</strong>
                    <span>Desa Sindangheula</span>
                </span>
            </a>

            <main>
                <section class="recovery" aria-labelledby="error-title">
                    <div class="content">
                        <p class="code" aria-hidden="true">{{ $statusCode }}</p>
                        <h1 id="error-title">{{ $copy['title'] }}</h1>
                        <p class="message">{{ $copy['message'] }}</p>
                        <div class="actions">
                            <a class="button" href="{{ route('home') }}">Kembali ke beranda</a>
                            <a class="back-link" href="{{ route('home') }}" onclick="if (window.history.length > 1) { window.history.back(); return false; }">Kembali ke halaman sebelumnya</a>
                        </div>
                    </div>
                    <div class="visual">
                        <div class="mascot-wrap">
                            <img src="{{ asset('images/landing/mascot-3.png') }}" alt="Maskot Bank Sampah Digital Sindangheula siap membantu Anda kembali ke beranda">
                            <p class="mascot-note">Jangan khawatir, mari mulai lagi dari halaman utama.</p>
                        </div>
                    </div>
                </section>
            </main>
        </div>
    </body>
</html>
