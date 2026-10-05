<?php

declare(strict_types=1);

namespace Tests\Feature\Withdrawals;

use App\Authorization\PermissionChecker;
use App\Domain\AuditReconciliation\Models\AuditLog;
use App\Domain\CustomersRegions\Actions\ManageRegions;
use App\Domain\CustomersRegions\Models\ServiceArea;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\StaffProfile;
use App\Domain\Identity\Models\StaffServiceArea;
use App\Domain\Ledger\Models\BalanceHold;
use App\Domain\Ledger\Models\LedgerAccount;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Ledger\Services\LedgerService;
use App\Domain\Notifications\Events\NotificationRequested;
use App\Domain\Withdrawals\Enums\WithdrawalStatus;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Domain\Withdrawals\Services\WithdrawalService;
use App\Livewire\Citizen\WithdrawalRequestForm;
use App\Livewire\Citizen\WithdrawalShow;
use App\Livewire\Treasurer\WithdrawalPayments;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

final class WithdrawalWaveTest extends TestCase
{
    use RefreshDatabase;

    public function test_lane_a_request_creates_one_atomic_hold_and_same_retry_returns_the_original(): void
    {
        [$customer, $area] = $this->context();
        $this->grant($customer, ['withdrawal.request', 'withdrawal.view', 'withdrawal.cancel']);
        $this->credit($customer, 100_000);
        Event::fake([NotificationRequested::class]);

        $service = app(WithdrawalService::class);
        $payload = $this->requestPayload($area);
        $created = $service->request($customer, $payload + ['amount' => '40000'], 'w6-request-key-0001');
        $retry = $service->request($customer, $payload + ['amount' => '40000'], 'w6-request-key-0001');

        self::assertSame($created->id, $retry->id);
        self::assertSame(WithdrawalStatus::PendingVerification, $created->status);
        self::assertSame(40_000, $created->amount);
        self::assertSame(1, BalanceHold::query()->where('source_id', $created->id)->count());
        self::assertSame(60_000, $customer->ledgerAccount()->firstOrFail()->fresh()->availableBalance());
        self::assertSame(1, AuditLog::query()->where('action', 'withdrawal.requested')->count());
        Event::assertDispatchedTimes(NotificationRequested::class, 1);
    }

    public function test_lane_a_rejects_insufficient_balance_invalid_amount_and_conflicting_retry_without_effects(): void
    {
        [$customer, $area] = $this->context();
        $this->grant($customer, ['withdrawal.request', 'withdrawal.view']);
        $this->credit($customer, 20_000);
        $service = app(WithdrawalService::class);
        $payload = $this->requestPayload($area);

        $this->expectException(ValidationException::class);
        $service->request($customer, $payload + ['amount' => 30_000], 'w6-insufficient-key-0001');
        self::assertDatabaseCount('withdrawal_requests', 0);
        self::assertDatabaseCount('balance_holds', 0);
        self::assertDatabaseCount('idempotency_keys', 0);

        try {
            $service->request($customer, $payload + ['amount' => 9_999], 'w6-invalid-amount-0001');
            self::fail('The minimum amount must be enforced.');
        } catch (ValidationException) {
            self::assertDatabaseCount('withdrawal_requests', 0);
        }

        $service->request($customer, $payload + ['amount' => 10_000], 'w6-conflict-key-0001');
        $this->expectException(ValidationException::class);
        $service->request($customer, $payload + ['amount' => 11_000], 'w6-conflict-key-0001');
    }

    public function test_citizen_withdrawal_form_blocks_amount_above_available_balance_before_request(): void
    {
        [$customer] = $this->context();
        $this->grant($customer, ['withdrawal.request']);
        $this->credit($customer, 20_000);

        Livewire::actingAs($customer)
            ->test(WithdrawalRequestForm::class)
            ->assertSee('Saldo tersedia')
            ->assertSee('Rp20.000')
            ->assertSeeHtml('wire:model.live.debounce.300ms="amount"')
            ->set('amount', '30000')
            ->assertHasErrors(['amount'])
            ->assertSee('Nominal melebihi saldo tersedia.')
            ->set('pickupLocation', 'Balai warga')
            ->call('submit')
            ->assertHasErrors(['amount']);

        self::assertDatabaseCount('withdrawal_requests', 0);
        self::assertDatabaseCount('balance_holds', 0);
    }

    public function test_citizen_multi_area_form_requires_a_valid_selection_and_persists_the_selected_snapshot(): void
    {
        [$customer, $areaA] = $this->context();
        $this->grant($customer, ['withdrawal.request', 'withdrawal.view']);
        $this->credit($customer, 50_000);
        $manager = User::factory()->create();
        $this->grant($manager, ['region.manage']);
        $rt = $customer->customerProfile()->firstOrFail()->rt()->firstOrFail();
        $areaB = app(ManageRegions::class)->createServiceArea($manager, 'Area W6 Form '.$customer->id, [$rt]);

        Livewire::actingAs($customer)
            ->test(WithdrawalRequestForm::class)
            ->assertSee('Pilih area layanan')
            ->set('amount', '20000')
            ->set('pickupLocation', 'Balai warga W6')
            ->call('submit')
            ->assertHasErrors(['serviceAreaId']);

        self::assertDatabaseCount('withdrawal_requests', 0);
        self::assertDatabaseCount('balance_holds', 0);

        Livewire::actingAs($customer)
            ->test(WithdrawalRequestForm::class)
            ->set('amount', '20000')
            ->set('pickupLocation', 'Balai warga W6')
            ->set('serviceAreaId', (string) $areaB->id)
            ->call('submit')
            ->assertHasNoErrors()
            ->assertRedirect();

        self::assertDatabaseHas('withdrawal_requests', [
            'customer_id' => $customer->id,
            'rt_id' => $rt->id,
            'service_area_id' => $areaB->id,
        ]);
        self::assertNotSame($areaA->id, $areaB->id);
    }

