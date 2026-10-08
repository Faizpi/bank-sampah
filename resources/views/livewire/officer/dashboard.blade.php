<x-slot:title>Tugas Petugas</x-slot:title>
<x-slot:date>{{ now()->translatedFormat('d F Y') }}</x-slot:date>
<x-slot:connectivity><x-ui.connectivity-status /></x-slot:connectivity>
<section class="grid gap-5" aria-labelledby="officer-tasks-title">
    @if ($errors->any())<div role="alert" class="rounded-xl border border-terracotta bg-danger-bg p-4">@foreach ($errors->all() as $message)<p>{{ $message }}</p>@endforeach</div>@endif
    <div>
        <h1 id="officer-tasks-title" class="text-h1 font-bold text-deep-green">Tugas yang perlu dikerjakan</h1>
        <p class="mt-2">Ambil penjemputan atau sembako sesuai area. Setoran dan layanan keliling tetap menjadi tugas Petugas.</p>
    </div>
    @if ($priorityTask)
        <div class="rounded-xl border border-border bg-info-bg p-4">
            <h2 class="font-bold">{{ $priorityTask['label'] }}</h2><p>{{ $priorityTask['description'] }}</p>
            @if ($priorityTask['href'])<a class="inline-flex min-h-touch items-center font-semibold underline" href="{{ $priorityTask['href'] }}">Buka tugas prioritas</a>@endif
        </div>
    @endif
    <div class="flex flex-wrap gap-3">
        <a href="{{ $identificationHref }}" class="inline-flex min-h-touch items-center rounded-md bg-forest-600 px-4 font-semibold text-white">Mulai setoran</a>
        <a href="{{ route('officer.mobile-services') }}" class="inline-flex min-h-touch items-center rounded-md border border-border px-4 font-semibold">Layanan keliling</a>
    </div>
    <div class="grid gap-3 sm:grid-cols-2">
        <x-ui.select name="taskTab" label="Tampilkan" wire:model.live="taskTab" :options="['waiting' => 'Menunggu diambil', 'mine' => 'Tugas saya']" />
        <x-ui.select name="taskType" label="Jenis tugas" wire:model.live="taskType" :options="['pickup' => 'Penjemputan', 'grocery' => 'Sembako', 'deposit' => 'Draf setoran saya', 'mobile' => 'Layanan keliling saya']" />
    </div>
    <div class="divide-y divide-border rounded-xl border border-border bg-surface px-4">
        @forelse ($tasks as $task)
            <article class="space-y-2 py-4" wire:key="officer-task-{{ $taskType }}-{{ $task->id }}">
                @if ($taskType === 'mobile')
                    <h2 class="font-bold">{{ $task->point }}</h2>
                    <p>{{ $task->starts_at->translatedFormat('d M Y H:i') }} · {{ \App\Support\StatusLabel::for($task->status) }}</p>
                    <a class="inline-flex min-h-touch items-center font-semibold underline" href="{{ route('officer.mobile-services') }}">Buka layanan</a>
                @else
                    <h2 class="font-bold">{{ $task->customer?->name }}</h2>
                    <p>{{ $taskType === 'deposit' ? $task->deposit_number : $task->request_number }} · {{ \App\Support\StatusLabel::for($task->status) }}</p>
                    @if ($taskType === 'pickup')
                        <p>{{ $task->address }} · {{ $task->selected_date->translatedFormat('d M Y') }}</p>
                        @if ($task->status->value === 'menunggu_petugas')
                            <x-ui.button type="button" wire:click="claimPickup({{ $task->id }})" wire:loading.attr="disabled">Ambil tugas</x-ui.button>
                        @else<a class="inline-flex min-h-touch items-center font-semibold underline" href="{{ route('officer.pickup.task', $task) }}">Lanjutkan tugas</a>@endif
                    @elseif ($taskType === 'grocery')
                        <p>{{ data_get($task->package_snapshot, 'name') }} · Rp {{ number_format($task->value_snapshot, 0, ',', '.') }}</p>
                        <a class="inline-flex min-h-touch items-center font-semibold underline" href="{{ route('officer.grocery.tasks', ['taskId' => $task->id]) }}">Buka tugas sembako</a>
                    @else<a class="inline-flex min-h-touch items-center font-semibold underline" href="{{ route('officer.deposit-form', ['customerId' => $task->customer_id, 'draftId' => $task->id]) }}">Lanjutkan draf</a>@endif
                    @if ($task->getAttribute('exception_reported_at') && ! $task->getAttribute('exception_resolved_at'))<p class="font-semibold text-terracotta">Tugas dikunci. Jangan ulangi serah fisik; tunggu hasil pemeriksaan.</p>@endif
                @endif
            </article>
        @empty<div class="py-6"><h2 class="font-bold">Tidak ada tugas pada pilihan ini</h2><p>Pilih jenis lain, buka Tugas saya, atau mulai setoran saat warga datang.</p></div>@endforelse
    </div>
    @if ($tasks->hasPages()){{ $tasks->links('components.ui.pagination') }}@endif
</section>
