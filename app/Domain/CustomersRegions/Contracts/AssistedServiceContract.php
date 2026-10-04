<?php

declare(strict_types=1);

namespace App\Domain\CustomersRegions\Contracts;

use App\Domain\Shared\InvalidValue;

final readonly class AssistedServiceContract
{
    private function __construct(
        public int $ownerId,
        public int $operatorId,
        public string $serviceType,
        public Consent $consent,
        public EvidenceReference $evidence,
    ) {}

    public static function create(
        int $ownerId,
        int $operatorId,
        string $serviceType,
        Consent $consent,
        EvidenceReference $evidence,
    ): self {
        if ($ownerId < 1 || $operatorId < 1 || $ownerId === $operatorId) {
            throw new InvalidValue('Pemilik dan pelaksana layanan berbantuan harus berbeda dan valid.');
        }

        if ($serviceType === '' || trim($serviceType) !== $serviceType || preg_match('/[\x00-\x1F\x7F]/', $serviceType) === 1) {
            throw new InvalidValue('Jenis layanan berbantuan wajib diisi dengan nilai yang aman.');
        }

        return new self($ownerId, $operatorId, $serviceType, $consent, $evidence);
    }
}