    public function test_lane_a_requested_amount_is_immutable_after_creation(): void
    {
        [$customer, $area] = $this->context();
        $this->grant($customer, ['withdrawal.request', 'withdrawal.view']);
        $this->credit($customer, 50_000);
        $withdrawal = app(WithdrawalService::class)->request($customer, $this->requestPayload($area) + ['amount' => 20_000], 'w6-immutable-key-0001');

        $this->expectException(LogicException::class);
        $withdrawal->forceFill(['amount' => 10_000])->save();
    }

    public function test_lane_b_rejects_self_approval_and_reject_releases_hold_without_payment(): void
    {
        [$customer, $area] = $this->context();
        $this->grant($customer, ['withdrawal.request', 'withdrawal.view', 'withdrawal.approve']);
        $this->credit($customer, 80_000);
        $service = app(WithdrawalService::class);
        $withdrawal = $service->request($customer, $this->requestPayload($area) + ['amount' => 25_000], 'w6-self-approval-key-0001');

        try {
            $service->approve($customer, $withdrawal, true);
            self::fail('The requester must not approve the same withdrawal.');
        } catch (AuthorizationException) {
            self::assertSame(WithdrawalStatus::PendingVerification, $withdrawal->fresh()->status);
        }

        $approver = User::factory()->create();
        $this->grant($approver, ['withdrawal.approve', 'withdrawal.view', 'user.view.all']);
        Event::fake([NotificationRequested::class]);
        $rejected = $service->approve($approver, $withdrawal, false, 'Dokumen penerima belum lengkap.');

        self::assertSame(WithdrawalStatus::Rejected, $rejected->status);
        self::assertSame(BalanceHold::STATUS_RELEASED, $rejected->balanceHold()->firstOrFail()->status);
        self::assertSame(0, LedgerEntry::query()->where('source_type', WithdrawalRequest::class)->count());
        self::assertSame(80_000, $customer->ledgerAccount()->firstOrFail()->fresh()->availableBalance());
        self::assertSame(1, AuditLog::query()->where('action', 'withdrawal.rejected')->count());
        Event::assertDispatchedTimes(NotificationRequested::class, 1);
    }

    public function test_lane_b_approval_and_payer_assignment_require_separate_permissions_and_area_scope(): void
    {
        [$customer, $area] = $this->context();
        $this->grant($customer, ['withdrawal.request', 'withdrawal.view']);
        $this->credit($customer, 100_000);
        $service = app(WithdrawalService::class);
        $withdrawal = $service->request($customer, $this->requestPayload($area) + ['amount' => 30_000], 'w6-assignment-key-0001');
        $approver = User::factory()->create();
        $this->grant($approver, ['withdrawal.approve', 'withdrawal.view', 'user.view.all']);
        $approved = $service->approve($approver, $withdrawal, true);
        self::assertSame(WithdrawalStatus::Approved, $approved->status);

        $wrongPayer = User::factory()->create();
        $this->grant($wrongPayer, ['withdrawal.pay', 'withdrawal.view']);
        $wrongPayer->staffProfile()->create(['staff_number' => 'W6-WRONG-'.$wrongPayer->id, 'service_area_id' => null, 'active_from' => today(), 'active_to' => null]);
        $this->expectException(ValidationException::class);
        $service->assignPayer($approver, $approved, $wrongPayer);
    }

    public function test_lane_b_record_query_requires_direct_view_permission(): void
    {
        [$customer, $area] = $this->context();
        $this->grant($customer, ['withdrawal.request']);
        $this->credit($customer, 50_000);
        $withdrawal = app(WithdrawalService::class)->request($customer, $this->requestPayload($area) + ['amount' => 20_000], 'w6-record-auth-key-0001');

        $this->expectException(AuthorizationException::class);
        app(WithdrawalService::class)->visibleFor($customer)->whereKey($withdrawal->id)->get();
    }

    public function test_lane_b_payable_query_requires_direct_payment_permission(): void
    {
        [$customer, $area] = $this->context();
        $this->grant($customer, ['withdrawal.request', 'withdrawal.view']);
        $this->credit($customer, 50_000);
        $withdrawal = app(WithdrawalService::class)->request($customer, $this->requestPayload($area) + ['amount' => 20_000], 'w6-payable-auth-key-0001');

        $this->expectException(AuthorizationException::class);
        app(WithdrawalService::class)->payableFor($customer)->whereKey($withdrawal->id)->get();
    }

