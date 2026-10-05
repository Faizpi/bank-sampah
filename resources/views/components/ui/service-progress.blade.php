@props(['status', 'nextStep' => null])
@php
    $value = $status instanceof \BackedEnum ? $status->value : $status;
    $summary = match ($value) {
        'selesai', 'sudah_dibayar', 'final' => 'Selesai',
        'siap_diambil' => 'Perlu tindakan Anda',
        'ditolak', 'dibatalkan', 'kedaluwarsa' => 'Perlu perhatian',
        default => 'Sedang diproses',
    };
@endphp
<div role="status" class="rounded-xl border border-border bg-success-bg p-4">
    <p class="text-title font-bold text-deep-green">{{ $summary }}</p>
    <p class="mt-1 text-body-sm text-text-secondary">{{ $nextStep ?? \App\Support\StatusLabel::for($value) }}</p>
</div>
