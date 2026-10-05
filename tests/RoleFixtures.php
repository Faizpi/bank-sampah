<?php

declare(strict_types=1);

namespace Tests;

use App\Authorization\RoleCapabilities;
use App\Domain\Identity\Enums\UserRole;
use App\Models\User;
use App\Support\Auth\AdminReauthentication;

final class RoleFixtures
{
    /** @param list<string> $capabilities */
    public static function assign(User $user, array $capabilities, ?string $namedRole = null): User
    {
        $role = $namedRole === null ? null : UserRole::tryFrom($namedRole);
        if ($role === null && in_array('report.view', $capabilities, true) && ! in_array('user.view.area', $capabilities, true)) {
            $role = UserRole::Admin;
        }
        if ($role === null) {
            foreach (UserRole::cases() as $candidate) {
                if (array_diff($capabilities, RoleCapabilities::for($candidate)) === []) {
                    $role = $candidate;
                    break;
                }
            }
        }
        if ($role === null) {
            throw new \LogicException('Fixture requests capabilities that no single fixed role possesses: '.implode(', ', $capabilities));
        }
        $user->forceFill(['role' => $role])->save();
        if ($role === UserRole::Admin && array_intersect(['transaction.correct', 'transaction.reverse', 'ledger.adjust'], $capabilities) !== []) {
            app(AdminReauthentication::class)->confirm($user, 'password');
        }

        return $user->fresh();
    }
}