    public function test_payment_boundary_allows_bendahara_and_assigned_petugas_but_denies_unassigned_payment(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        [$customer, $area] = $this->context();
        $this->grant($customer, ['withdrawal.request', 'withdrawal.view']);
        $this->credit($customer, 100_000);
        $approver = User::factory()->create(['status' => UserStatus::Active]);
        $this->grant($approver, ['withdrawal.approve', 'withdrawal.view', 'user.view.all']);
        $bendahara = User::factory()->create(['status' => UserStatus::Active]);
        $bendahara->roles()->attach(Role::query()->where('name', 'bendahara')->sole());
        $bendahara->staffProfile()->create(['staff_number' => 'W6-BEN-'.$bendahara->id, 'service_area_id' => $area->id, 'active_from' => today(), 'active_to' => null]);
        $petugas = User::factory()->create(['status' => UserStatus::Active]);
        $petugas->roles()->attach(Role::query()->where('name', 'petugas')->sole());
        $petugas->staffProfile()->create(['staff_number' => 'W6-PET-'.$petugas->id, 'service_area_id' => $area->id, 'active_from' => today(), 'active_to' => null]);
        $service = app(WithdrawalService::class);
        $withdrawal = $service->approve($approver, $service->request($customer, $this->requestPayload($area) + ['amount' => 20_000], 'w6-role-boundary-request-0001'), true);
        $withdrawal = $service->assignPayer($approver, $withdrawal, $bendahara);

        self::assertTrue(app(PermissionChecker::class)->allows($bendahara, 'withdrawal.pay'));
        self::assertTrue(app(PermissionChecker::class)->allows($petugas, 'withdrawal.pay'));
        self::assertTrue(app(WithdrawalService::class)->payableFor($bendahara)->whereKey($withdrawal->id)->exists());
        self::assertFalse(app(WithdrawalService::class)->payableFor($petugas)->whereKey($withdrawal->id)->exists());

        Livewire::actingAs($petugas)
            ->test(WithdrawalPayments::class)
            ->assertSee('Pembayaran hanya untuk Bendahara atau petugas yang ditugaskan.');

        $this->expectException(AuthorizationException::class);
        $service->pay($petugas, $withdrawal, 'kartu_nasabah', (string) $customer->customerProfile?->customer_number, UploadedFile::fake()->image('unassigned.png'), 'w6-role-boundary-payment-0001');
    }

    public function test_bendahara_payment_form_explains_identity_methods_and_accepts_one_compressed_photo(): void
    {
        [$customer, $area] = $this->context();
        $this->grant($customer, ['withdrawal.request', 'withdrawal.view']);
        $this->credit($customer, 50_000);
        $approver = User::factory()->create();
        $this->grant($approver, ['withdrawal.approve', 'withdrawal.view', 'user.view.all']);
        $payer = $this->payerFor($area);
        $service = app(WithdrawalService::class);
        $withdrawal = $service->assignPayer($approver, $service->approve($approver, $service->request($customer, $this->requestPayload($area) + ['amount' => 20_000], 'w6-form-request-0001'), true), $payer);

        Livewire::actingAs($payer)
            ->test(WithdrawalPayments::class)
            ->call('select', $withdrawal->id)
            ->assertSee('Kartu dan nomor nasabah berbeda')
            ->assertSee('Kartu nasabah adalah media identitas fisik atau digital.')
            ->assertSee('Pindai QR kartu nasabah')
            ->assertSee('Ambil dari kamera')
            ->assertSee('Pilih dari galeri')
            ->assertSeeHtml('data-photo-picker-max="1"')
            ->assertSeeHtml('accept="image/*"')
            ->assertDontSeeHtml('application/pdf')
            ->set('recipientVerification', 'nomor_nasabah')
            ->assertSee('Nomor nasabah adalah kode unik warga.');
    }

    public function test_payment_requires_review_confirmation_before_any_financial_effect(): void
    {
        Storage::fake('media_private');
        [$customer, $area] = $this->context();
        $this->grant($customer, ['withdrawal.request', 'withdrawal.view']);
        $this->credit($customer, 50_000);
        $approver = User::factory()->create();
        $this->grant($approver, ['withdrawal.approve', 'withdrawal.view', 'user.view.all']);
        $payer = $this->payerFor($area);
        $service = app(WithdrawalService::class);
        $withdrawal = $service->assignPayer($approver, $service->approve($approver, $service->request($customer, $this->requestPayload($area) + ['amount' => 20_000], 'w6-confirmation-request-0001'), true), $payer);

        Livewire::actingAs($payer)
            ->test(WithdrawalPayments::class)
            ->call('select', $withdrawal->id)
            ->set('recipientReference', (string) $customer->customerProfile?->customer_number)
            ->set('proof', UploadedFile::fake()->image('payment-proof.png'))
            ->call('pay')
            ->assertHasErrors(['selectedWithdrawalId']);

        self::assertSame(WithdrawalStatus::ReadyForPickup, $withdrawal->fresh()->status);
        self::assertSame(0, LedgerEntry::query()->where('source_type', WithdrawalRequest::class)->where('direction', LedgerEntry::DIRECTION_OUT)->count());
    }

