@props([
    'destinations',
    'active',
])

@php
    $labels = ['Beranda', 'Kartu Nasabah', 'Layanan', 'Riwayat', 'Akun'];

    if (array_keys($destinations) !== $labels) {
        throw new InvalidArgumentException('Citizen navigation destinations must contain exactly: Beranda, Kartu Nasabah, Layanan, Riwayat, Akun.');
    }

    if (! in_array($active, $labels, true)) {
        throw new InvalidArgumentException('Citizen navigation active item must be one of: Beranda, Kartu Nasabah, Layanan, Riwayat, Akun.');
    }

    foreach ($destinations as $label => $destination) {
        if (! is_string($destination)) {
            throw new InvalidArgumentException("Citizen navigation destination for {$label} must be a string.");
        }

        if ($destination === '' || trim($destination) !== $destination || preg_match('/%(?![0-9A-Fa-f]{2})/', $destination) === 1) {
            throw new InvalidArgumentException("Citizen navigation destination for {$label} must be a safe internal path, query, or fragment.");
        }

        $decodedDestination = rawurldecode($destination);
        $hasUnsafeCharacters = preg_match('/[\\x00-\\x1F\\x7F\\\\]/', $decodedDestination) === 1;
        $isAllowedInternalDestination = preg_match('/^(?:\/(?!\/).*|\?[^?#].*|#[^#].*)$/su', $decodedDestination) === 1;

        if ($hasUnsafeCharacters || ! $isAllowedInternalDestination) {
            throw new InvalidArgumentException("Citizen navigation destination for {$label} must be a safe internal path, query, or fragment.");
        }
    }

    $icons = [
        'Beranda' => 'home',
        'Kartu Nasabah' => 'scan-line',
        'Layanan' => 'grid-2x2',
        'Riwayat' => 'history',
        'Akun' => 'user-round',
    ];

    $items = array_map(
        static fn (string $label): array => [
            'label' => $label === 'Kartu Nasabah' ? 'Kartu' : $label,
            'href' => $destinations[$label],
            'icon' => $icons[$label],
            'active' => $active === $label,
        ],
        $labels,
    );
@endphp

<x-ui.bottom-navigation :items="$items" label="Navigasi warga" {{ $attributes }} />
