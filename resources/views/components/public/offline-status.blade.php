@props([
    'id' => 'public-offline-status',
    'label' => 'Status koneksi',
])

<div
    id="{{ $id }}"
    data-public-offline-status
    class="public-live-region"
    role="status"
    aria-live="polite"
    aria-atomic="true"
    aria-label="{{ $label }}"
></div>
