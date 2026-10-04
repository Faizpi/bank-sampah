@props([
    'destinations',
    'active',
])

@php
    $labels = ['Beranda', 'Setoran', 'Layanan', 'Kartu Nasabah', 'Akun'];

    if (array_keys($destinations) !== $labels) {
        throw new InvalidArgumentException('Citizen navigation destinations must contain exactly: Beranda, Setoran, Layanan, Kartu Nasabah, Akun.');
    }

    if (! in_array($active, $labels, true)) {
        throw new InvalidArgumentException('Citizen navigation active item must be one of: Beranda, Setoran, Layanan, Kartu Nasabah, Akun.');
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
        'Setoran' => 'wallet-cards',
        'Layanan' => 'grid-2x2',
        'Kartu Nasabah' => 'user-round',
        'Akun' => 'user-round',
    ];

    $items = array_map(
        static fn (string $label): array => [
            'label' => $label,
            'href' => $destinations[$label],
            'icon' => $icons[$label],
            'active' => $active === $label,
        ],
        $labels,
    );
@endphp

<x-ui.bottom-navigation :items="$items" label="Navigasi warga" {{ $attributes }} />
