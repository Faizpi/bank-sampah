<?php

declare(strict_types=1);

namespace App\Support;

use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\CustomersRegions\Models\Rw;
use Illuminate\Database\Eloquent\Builder;

final class RegionOptions
{
    public static function label(Rt $rt): string
    {
        return collect([$rt->rw?->dusun?->name, $rt->rw?->name, $rt->name])->filter()->implode(' / ');
    }

    /** @return array<int, string> */
    public static function rts(?int $rwId = null): array
    {
        return Rt::query()->with('rw.dusun')->where('is_active', true)
            ->whereHas('rw', fn (Builder $rw): Builder => $rw->where('is_active', true)->whereHas('dusun', fn (Builder $dusun): Builder => $dusun->where('is_active', true)))
            ->when($rwId !== null, fn (Builder $query): Builder => $query->where('rw_id', $rwId))
            ->orderBy('name')->get()->mapWithKeys(fn (Rt $rt): array => [$rt->id => self::label($rt)])->all();
    }

    /** @return array<int, string> */
    public static function rws(): array
    {
        return Rw::query()->with('dusun')->where('is_active', true)->whereHas('dusun', fn (Builder $dusun): Builder => $dusun->where('is_active', true))
            ->orderBy('name')->get()->mapWithKeys(fn (Rw $rw): array => [$rw->id => $rw->dusun->name.' / '.$rw->name])->all();
    }
}
