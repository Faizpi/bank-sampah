<?php

declare(strict_types=1);

namespace App\Domain\WasteMaster\Models;

use App\Domain\Shared\InvalidValue;
use App\Domain\Shared\Weight;
use Database\Factories\WasteUnitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;
use LogicException;

final class WasteUnit extends WasteMasterModel
{
    public const CLASSIFICATION_WEIGHT = 'weight';

    public const CLASSIFICATION_NON_WEIGHT = 'non_weight';

    /** @use HasFactory<WasteUnitFactory> */
    use HasFactory;

    protected $fillable = ['code', 'name', 'symbol', 'classification', 'conversion_factor_to_kg', 'is_active'];

    protected static function newFactory(): WasteUnitFactory
    {
        return WasteUnitFactory::new();
    }

    protected function casts(): array
    {
        return ['conversion_factor_to_kg' => 'decimal:6', 'is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        self::saving(function (self $unit): void {
            if ($unit->conversion_factor_to_kg === null) {
                return;
            }

            if ($unit->classification !== self::CLASSIFICATION_WEIGHT || self::factorToMicros($unit->conversionFactor()) <= 0) {
                throw ValidationException::withMessages([
                    'conversion_factor_to_kg' => 'A conversion factor must be positive and is allowed only for physical weight units.',
                ]);
            }
        });
    }

    public function isCanonicalKilogram(): bool
    {
        return $this->classification === self::CLASSIFICATION_WEIGHT
            && $this->code === 'KG'
            && $this->symbol === 'kg';
    }

    public function toCanonicalKilograms(string $quantity): string
    {
        $weight = Weight::fromDecimal($quantity);

        if ($this->isCanonicalKilogram()) {
            return $weight->decimal();
        }

        if ($this->classification !== self::CLASSIFICATION_WEIGHT || $this->conversion_factor_to_kg === null) {
            throw new LogicException('Only physical weight units with an explicit conversion factor can convert to kilograms.');
        }

        $factorMicros = self::factorToMicros($this->conversionFactor());
        if ($weight->grams() > intdiv(PHP_INT_MAX, $factorMicros)) {
            throw new LogicException('The conversion result exceeds the integer limit.');
        }

        $gramsTimesMicros = $weight->grams() * $factorMicros;
        if ($gramsTimesMicros % 1_000_000 !== 0) {
            throw new LogicException('The conversion result cannot be represented exactly to three decimal kilogram places.');
        }

        return Weight::fromGrams(intdiv($gramsTimesMicros, 1_000_000))->decimal();
    }

    private function conversionFactor(): string
    {
        return (string) $this->getAttribute('conversion_factor_to_kg');
    }

    private static function factorToMicros(string $factor): int
    {
        if (preg_match('/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,6})?$/D', $factor) !== 1) {
            throw new InvalidValue('The conversion factor must be a canonical decimal with at most six fractional digits.');
        }

        [$whole, $fraction] = array_pad(explode('.', $factor, 2), 2, '');
        if (strlen($whole) > strlen((string) intdiv(PHP_INT_MAX, 1_000_000))) {
            throw new InvalidValue('The conversion factor exceeds the integer limit.');
        }

        $wholeMicros = (int) $whole * 1_000_000;
        $fractionMicros = (int) str_pad($fraction, 6, '0');
        if ($fractionMicros > PHP_INT_MAX - $wholeMicros) {
            throw new InvalidValue('The conversion factor exceeds the integer limit.');
        }

        return $wholeMicros + $fractionMicros;
    }

    /** @return HasMany<WasteType, $this> */
    public function wasteTypes(): HasMany
    {
        return $this->hasMany(WasteType::class, 'waste_unit_id');
    }
}
