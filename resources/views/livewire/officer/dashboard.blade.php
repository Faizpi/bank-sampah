<x-slot:title>Tugas Petugas</x-slot:title>
<x-slot:date>{{ now()->translatedFormat('d F Y') }}</x-slot:date>
<x-slot:connectivity><x-ui.connectivity-status /></x-slot:connectivity>
<section class="grid gap-6" aria-labelledby="officer-tasks-title">
    @if ($errors->any())<div role="alert" class="rounded-xl border border-terracotta bg-danger-bg p-4">@foreach ($errors->all() as $message)<p>{{ $message }}</p>@endforeach</div>@endif
    <x-ui.page-heading id="officer-tasks-title" title="Tugas Petugas" description="Mulai setoran atau lanjutkan tugas Anda." mascot="8" />
    <div class="grid grid-cols-2 gap-3">
        <x-ui.action-card :href="$identificationHref" icon="scale" title="Mulai setoran" compact />
        <x-ui.action-card :href="route('officer.mobile-services')" icon="truck" title="Layanan keliling" compact />
    </div>
    @if ($priorityTask)
        <div class="rounded-xl border border-border bg-info-bg p-4">
            <h2 class="font-bold">{{ $priorityTask['label'] }}</h2><p>{{ $priorityTask['description'] }}</p>
            @if ($priorityTask['href'])<a class="inline-flex min-h-touch items-center font-semibold underline" href="{{ $priorityTask['href'] }}">Buka tugas prioritas</a>@endif
        </div>
    @endif
    <div class="grid gap-4 rounded-lg border border-border bg-surface p-4 sm:grid-cols-2 sm:p-5">
        <x-ui.select name="taskTab" label="Tampilkan" wire:model.live="taskTab" :options="['waiting' => 'Menunggu diambil', 'mine' => 'Tugas saya']" />
        <x-ui.select name="taskType" label="Jenis tugas" wire:model.live="taskType" :options="['pickup' => 'Penjemputan', 'grocery' => 'Sembako', 'deposit' => 'Draf setoran saya', 'mobile' => 'Layanan keliling saya']" />
    </div>
    <div class="divide-y divide-border rounded-xl border border-border bg-surface px-4">
        @forelse ($tasks as $task)
            <article class="space-y-3 py-5" wire:key="officer-task-{{ $taskType }}-{{ $task->id }}">
                @if ($taskType === 'mobile')
                    <h2 class="text-title font-bold text-deep-green">{{ $task->point }}</h2>
                    <p>{{ $task->starts_at->translatedFormat('d M Y H:i') }} · {{ \App\Support\StatusLabel::for($task->status) }}</p>
                    <a class="inline-flex min-h-touch items-center font-semibold underline" href="{{ route('officer.mobile-services') }}">Buka layanan</a>
                @else
                    <h2 class="text-title font-bold text-deep-green">{{ $task->customer?->name }}</h2>
                    <p>{{ $taskType === 'deposit' ? $task->deposit_number : $task->request_number }} · {{ \App\Support\StatusLabel::for($task->status) }}</p>
                    @if ($taskType === 'pickup')
                        <p>{{ $task->address }} · {{ $task->selected_date->translatedFormat('d M Y') }}</p>
                        @if ($task->status->value === 'menunggu_petugas')
                            <x-ui.button type="button" wire:click="claimPickup({{ $task->id }})" wire:loading.attr="disabled">Ambil tugas</x-ui.button>
                        @else<a class="inline-flex min-h-touch items-center font-semibold underline" href="{{ route('officer.pickup.task', $task) }}">Lanjutkan tugas</a>@endif
                    @elseif ($taskType === 'grocery')
                        <p>{{ data_get($task->package_snapshot, 'name') }} · Rp {{ number_format($task->value_snapshot, 0, ',', '.') }}</p>
                        <a class="inline-flex min-h-touch items-center font-semibold underline" href="{{ route('officer.grocery.tasks', ['taskNumber' => $task->request_number]) }}">Buka tugas sembako</a>
                    @else
                        @if ($task->customer?->customerProfile?->customer_number)
                            <a class="inline-flex min-h-touch items-center font-semibold underline" href="{{ route('officer.deposit-form', ['customerId' => $task->customer->customerProfile->customer_number, 'draftNumber' => $task->deposit_number]) }}">Lanjutkan draf</a>
                        @else
                            <p class="text-body-sm text-text-secondary">Nomor nasabah belum tersedia untuk melanjutkan draf.</p>
                        @endif
                    @endif
                    @if ($task->getAttribute('exception_reported_at') && ! $task->getAttribute('exception_resolved_at'))<p class="font-semibold text-terracotta">Tugas dikunci. Jangan ulangi serah fisik; tunggu hasil pemeriksaan.</p>@endif
                @endif
            </article>
        @empty<x-ui.empty-state title="Tidak ada tugas pada pilihan ini" description="Pilih jenis tugas lain atau mulai setoran saat warga datang." />@endforelse
    </div>
    @if ($tasks->hasPages()){{ $tasks->links('components.ui.pagination') }}@endif
</section>
