<?php

declare(strict_types=1);

namespace App\Domain\Reports\Policies;

use App\Domain\Reports\Enums\ReportType;
use Illuminate\Validation\ValidationException;

final class ReportTypeAccess
{
    public const TREASURER = 'treasurer';

    public const BACKOFFICE = 'backoffice';

    /** @return array<string, string> */
    public function optionsFor(string $surface): array
    {
        return collect($this->allowedFor($surface))
            ->mapWithKeys(static fn (ReportType $type): array => [$type->value => self::label($type)])
            ->all();
    }

    public function defaultFor(string $surface): ReportType
    {
        return $this->allowedFor($surface)[0];
    }

    public function assertAllowed(string $surface, string $reportType): ReportType
    {
        $type = ReportType::tryFrom($reportType);
        if ($type === null || ! in_array($type, $this->allowedFor($surface), true)) {
            throw ValidationException::withMessages(['reportType' => 'Jenis laporan tidak diizinkan.']);
        }

        return $type;
    }

    /** @return list<ReportType> */
    private function allowedFor(string $surface): array
    {
        return match ($surface) {
            self::TREASURER => [ReportType::Withdrawals, ReportType::Groceries],
            self::BACKOFFICE => ReportType::cases(),
            default => throw ValidationException::withMessages(['surface' => 'Permukaan laporan tidak diizinkan.']),
        };
    }

    private static function label(ReportType $type): string
    {
        return match ($type) {
            ReportType::Deposits => 'Setoran',
            ReportType::Withdrawals => 'Pencairan',
            ReportType::Groceries => 'Sembako',
            ReportType::Pickups => 'Penjemputan',
            ReportType::Participation => 'Partisipasi',
        };
    }
}
