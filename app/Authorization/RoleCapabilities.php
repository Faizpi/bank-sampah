<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Domain\Identity\Enums\UserRole;

final class RoleCapabilities
{
    private const COMMON = ['profile.view', 'profile.update', 'notification.view', 'announcement.view', 'region.view', 'waste.view', 'price.view', 'mobile-service.view', 'target.view', 'grocery.package.view'];

    /** @return list<string> */
    public static function for(UserRole $role): array
    {
        return [...self::COMMON, ...match ($role) {
            UserRole::Citizen => ['customer.view', 'customer.update', 'deposit.view', 'ledger.view', 'correction.view-customer', 'pickup.view', 'pickup.request', 'pickup.cancel', 'withdrawal.view', 'withdrawal.request', 'withdrawal.cancel', 'grocery.view', 'grocery.request', 'grocery.cancel'],
            UserRole::Officer => ['user.view', 'user.view.area', 'customer.view', 'deposit.view', 'deposit.create', 'deposit.update-draft', 'deposit.finalize', 'pickup.view', 'pickup.claim', 'pickup.execute', 'pickup.complete', 'pickup.cancel', 'grocery.view', 'grocery.prepare', 'grocery.handover', 'grocery.cancel', 'mobile-service.operate'],
            UserRole::Treasurer => ['user.view', 'user.view.area', 'customer.view', 'withdrawal.view', 'withdrawal.pay', 'withdrawal.cancel', 'report.view', 'report.export'],
            UserRole::Admin => ['backoffice.access', 'user.view', 'user.view.all', 'user.create', 'user.update', 'user.activate', 'user.verify', 'user.reject', 'user.reset-password', 'role.manage', 'session.revoke', 'customer.view', 'customer.update', 'customer.card.issue', 'customer.qr.rotate', 'region.manage', 'waste.manage', 'price.manage', 'deposit.view', 'ledger.view', 'transaction.correct', 'transaction.reverse', 'ledger.adjust', 'correction.view-customer', 'pickup.view', 'withdrawal.view', 'grocery.view', 'grocery.package.manage', 'transaction.exception', 'announcement.manage', 'announcement.publish', 'mobile-service.manage', 'target.manage', 'target.publish', 'statistics.internal.view', 'statistics.public.manage', 'qr-verification.rotate', 'report.view', 'report.export', 'audit.view', 'reconciliation.view', 'reconciliation.create', 'reconciliation.approve', 'system.settings.manage', 'system.maintenance', 'backup.run', 'backup.view', 'backup.restore', 'audit.retention.execute', 'media.retention.execute'],
        }];
    }
}
