<?php

declare(strict_types=1);

namespace App\Filament\Resources\Identity\Models\SessionInventories\Actions;

use Filament\Actions\Action;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Model;

final class RevokeSessionAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->successNotificationTitle('Sesi pengguna telah diakhiri.');
    }

    public function resolveRecordKey(Model|array $record): ?string
    {
        $livewire = $this->getLivewire();

        return $livewire instanceof HasTable
            ? $livewire->getTableRecordKey($record)
            : parent::resolveRecordKey($record);
    }
}