    public function test_treasurer_success_receipt_renders_all_payment_facts(): void
    {
        $payer = User::factory()->create(['status' => UserStatus::Active]);
        $this->grant($payer, ['withdrawal.pay']);
        $occurredAt = '15 Agustus 2026, 10:30';

        Livewire::actingAs($payer)
            ->test(WithdrawalPayments::class)
            ->set('receipt', ['number' => 'WD-PAYMENT-001', 'value' => 35_000, 'occurredAt' => $occurredAt, 'status' => 'sudah_dibayar'])
            ->assertSee('Berhasil')
            ->assertSee('WD-PAYMENT-001')
            ->assertSee('Rp 35.000')
            ->assertSee($occurredAt);
    }

    public function test_payment_card_scan_uses_camera_with_manual_fallback_and_cleanup(): void
    {
        $view = file_get_contents(resource_path('views/livewire/treasurer/withdrawal-payments.blade.php'));

        self::assertIsString($view);
        self::assertStringContainsString("'BarcodeDetector' in window", $view);
        self::assertStringContainsString('navigator.mediaDevices?.getUserMedia', $view);
        self::assertStringContainsString('$wire.scanCustomerCard(rawValue)', $view);
        self::assertStringContainsString('stream?.getTracks().forEach((track) => track.stop())', $view);
        self::assertStringContainsString('wire:model="scanToken"', $view);
        self::assertStringContainsString('$wire.closeScanner()', $view);
    }

    public function test_lane_c_payment_verifies_recipient_keeps_proof_private_and_posts_one_outgoing_entry(): void
    {
        Storage::fake('media_private');
        [$customer, $area] = $this->context();
        $this->grant($customer, ['withdrawal.request', 'withdrawal.view']);
        $this->credit($customer, 120_000);
        $approver = User::factory()->create();
        $this->grant($approver, ['withdrawal.approve', 'withdrawal.view', 'user.view.all']);
        $payer = $this->payerFor($area);
        $service = app(WithdrawalService::class);
        $withdrawal = $service->request($customer, $this->requestPayload($area) + ['amount' => 35_000], 'w6-payment-request-0001');
        $withdrawal = $service->approve($approver, $withdrawal, true);
        $withdrawal = $service->assignPayer($approver, $withdrawal, $payer);
        Event::fake([NotificationRequested::class]);
        $customerNumber = (string) $customer->customerProfile?->customer_number;

        $proof = UploadedFile::fake()->image('bukti-pencairan.png');
        $paid = $service->pay($payer, $withdrawal, 'kartu_nasabah', $customerNumber, $proof, 'w6-payment-key-0001');
        $retry = $service->pay($payer, $withdrawal, 'kartu_nasabah', $customerNumber, UploadedFile::fake()->image('retry.png'), 'w6-payment-key-0001');

        self::assertSame($paid->id, $retry->id);
        self::assertSame(WithdrawalStatus::Paid, $paid->status);
        self::assertSame(BalanceHold::STATUS_CONVERTED, $paid->balanceHold()->firstOrFail()->status);
        self::assertSame(1, LedgerEntry::query()->where('source_type', WithdrawalRequest::class)->where('direction', LedgerEntry::DIRECTION_OUT)->count());
        self::assertSame(85_000, $customer->ledgerAccount()->firstOrFail()->fresh()->availableBalance());
        self::assertSame('private', $paid->proofMedia()->firstOrFail()->getRawOriginal('visibility'));
        Storage::disk('media_private')->assertExists($paid->proofMedia->path);
        Event::assertDispatchedTimes(NotificationRequested::class, 1);
    }

    public function test_terminal_payment_with_a_missing_idempotency_key_does_not_swallow_validation(): void
    {
        Storage::fake('media_private');
        [$customer, $area] = $this->context();
        $this->grant($customer, ['withdrawal.request', 'withdrawal.view']);
        $this->credit($customer, 60_000);
        $approver = User::factory()->create();
        $this->grant($approver, ['withdrawal.approve', 'withdrawal.view', 'user.view.all']);
        $payer = $this->payerFor($area);
        $service = app(WithdrawalService::class);
        $withdrawal = $service->assignPayer($approver, $service->approve($approver, $service->request($customer, $this->requestPayload($area) + ['amount' => 20_000], 'w6-reconcile-request-0001'), true), $payer);
        $reference = (string) $customer->customerProfile?->customer_number;
        $service->pay($payer, $withdrawal, 'nomor_nasabah', $reference, UploadedFile::fake()->image('committed.png'), 'w6-reconcile-original-0001');

        Livewire::actingAs($payer)
            ->test(WithdrawalPayments::class)
            ->set('selectedWithdrawalId', $withdrawal->id)
            ->set('recipientVerification', 'nomor_nasabah')
            ->set('recipientReference', $reference)
            ->set('proof', UploadedFile::fake()->image('different.png'))
            ->set('idempotencyKey', 'w6-reconcile-missing-0001')
            ->set('showPaymentReview', true)
            ->call('pay')
            ->assertHasErrors(['status']);
    }

