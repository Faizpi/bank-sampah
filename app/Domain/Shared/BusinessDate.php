<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

final readonly class BusinessDate
{
    private const TIMEZONE = 'Asia/Jakarta';

    private function __construct(private DateTimeImmutable $date) {}

    public static function fromString(string $value): self
    {
        $timezone = new DateTimeZone(self::TIMEZONE);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $timezone);
        $errors = DateTimeImmutable::getLastErrors();

        if ($date === false || ($errors !== false && ($errors['warning_count'] !== 0 || $errors['error_count'] !== 0)) || $date->format('Y-m-d') !== $value) {
            throw new InvalidValue('Tanggal bisnis harus berformat Y-m-d dan merupakan tanggal yang valid.');
        }

        return new self($date);
    }

    public static function fromInstant(DateTimeInterface $instant): self
    {
        $date = DateTimeImmutable::createFromInterface($instant)->setTimezone(new DateTimeZone(self::TIMEZONE));

        return self::fromString($date->format('Y-m-d'));
    }

    public function value(): string
    {
        return $this->date->format('Y-m-d');
    }

    public function nextDay(): self
    {
        return new self($this->date->modify('+1 day'));
    }

    public function equals(self $other): bool
    {
        return $this->value() === $other->value();
    }
}
