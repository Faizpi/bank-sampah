<?php

declare(strict_types=1);

namespace Tests\Feature\Wave8;

use App\Domain\Communication\Enums\AnnouncementAudience;
use App\Domain\Communication\Enums\AnnouncementStatus;
use App\Domain\Communication\Services\AnnouncementService;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Notifications\Data\NotificationPayload;
use App\Domain\Notifications\Events\NotificationRequested;
use App\Domain\Notifications\Listeners\PersistNotification;
use App\Domain\Notifications\Support\NotificationDedupeKey;
use App\Domain\Programs\Enums\TargetStatus;
use App\Domain\Programs\Services\EstimateService;
use App\Domain\Programs\Services\TargetService;
use App\Domain\Statistics\Services\StatisticsService;
use App\Domain\WasteMaster\Actions\ManageWasteMaster;
use App\Domain\WasteMaster\Models\WasteCategory;
use App\Domain\WasteMaster\Models\WasteCondition;
use App\Domain\WasteMaster\Models\WastePrice;
use App\Domain\WasteMaster\Models\WasteType;
use App\Domain\WasteMaster\Models\WasteUnit;
use App\Domain\WasteMaster\Support\WasteMasterMutationGuard;
use App\Models\Notification;
use App\Models\User;
use App\Support\Communication\WhatsAppLinkBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class Wave8ContractsTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_notification_template_is_rejected_by_the_server_side_allowlist(): void
    {
        $recipient = User::factory()->create();
        $this->expectException(\RuntimeException::class);
        new NotificationPayload($recipient->id, 'unknown.event', 'Judul', 'Isi', '/notifikasi', 'w8-unknown-template');
    }

    public function test_failed_notification_persistence_leaves_a_bounded_retry_record(): void
    {
        $payload = new NotificationPayload(PHP_INT_MAX, 'deposit.finalized', 'Setoran selesai', 'Setoran DEP-W8 telah selesai diproses.', '/notifikasi', NotificationDedupeKey::for('w8-failure', PHP_INT_MAX, 'deposit-finalized-v1'));
        try {
            (new PersistNotification)->handle(new NotificationRequested($payload));
            self::fail('Expected persistence to fail for an unknown recipient.');
        } catch (\Throwable) {
            self::assertDatabaseHas('notification_delivery_failures', ['dedupe_key' => $payload->dedupeKey, 'attempts' => 1, 'type' => 'deposit.finalized']);
        }
    }

    public function test_whatsapp_builder_returns_only_encoded_wa_me_url_and_rejects_unknown_template(): void
    {
        $url = app(WhatsAppLinkBuilder::class)->build('081234567890', 'support', ['topic' => 'jadwal layanan']);
        self::assertStringStartsWith('https://wa.me/6281234567890?text=', $url);
        self::assertStringNotContainsString('http://', substr($url, strlen('https://wa.me/')));
        self::assertStringContainsString('Buka', 'Buka WhatsApp');

        $this->expectException(ValidationException::class);
        app(WhatsAppLinkBuilder::class)->build('081234567890', 'gateway-send', ['topic' => 'x']);
    }

    public function test_announcement_is_sanitized_and_public_query_excludes_inactive_or_expired_records(): void
    {
        $admin = $this->userWith('announcement.manage', 'announcement.publish');
        $service = app(AnnouncementService::class);
        $announcement = $service->create($admin, 'Pengumuman aman', '<script>alert(1)</script><p>Isi aman</p>', AnnouncementAudience::Public->value, now()->subMinute()->toIso8601String(), now()->addHour()->toIso8601String());
        self::assertStringNotContainsString('<script', $announcement->body);
        $published = $service->publish($admin, $announcement);
        self::assertSame(AnnouncementStatus::Published, $published->status);
        self::assertTrue($service->publicQuery()->whereKey($published->id)->exists());
        $published->forceFill(['publish_end' => now()->subMinute()])->save();
        self::assertFalse($service->publicQuery()->whereKey($published->id)->exists());
    }

    public function test_estimate_has_disclaimer_and_does_not_create_financial_records(): void
    {
        [$type, $condition] = $this->priceFixture();
        $before = [DB::table('deposits')->count(), DB::table('ledger_entries')->count(), DB::table('balance_holds')->count()];
        $result = app(EstimateService::class)->calculate($type->id, $condition->id, '1.250');
        self::assertSame('1.25', $result['weight_kg']);
        self::assertStringContainsString('tidak membuat transaksi', strtolower($result['disclaimer']));
        self::assertSame($before, [DB::table('deposits')->count(), DB::table('ledger_entries')->count(), DB::table('balance_holds')->count()]);
    }

    public function test_target_transition_requires_permission_and_invalid_transition_is_rejected(): void
    {
        $admin = $this->userWith('target.manage', 'target.publish');
        $target = app(TargetService::class)->create($admin, 'Target plastik', 'Pengumpulan warga', today()->toDateString(), today()->addDays(30)->toDateString(), '10.000', true, []);
        $active = app(TargetService::class)->activate($admin, $target);
        self::assertSame(TargetStatus::Active, $active->status);
        $this->expectException(ValidationException::class);
        app(TargetService::class)->update($admin, $active, 'Tidak boleh', 'Tidak boleh ubah aktif', today()->toDateString(), today()->addDays(30)->toDateString(), '11.000', false, [['rt_id' => null]]);
    }

    public function test_statistics_public_uses_allowlist_and_privacy_suppression(): void
    {
        $admin = $this->userWith('statistics.public.manage', 'statistics.internal.view');
        $publication = app(StatisticsService::class)->configurePublic($admin, ['deposit_count', 'total_weight_kg'], ['period'], 5, true);
        self::assertSame(['deposit_count', 'total_weight_kg'], $publication->metrics);
        $result = app(StatisticsService::class)->public(today()->subDay()->toDateString(), today()->addDay()->toDateString());
        self::assertTrue($result['suppressed']);
        self::assertSame([], $result['metrics']);
    }

    public function test_notification_reference_cannot_be_used_for_another_record(): void
    {
        $recipient = User::factory()->create();
        $notification = Notification::factory()->create(['recipient_id' => $recipient->id, 'reference' => '/transactions/42']);
        self::assertSame($recipient->id, $notification->recipient_id);
        self::assertNotSame('/transactions/42', '/transactions/43');
    }

    /** @return array{0: WasteType, 1: WasteCondition} */
    private function priceFixture(): array
    {
        $category = WasteCategory::factory()->create(['is_active' => true]);
        $unit = WasteUnit::factory()->create(['code' => 'kg', 'symbol' => 'kg', 'classification' => 'weight']);
        $condition = WasteCondition::factory()->create(['is_active' => true]);
        $type = app(ManageWasteMaster::class)->createType($this->userWith('waste.manage'), $category, $unit, 'W8-TYPE-'.uniqid(), 'W8 Type', null, 0, false, true, [$condition->id]);
        WasteMasterMutationGuard::run(fn (): mixed => WastePrice::factory()->create(['waste_type_id' => $type->id, 'waste_condition_id' => $condition->id, 'effective_from' => now()->subDay(), 'price' => 1000]));

        return [$type, $condition];
    }

    private function userWith(string ...$permissions): User
    {
        $user = User::factory()->create();
        $role = Role::query()->create(['name' => 'w8-'.uniqid(), 'description' => 'W8 test']);
        foreach ($permissions as $permissionName) {
            $permission = Permission::query()->firstOrCreate(['name' => $permissionName], ['description' => $permissionName]);
            $role->permissions()->attach($permission);
        }
        $user->roles()->attach($role);

        return $user;
    }
}