    public function test_lane_c_payment_normalizes_one_photo_proof_to_jpeg_under_one_megabyte(): void
    {
        Storage::fake('media_private');
        [$customer, $area] = $this->context();
        $this->grant($customer, ['withdrawal.request', 'withdrawal.view']);
        $this->credit($customer, 60_000);
        $approver = User::factory()->create();
        $this->grant($approver, ['withdrawal.approve', 'withdrawal.view', 'user.view.all']);
        $payer = $this->payerFor($area);
        $service = app(WithdrawalService::class);
        $withdrawal = $service->assignPayer($approver, $service->approve($approver, $service->request($customer, $this->requestPayload($area) + ['amount' => 20_000], 'w6-photo-request-0001'), true), $payer);

        $paid = $service->pay($payer, $withdrawal, 'kartu_nasabah', (string) $customer->customerProfile?->customer_number, UploadedFile::fake()->image('payment.png', 2400, 1800), 'w6-photo-payment-0001');
        $media = $paid->proofMedia()->firstOrFail();

        self::assertSame('image/jpeg', $media->mime_type);
        self::assertLessThanOrEqual(1 * 1024 * 1024, $media->size);
        self::assertSame('jpg', pathinfo($media->path, PATHINFO_EXTENSION));
    }

    public function test_lane_c_payment_is_denied_before_assignment_invalid_recipient_and_out_of_scope_proof(): void
    {
        Storage::fake('media_private');
        [$customer, $area] = $this->context();
        $this->grant($customer, ['withdrawal.request', 'withdrawal.view']);
        $this->credit($customer, 50_000);
        $approver = User::factory()->create();
        $this->grant($approver, ['withdrawal.approve', 'withdrawal.view', 'user.view.all']);
        $payer = $this->payerFor($area);
        $service = app(WithdrawalService::class);
        $withdrawal = $service->approve($approver, $service->request($customer, $this->requestPayload($area) + ['amount' => 20_000], 'w6-invalid-payment-request-0001'), true);

        $this->grant($payer, ['withdrawal.pay', 'withdrawal.view']);
        try {
            $service->pay($payer, $withdrawal, 'kartu_nasabah', 'CST-W6-0002', UploadedFile::fake()->image('proof.png'), 'w6-invalid-payment-key-0001');
            self::fail('An unassigned payer must not pay.');
        } catch (AuthorizationException) {
            self::assertSame(WithdrawalStatus::Approved, $withdrawal->fresh()->status);
        }

        $withdrawal = $service->assignPayer($approver, $withdrawal, $payer);
        $this->expectException(ValidationException::class);
        $service->pay($payer, $withdrawal, 'invalid', 'CST-W6-0002', UploadedFile::fake()->image('proof.png'), 'w6-invalid-recipient-key-0001');
    }

    public function test_lane_c_payment_rolls_back_private_proof_when_hold_conversion_fails(): void
    {
        Storage::fake('media_private');
        [$customer, $area] = $this->context();
        $this->grant($customer, ['withdrawal.request', 'withdrawal.view']);
        $this->credit($customer, 60_000);
        $approver = User::factory()->create();
        $this->grant($approver, ['withdrawal.approve', 'withdrawal.view', 'user.view.all']);
        $payer = $this->payerFor($area);
        $service = app(WithdrawalService::class);
        $withdrawal = $service->assignPayer($approver, $service->approve($approver, $service->request($customer, $this->requestPayload($area) + ['amount' => 20_000], 'w6-payment-rollback-request-0001'), true), $payer);
        app(LedgerService::class)->releaseHold($withdrawal->balanceHold()->firstOrFail());
        $customerNumber = (string) $customer->customerProfile?->customer_number;

        $this->expectException(ValidationException::class);
        try {
            $service->pay($payer, $withdrawal, 'kartu_nasabah', $customerNumber, UploadedFile::fake()->image('rollback-proof.png'), 'w6-payment-rollback-key-0001');
        } finally {
            self::assertSame(WithdrawalStatus::ReadyForPickup, $withdrawal->fresh()->status);
            self::assertSame(0, LedgerEntry::query()->where('source_type', WithdrawalRequest::class)->count());
            self::assertSame(0, $withdrawal->fresh()->proofMedia()->count());
            self::assertSame(0, AuditLog::query()->where('action', 'withdrawal.paid')->count());
            Storage::disk('media_private')->assertDirectoryEmpty('/');
        }
    }

    public function test_lane_d_citizen_cannot_cancel_after_approval(): void
    {
        [$customer, $area] = $this->context();
        $this->grant($customer, ['withdrawal.request', 'withdrawal.view', 'withdrawal.cancel']);
        $this->credit($customer, 80_000);
        $approver = User::factory()->create();
        $this->grant($approver, ['withdrawal.approve', 'withdrawal.view', 'user.view.all']);
        $service = app(WithdrawalService::class);
        $approved = $service->approve($approver, $service->request($customer, $this->requestPayload($area) + ['amount' => 20_000], 'w6-cancel-after-approve-0001'), true);

        $this->expectException(AuthorizationException::class);
        $service->cancel($customer, $approved, 'Warga mencoba membatalkan setelah persetujuan.');
    }

