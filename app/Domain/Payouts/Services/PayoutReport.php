<?php

declare(strict_types=1);

namespace App\Domain\Payouts\Services;

use App\Domain\Payouts\Models\SandboxPayout;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

final readonly class PayoutReport
{
    public function __construct(private PayoutAccess $access) {}

    /** @param array<string, mixed> $filters
     * @return Builder<SandboxPayout>
     */
    public function query(User $actor, array $filters): Builder
    {
        abort_unless($actor->hasRole('admin', 'bendahara'), 403);
        $dates = validator($filters, ['start' => ['required', 'date_format:Y-m-d'], 'end' => ['required', 'date_format:Y-m-d', 'after_or_equal:start'], 'search' => ['nullable', 'string', 'max:100']])->validate();
        $start = CarbonImmutable::parse($dates['start'], 'Asia/Jakarta')->startOfDay();
        $end = CarbonImmutable::parse($dates['end'], 'Asia/Jakarta')->addDay()->startOfDay();
        if ($start->diffInDays($end) > 366) {
            throw ValidationException::withMessages(['end' => 'Rentang laporan maksimal 366 hari.']);
        }
        $query = $this->access->visibleFor($actor)->where('created_at', '>=', $start)->where('created_at', '<', $end);
        $search = trim((string) ($dates['search'] ?? ''));
        if ($search !== '') {
            $query->where(fn ($q) => $q->where('request_number', 'like', '%'.$search.'%')->orWhereHas('customer', fn ($c) => $c->where('name', 'like', '%'.$search.'%')));
        }

        return $query;
    }
}
