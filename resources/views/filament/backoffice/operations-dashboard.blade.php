<x-filament-panels::page>
    <section class="backoffice-page-intro" aria-labelledby="technical-overview-title">
        <p class="text-sm font-semibold text-forest-700">Administrasi sistem</p>
        <h2 id="technical-overview-title" class="mt-1 text-2xl font-bold text-deep-green">Kontrol teknis</h2>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-text-secondary">Pilih satu area kerja. Kontrol berisiko tidak lagi ditumpuk dalam satu halaman panjang.</p>
    </section>

    <nav class="mt-6 overflow-x-auto border-b border-gray-200" aria-label="Bagian kontrol teknis">
        <div class="flex min-w-max gap-6">
            <a href="{{ \App\Filament\Pages\TechnicalHealthPage::getUrl() }}" class="inline-flex min-h-12 items-center border-b-2 border-transparent px-1 text-sm font-semibold text-gray-700 hover:border-primary-500 hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2">Health</a>
            @if ($canManageSettings)
                <a href="{{ \App\Filament\Pages\TechnicalSettingsPage::getUrl() }}" class="inline-flex min-h-12 items-center border-b-2 border-transparent px-1 text-sm font-semibold text-gray-700 hover:border-primary-500 hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2">Pengaturan</a>
            @endif
            @if ($canManageMaintenance)
                <a href="{{ \App\Filament\Pages\TechnicalMaintenancePage::getUrl() }}" class="inline-flex min-h-12 items-center border-b-2 border-transparent px-1 text-sm font-semibold text-gray-700 hover:border-primary-500 hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2">Pemeliharaan</a>
            @endif
            @if ($canViewBackups || $canRunBackup || $canRestoreBackup)
                <a href="{{ \App\Filament\Pages\TechnicalBackupsPage::getUrl() }}" class="inline-flex min-h-12 items-center border-b-2 border-transparent px-1 text-sm font-semibold text-gray-700 hover:border-primary-500 hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2">Cadangan</a>
            @endif
            @if ($canExecuteRetention)
                <a href="{{ \App\Filament\Pages\TechnicalAuditRetentionPage::getUrl() }}" class="inline-flex min-h-12 items-center border-b-2 border-transparent px-1 text-sm font-semibold text-gray-700 hover:border-primary-500 hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2">Retensi audit</a>
            @endif
            @if ($canExecuteMediaRetention)
                <a href="{{ \App\Filament\Pages\TechnicalMediaRetentionPage::getUrl() }}" class="inline-flex min-h-12 items-center border-b-2 border-transparent px-1 text-sm font-semibold text-gray-700 hover:border-primary-500 hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2">Retensi foto</a>
            @endif
        </div>
    </nav>

    <p class="mt-4 max-w-2xl text-sm leading-6 text-gray-600">Health bersifat baca-saja. Pengaturan, pemeliharaan, cadangan, dan retensi hanya muncul jika akun memiliki izin teknis terkait.</p>
</x-filament-panels::page>