    public function test_citizen_withdrawal_page_only_shows_the_cancel_button_while_pending_verification(): void
    {
        [$customer, $area] = $this->context();
        $this->grant($customer, ['withdrawal.request', 'withdrawal.view', 'withdrawal.cancel']);
        $this->credit($customer, 80_000);
        $approver = User::factory()->create();
        $this->grant($approver, ['withdrawal.approve', 'withdrawal.view', 'user.view.all']);
        $service = app(WithdrawalService::class);
        $pending = $service->request($customer, $this->requestPayload($area) + ['amount' => 20_000], 'w6-cancel-button-pending-0001');
        $approved = $service->approve($approver, $pending, true);

        Livewire::actingAs($customer)
            ->test(WithdrawalShow::class, ['withdrawal' => $approved])
            ->assertDontSee('Batalkan pengajuan');

        $secondPending = $service->request($customer, $this->requestPayload($area) + ['amount' => 20_000], 'w6-cancel-button-second-0001');

        Livewire::actingAs($customer)
            ->test(WithdrawalShow::class, ['withdrawal' => $secondPending])
            ->assertSee('Batalkan pengajuan');
    }

    public function test_lane_d_cancel_and_expiry_release_hold_once_and_never_create_balance_out(): void
    {
        [$customer, $area] = $this->context();
        $this->grant($customer, ['withdrawal.request', 'withdrawal.view', 'withdrawal.cancel']);
        $this->credit($customer, 100_000);
        $service = app(WithdrawalService::class);
        $cancelled = $service->request($customer, $this->requestPayload($area) + ['amount' => 20_000], 'w6-cancel-key-0001');
        $cancelled = $service->cancel($customer, $cancelled, 'Warga memilih untuk menunda pengambilan.');
        $cancelledAgain = $service->cancel($customer, $cancelled, 'Percobaan pembatalan kedua.');
        self::assertSame(WithdrawalStatus::Cancelled, $cancelledAgain->status);
        self::assertSame(BalanceHold::STATUS_RELEASED, $cancelledAgain->balanceHold()->firstOrFail()->status);
        self::assertSame(0, LedgerEntry::query()->where('source_type', WithdrawalRequest::class)->count());

        $second = $service->request($customer, $this->requestPayload($area) + ['amount' => 20_000], 'w6-expiry-key-0001');
        $second->forceFill(['expires_at' => now()->subDay()])->save();
        $expired = $service->expire($second);
        $expiredAgain = $service->expire($expired);
        self::assertSame(WithdrawalStatus::Expired, $expiredAgain->status);
        self::assertSame(BalanceHold::STATUS_RELEASED, $expiredAgain->balanceHold()->firstOrFail()->status);
        self::assertSame(100_000, $customer->ledgerAccount()->firstOrFail()->fresh()->availableBalance());
        self::assertSame(0, LedgerEntry::query()->where('source_type', WithdrawalRequest::class)->count());
    }

    public function test_record_scope_and_private_proof_routes_fail_closed_for_other_users(): void
    {
        Storage::fake('media_private');
        [$customer, $area] = $this->context();
        $this->grant($customer, ['withdrawal.request', 'withdrawal.view']);
        $this->credit($customer, 70_000);
        $approver = User::factory()->create();
        $this->grant($approver, ['withdrawal.approve', 'withdrawal.view', 'user.view.all']);
        $payer = $this->payerFor($area);
        $service = app(WithdrawalService::class);
        $customerNumber = (string) $customer->customerProfile?->customer_number;
        $paid = $service->pay($payer, $service->assignPayer($approver, $service->approve($approver, $service->request($customer, $this->requestPayload($area) + ['amount' => 25_000], 'w6-idor-request-0001'), true), $payer), 'nomor_nasabah', $customerNumber, UploadedFile::fake()->image('idor-proof.png'), 'w6-idor-payment-0001');
        $other = User::factory()->create();
        $this->grant($other, ['withdrawal.view']);
        $this->actingAs($other)->get(route('citizen.withdrawal.show', $paid))->assertNotFound();
        $this->actingAs($other)->get(route('withdrawal.proof', $paid->proofMedia))->assertNotFound();
        $this->actingAs($customer)->get(route('citizen.withdrawal.show', $paid))->assertOk()->assertSee($paid->request_number);
        $proofUrl = route('withdrawal.proof', $paid->proofMedia);
        $this->actingAs($customer)->get(route('citizen.withdrawal.receipt', $paid))
            ->assertOk()
            ->assertSee('Pencairan berhasil')
            ->assertSee($paid->request_number)
            ->assertSee('Rp25.000')
            ->assertSee('Berhasil')
            ->assertSeeHtml('<img src="'.$proofUrl.'" alt="Bukti pembayaran pencairan '.$paid->request_number.'" class="max-h-96 w-full object-contain" />')
            ->assertSeeHtml('href="'.$proofUrl.'"')
            ->assertDontSee('storage/')
            ->assertDontSee('Nomor bukti');
        $this->actingAs($customer)->get(route('withdrawal.proof', $paid->proofMedia))->assertOk();
    }

