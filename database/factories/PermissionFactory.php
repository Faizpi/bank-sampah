<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Identity\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Permission> */
final class PermissionFactory extends Factory
{
    protected $model = Permission::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->lexify('??????.??????'),
            'description' => fake()->sentence(),
        ];
    }
}
