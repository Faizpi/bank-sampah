<?php

declare(strict_types=1);

namespace App\Filament\Resources\Identity\Models\SessionInventories\Pages;

use App\Domain\Identity\Models\DatabaseSession;
use App\Filament\Resources\Identity\Models\SessionInventories\SessionInventoryResource;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Model;

final class ManageSessionInventories extends ManageRecords
{
    protected static string $resource = SessionInventoryResource::class;

    public function getTableRecordKey(Model|array $record): string
    {
        if (! $record instanceof DatabaseSession) {
            return parent::getTableRecordKey($record);
        }

        return $this->opaqueSessionKey((string) $record->getKey());
    }

    protected function resolveTableRecord(?string $key): ?Model
    {
        if ($key === null) {
            return null;
        }

        foreach (SessionInventoryResource::getEloquentQuery()->get() as $session) {
            $sessionId = (string) $session->getKey();

            if (hash_equals($key, $this->opaqueSessionKey($sessionId))) {
                return $session;
            }
        }

        return null;
    }

    private function opaqueSessionKey(string $sessionId): string
    {
        return hash_hmac('sha256', $sessionId, (string) config('app.key'));
    }
}