    public function test_withdrawal_proof_images_render_inline_while_pdfs_are_downloads(): void
    {
        Storage::fake('media_private');
        [$customer, $area] = $this->context();
        $this->grant($customer, ['withdrawal.request', 'withdrawal.view']);
        $this->credit($customer, 70_000);
        $approver = User::factory()->create();
        $this->grant($approver, ['withdrawal.approve', 'withdrawal.view', 'user.view.all']);
        $payer = $this->payerFor($area);
        $service = app(WithdrawalService::class);
        $customerNumber = (string) $customer->customerProfile?->customer_number;
        $paid = $service->pay($payer, $service->assignPayer($approver, $service->approve($approver, $service->request($customer, $this->requestPayload($area) + ['amount' => 25_000], 'w6-proof-disposition-request-0001'), true), $payer), 'nomor_nasabah', $customerNumber, UploadedFile::fake()->image('proof.png'), 'w6-proof-disposition-payment-0001');

        $imageResponse = $this->actingAs($customer)->get(route('withdrawal.proof', $paid->proofMedia));
        $imageResponse->assertOk();
        self::assertStringStartsWith('inline;', (string) $imageResponse->headers->get('Content-Disposition'));

        $paid->proofMedia->update(['mime_type' => 'application/pdf', 'original_name' => 'proof.pdf']);
        $proofMedia = $paid->proofMedia->fresh();
        $pdfResponse = $this->actingAs($customer)->get(route('withdrawal.proof', $proofMedia));
        $pdfResponse->assertOk();
        self::assertStringStartsWith('attachment;', (string) $pdfResponse->headers->get('Content-Disposition'));

        $this->actingAs($customer)->get(route('citizen.withdrawal.receipt', $paid))
            ->assertOk()
            ->assertDontSee('<img', false)
            ->assertSeeHtml('href="'.route('withdrawal.proof', $proofMedia).'"');
    }

    public function test_paid_withdrawal_detail_exposes_the_receipt_action(): void
    {
        [$customer] = $this->context();
        $this->grant($customer, ['withdrawal.view']);
        $paid = WithdrawalRequest::factory()->for($customer, 'customer')->create([
            'requested_by_id' => $customer->id,
            'status' => WithdrawalStatus::Paid,
        ]);

        Livewire::actingAs($customer)
            ->test(WithdrawalShow::class, ['withdrawal' => $paid])
            ->assertSee('Buka Bukti Pencairan')
            ->assertSeeHtml('href="'.route('citizen.withdrawal.receipt', $paid).'"');
    }

    public function test_multi_area_bendahara_assignments_allow_snapshot_area_a_or_b_and_reject_area_c(): void
    {
        [$customer, $areaA] = $this->context();
        $this->grant($customer, ['withdrawal.request', 'withdrawal.view']);
        $this->credit($customer, 100_000);
        $manager = User::factory()->create();
        $this->grant($manager, ['region.manage']);
        $rt = $customer->customerProfile()->firstOrFail()->rt()->firstOrFail();
        $regions = app(ManageRegions::class);
        $areaB = $regions->createServiceArea($manager, 'Area W6 B '.$customer->id, [$rt]);
        $areaC = $regions->createServiceArea($manager, 'Area W6 C '.$customer->id, [$rt]);
        $approver = User::factory()->create();
        $this->grant($approver, ['withdrawal.approve', 'withdrawal.view', 'user.view.all']);
        $payer = $this->payerFor($areaA);
        StaffServiceArea::query()->create(['staff_profile_user_id' => $payer->id, 'service_area_id' => $areaB->id, 'active_from' => today(), 'active_to' => null]);
        $service = app(WithdrawalService::class);

        try {
            $service->request($customer, Arr::except($this->requestPayload($areaA), 'service_area_id') + ['amount' => 20_000], 'w6-snapshot-area-ambiguous-0001');
            self::fail('A customer in multiple active areas must select an area explicitly.');
        } catch (ValidationException) {
            self::assertDatabaseCount('withdrawal_requests', 0);
            self::assertDatabaseCount('balance_holds', 0);
        }

        $areaAWithdrawal = $service->approve($approver, $service->request($customer, $this->requestPayload($areaA) + ['amount' => 20_000], 'w6-snapshot-area-a-0001'), true);
        $areaBWithdrawal = $service->approve($approver, $service->request($customer, $this->requestPayload($areaB) + ['amount' => 20_000], 'w6-snapshot-area-b-0001'), true);
        $areaCWithdrawal = $service->approve($approver, $service->request($customer, $this->requestPayload($areaC) + ['amount' => 20_000], 'w6-snapshot-area-c-0001'), true);

        self::assertSame($areaA->id, $areaAWithdrawal->service_area_id);
        self::assertSame($rt->id, $areaAWithdrawal->rt_id);
        self::assertSame(WithdrawalStatus::ReadyForPickup, $service->assignPayer($approver, $areaAWithdrawal, $payer)->status);
        self::assertSame(WithdrawalStatus::ReadyForPickup, $service->assignPayer($approver, $areaBWithdrawal, $payer)->status);
        $this->expectException(ValidationException::class);
        $service->assignPayer($approver, $areaCWithdrawal, $payer);
    }

