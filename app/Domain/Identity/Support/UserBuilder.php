<?php

declare(strict_types=1);

namespace App\Domain\Identity\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

/** @extends Builder<User> */
final class UserBuilder extends Builder
{
    /** @param array<string, mixed> $values */
    public function update(array $values): int
    {
        if (array_intersect(['role', 'status', 'deleted_at'], array_keys($values)) !== [] && ! User::isPersistingInstance()) {
            throw new LogicException('Access changes must use a guarded User instance, not bulk updates.');
        }

        return parent::update($values);
    }

    public function delete(): int
    {
        if (! User::isPersistingInstance()) {
            throw new LogicException('Users must be deleted through a guarded instance.');
        }

        return parent::delete();
    }
}
