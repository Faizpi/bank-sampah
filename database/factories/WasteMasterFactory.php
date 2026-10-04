<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\WasteMaster\Support\WasteMasterMutationGuard;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * @template TModel of \App\Domain\WasteMaster\Models\WasteMasterModel
 *
 * @extends Factory<TModel>
 */
abstract class WasteMasterFactory extends Factory
{
    /**
     * @param  (callable(array<string, mixed>): array<string, mixed>)|array<string, mixed>  $attributes
     * @return TModel|Collection<int, TModel>
     */
    public function create($attributes = [], ?Model $parent = null)
    {
        if (! app()->environment('testing')) {
            throw new LogicException('Waste master factories are restricted to the testing environment.');
        }

        return WasteMasterMutationGuard::run(fn () => parent::create($attributes, $parent));
    }
}