    public function test_post_assignment_expiry_removal_or_inactive_snapshot_area_blocks_payment_without_financial_side_effects(): void
    {
        Storage::fake('media_private');
        [$customer, $area] = $this->context();
        $this->grant($customer, ['withdrawal.request', 'withdrawal.view']);
        $this->credit($customer, 100_000);
        $manager = User::factory()->create();
        $this->grant($manager, ['region.manage']);
        $approver = User::factory()->create();
        $this->grant($approver, ['withdrawal.approve', 'withdrawal.view', 'user.view.all']);
        $payer = $this->payerFor($area);
        $service = app(WithdrawalService::class);

        foreach (['expired', 'removed', 'inactive'] as $index => $case) {
            $withdrawal = $service->assignPayer($approver, $service->approve($approver, $service->request($customer, $this->requestPayload($area) + ['amount' => 20_000], 'w6-post-assignment-'.$case.'-0001'), true), $payer);
            match ($case) {
                'expired' => StaffServiceArea::query()->where('staff_profile_user_id', $payer->id)->where('service_area_id', $area->id)->update(['active_from' => today()->subDays(2), 'active_to' => today()->subDay()]),
                'removed' => StaffServiceArea::query()->where('staff_profile_user_id', $payer->id)->where('service_area_id', $area->id)->delete(),
                'inactive' => app(ManageRegions::class)->deactivate($manager, $area),
            };

            try {
                $service->pay($payer, $withdrawal, 'nomor_nasabah', (string) $customer->customerProfile?->customer_number, UploadedFile::fake()->image($case.'.png'), 'w6-post-assignment-pay-'.$index.'001');
                self::fail('A payer without a current snapshot-area assignment must not pay.');
            } catch (AuthorizationException) {
                self::assertSame(WithdrawalStatus::ReadyForPickup, $withdrawal->fresh()->status);
                self::assertNull($withdrawal->fresh()->proof_media_id);
                self::assertNull($withdrawal->fresh()->receipt_ledger_entry_id);
                self::assertSame(0, LedgerEntry::query()->where('source_type', WithdrawalRequest::class)->where('source_id', $withdrawal->id)->count());
            }

            if ($case === 'expired') {
                StaffServiceArea::query()->where('staff_profile_user_id', $payer->id)->where('service_area_id', $area->id)->update(['active_from' => today(), 'active_to' => null]);
            }
            if ($case === 'removed') {
                StaffServiceArea::query()->create(['staff_profile_user_id' => $payer->id, 'service_area_id' => $area->id, 'active_from' => today(), 'active_to' => null]);
            }
            if ($case === 'inactive') {
                app(ManageRegions::class)->activate($manager, $area);
            }
        }

        Storage::disk('media_private')->assertDirectoryEmpty('/');
    }

    /** @return array{User, ServiceArea} */
    private function context(): array
    {
        $customer = User::factory()->create(['status' => UserStatus::Active]);
        $manager = User::factory()->create();
        $this->grant($manager, ['region.manage']);
        $regions = app(ManageRegions::class);
        $dusun = $regions->createDusun($manager, 'W6-DS-'.$customer->id, 'Dusun W6');
        $rw = $regions->createRw($manager, $dusun, 'W6-RW-'.$customer->id, 'RW W6');
        $rt = $regions->createRt($manager, $rw, 'W6-RT-'.$customer->id, 'RT W6');
        $customer->customerProfile()->create(['customer_number' => 'CST-'.str_pad((string) $customer->id, 8, '0', STR_PAD_LEFT), 'rt_id' => $rt->id, 'address' => 'Alamat warga W6']);
        $area = $regions->createServiceArea($manager, 'Area W6 '.$customer->id, [$rt]);

        return [$customer, $area];
    }

    private function credit(User $customer, int $amount): void
    {
        $account = LedgerAccount::query()->create(['user_id' => $customer->id, 'status' => 'aktif', 'currency' => 'IDR']);
        LedgerEntry::query()->create(['entry_number' => 'LED-W6-'.$customer->id, 'ledger_account_id' => $account->id, 'direction' => LedgerEntry::DIRECTION_IN, 'kind' => 'deposit', 'amount' => $amount, 'source_type' => User::class, 'source_id' => $customer->id, 'source_key' => 'w6-credit-'.$customer->id, 'effective_at' => now(), 'balance_after' => $amount]);
    }

    /** @return array<string, mixed> */
    private function requestPayload(ServiceArea $area): array
    {
        return ['pickup_location' => 'Balai warga W6', 'pickup_date' => today()->addDay()->toDateString(), 'service_area_id' => $area->id];
    }

    private function payerFor(ServiceArea $area): User
    {
        $payer = User::factory()->create(['status' => UserStatus::Active]);
        $role = Role::query()->firstOrCreate(['name' => 'bendahara'], ['description' => 'Bendahara']);
        foreach (['withdrawal.pay', 'withdrawal.view'] as $permissionName) {
            $permission = Permission::query()->firstOrCreate(['name' => $permissionName], ['description' => $permissionName]);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $payer->roles()->attach($role);
        StaffProfile::query()->create(['user_id' => $payer->id, 'staff_number' => 'W6-PAYER-'.$payer->id, 'service_area_id' => $area->id, 'active_from' => today(), 'active_to' => null]);

        return $payer;
    }

    /** @param list<string> $permissions */
    private function grant(User $user, array $permissions): void
    {
        $role = Role::query()->create(['name' => 'w6-role-'.$user->id.'-'.str()->random(5), 'description' => 'W6']);
        foreach ($permissions as $permissionName) {
            $permission = Permission::query()->firstOrCreate(['name' => $permissionName], ['description' => $permissionName]);
            $role->permissions()->attach($permission);
        }
        $user->roles()->attach($role);
    }
}
