@props([
    'persona',
    'destinations',
    'active',
])

@php
    if (! in_array($persona, ['officer', 'treasurer'], true)) {
        throw new InvalidArgumentException('Officer navigation persona must be officer or treasurer.');
    }

    if (count($destinations) < 3 || count($destinations) > 5) {
        throw new InvalidArgumentException('Officer navigation must contain between three and five destinations.');
    }

    $labels = array_keys($destinations);
    $requiredLabels = ['Tugas', 'Akun'];
    if (array_diff($requiredLabels, $labels) !== []) {
        throw new InvalidArgumentException('Officer navigation must contain Tugas and Akun.');
    }

    $canonicalLabels = $persona === 'officer'
        ? ['Tugas', 'Setoran', 'Layanan', 'Akun']
        : ['Tugas', 'Pembayaran', 'Rekonsiliasi', 'Akun'];

    if (array_diff($labels, $canonicalLabels) !== []) {
        throw new InvalidArgumentException("Officer navigation destinations are invalid for persona {$persona}.");
    }

    $canonicalSubset = array_values(array_intersect($canonicalLabels, $labels));
    if ($labels !== $canonicalSubset) {
        throw new InvalidArgumentException('Officer navigation destinations must follow canonical order.');
    }

    if (! in_array($active, $labels, true)) {
        throw new InvalidArgumentException('Officer navigation active item must be one of the supplied destinations.');
    }

    // Normalize full URLs to internal paths (e.g. route() output → /path?query)
    $destinations = array_map(static function (mixed $dest): mixed {
        if (is_string($dest) && str_contains($dest, '://')) {
            $path  = parse_url($dest, PHP_URL_PATH) ?? '';
            $query = parse_url($dest, PHP_URL_QUERY);
            return $path . ($query !== null ? '?' . $query : '');
        }
        return $dest;
    }, $destinations);

    foreach ($destinations as $label => $destination) {
        if (! is_string($destination)) {
            throw new InvalidArgumentException("Officer navigation destination for {$label} must be a string.");
        }

        if ($destination === '' || trim($destination) !== $destination || preg_match('/%(?![0-9A-Fa-f]{2})/', $destination) === 1) {
            throw new InvalidArgumentException("Officer navigation destination for {$label} must be a safe internal path, query, or fragment.");
        }

        $decodedDestination = rawurldecode($destination);
        $hasUnsafeCharacters = preg_match('/[\\x00-\\x1F\\x7F\\\\]/', $decodedDestination) === 1;
        $isAllowedInternalDestination = preg_match('/^(?:\/(?!\/).*|\?[^?#].*|#[^#].*)$/su', $decodedDestination) === 1;

        $secondInspection = rawurldecode($decodedDestination);
        $hasNestedEncoding = $secondInspection !== $decodedDestination;
        $concealsUnsafeCharacters = $hasNestedEncoding && preg_match('/[\\x00-\\x1F\\x7F\\\\]/', $secondInspection) === 1;
        $concealsNetworkSeparators = $hasNestedEncoding && str_contains($secondInspection, '//');
        $concealsScheme = $hasNestedEncoding && preg_match('/(?:^|\/)(?:javascript|data|vbscript):/iu', $secondInspection) === 1;

        if ($hasUnsafeCharacters || ! $isAllowedInternalDestination || $concealsUnsafeCharacters || $concealsNetworkSeparators || $concealsScheme) {
            throw new InvalidArgumentException("Officer navigation destination for {$label} must be a safe internal path, query, or fragment.");
        }
    }

    $icons = [
        'Tugas' => 'clipboard-list',
        'Setoran' => 'wallet-cards',
        'Layanan' => 'grid-2x2',
        'Pembayaran' => 'wallet-cards',
        'Rekonsiliasi' => 'grid-2x2',
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

<x-ui.bottom-navigation :items="$items" :label="$persona === 'officer' ? 'Navigasi petugas' : 'Navigasi bendahara'" {{ $attributes }} />
