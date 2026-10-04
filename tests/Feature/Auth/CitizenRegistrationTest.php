<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Actions\Auth\RegisterCitizen;
use App\Domain\CustomersRegions\Models\Dusun;
use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\CustomersRegions\Models\Rw;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\CustomerProfile;
use App\Livewire\Auth\RegisterCitizenForm;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class CitizenRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_registration_persists_pending_user_profile_and_matching_append_only_consent_without_authentication(): void
    {
        $rt = $this->activeRt();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-31 10:15:00', 'Asia/Jakarta'));

        Livewire::test(RegisterCitizenForm::class)
            ->set('name', '  Siti   Aminah  ')
            ->set('phone', '0812-3456-7890')
            ->set('password', 'rahasia-yang-kuat')
            ->set('password_confirmation', 'rahasia-yang-kuat')
            ->set('rt_id', (string) $rt->id)
            ->set('address', ' Jalan Melati Nomor 10 ')
            ->set('terms_accepted', true)
            ->call('register')
            ->assertHasNoErrors()
            ->assertSet('registered', true);

        $user = User::query()->sole();

        self::assertSame('Siti Aminah', $user->name);
        self::assertSame('6281234567890', $user->phone);
        self::assertSame(UserStatus::PendingVerification, $user->status);
        self::assertNull($user->verified_at);
        self::assertNull($user->verified_by);
        self::assertNull($user->last_login_at);
        self::assertSame('v1.0', $user->terms_version);
        self::assertInstanceOf(CarbonImmutable::class, $user->terms_accepted_at);
        self::assertSame(now()->format('Y-m-d H:i:s'), $user->terms_accepted_at->format('Y-m-d H:i:s'));
        self::assertGuest();
        $this->assertDatabaseHas('customer_profiles', ['user_id' => $user->id, 'rt_id' => $rt->id, 'address' => 'Jalan Melati Nomor 10']);
        $this->assertDatabaseHas('terms_acceptance_histories', ['user_id' => $user->id, 'accepted_version' => 'v1.0', 'accepted_at' => now()]);
    }

    public function test_registration_validates_every_required_field_and_has_no_persistence_side_effect(): void
    {
        Livewire::test(RegisterCitizenForm::class)
            ->call('register')
            ->assertHasErrors([
                'name' => 'required',
                'phone' => 'required',
                'password' => 'required',
                'rt_id' => 'required',
                'address' => 'required',
                'terms_accepted' => 'accepted',
            ])
            ->assertDispatched('registration-invalid')
            ->assertSeeHtml('id="registration-errors"')
            ->assertSeeHtml('href="#name"')
            ->assertSeeHtml('href="#phone"')
            ->assertSeeHtml('href="#password"')
            ->assertSeeHtml('href="#rt_id"')
            ->assertSeeHtml('href="#address"')
            ->assertSeeHtml('href="#terms_accepted"')
            ->assertSeeHtml('tabindex="-1"')
            ->assertSeeHtml('x-on:registration-invalid.window="$nextTick(() => $el.focus())"')
            ->assertSeeHtml('aria-describedby="registration-errors"');

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('terms_acceptance_histories', 0);
    }

    public function test_registration_rejects_invalid_name_phone_password_confirmation_address_and_inactive_hierarchy(): void
    {
        $inactiveRt = $this->activeRt(['rt_active' => false]);

        Livewire::test(RegisterCitizenForm::class)
            ->set('name', " \t ")
            ->set('phone', '123')
            ->set('password', 'pendek')
            ->set('password_confirmation', 'lain')
            ->set('rt_id', (string) $inactiveRt->id)
            ->set('address', 'x')
            ->set('terms_accepted', true)
            ->call('register')
            ->assertHasErrors([
                'name',
                'phone',
                'password',
                'address',
            ]);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('terms_acceptance_histories', 0);

        Livewire::test(RegisterCitizenForm::class)
            ->set('name', 'Siti Aminah')
            ->set('phone', '0812 3456 7890')
            ->set('password', 'rahasia-yang-kuat')
            ->set('password_confirmation', 'rahasia-yang-kuat')
            ->set('rt_id', (string) $inactiveRt->id)
            ->set('address', 'Jalan Melati Nomor 10')
            ->set('terms_accepted', true)
            ->call('register')
            ->assertHasErrors(['rt_id']);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('terms_acceptance_histories', 0);
    }

    public function test_registration_rejects_separator_only_phone_input(): void
    {
        $rt = $this->activeRt();

        Livewire::test(RegisterCitizenForm::class)
            ->set('name', 'Siti Aminah')
            ->set('phone', '()+-. ')
            ->set('password', 'rahasia-yang-kuat')
            ->set('password_confirmation', 'rahasia-yang-kuat')
            ->set('rt_id', (string) $rt->id)
            ->set('address', 'Jalan Melati Nomor 10')
            ->set('terms_accepted', true)
            ->call('register')
            ->assertHasErrors(['phone']);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('terms_acceptance_histories', 0);
    }

    public function test_registration_rejects_phone_already_used_by_an_active_account_without_creating_history(): void
    {
        $rt = $this->activeRt();
        User::factory()->create(['phone' => '6281234567890', 'status' => UserStatus::Active]);

        Livewire::test(RegisterCitizenForm::class)
            ->set('name', 'Siti Aminah')
            ->set('phone', '0812 3456 7890')
            ->set('password', 'rahasia-yang-kuat')
            ->set('password_confirmation', 'rahasia-yang-kuat')
            ->set('rt_id', (string) $rt->id)
            ->set('address', 'Jalan Melati Nomor 10')
            ->set('terms_accepted', true)
            ->call('register')
            ->assertHasErrors(['phone' => 'unique']);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('terms_acceptance_histories', 0);
        self::assertGuest();
    }

    public function test_registration_rejects_phone_reserved_by_a_soft_deleted_account(): void
    {
        $rt = $this->activeRt();
        User::factory()->create(['phone' => '6281234567890'])->delete();

        Livewire::test(RegisterCitizenForm::class)
            ->set('name', 'Siti Aminah')
            ->set('phone', '0812 3456 7890')
            ->set('password', 'rahasia-yang-kuat')
            ->set('password_confirmation', 'rahasia-yang-kuat')
            ->set('rt_id', (string) $rt->id)
            ->set('address', 'Jalan Melati Nomor 10')
            ->set('terms_accepted', true)
            ->call('register')
            ->assertHasErrors(['phone' => 'unique']);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('customer_profiles', 0);
        $this->assertDatabaseCount('terms_acceptance_histories', 0);
    }

    public function test_registration_rolls_back_user_when_a_post_user_write_fails(): void
    {
        $rt = $this->activeRt();
        CustomerProfile::creating(static function (): void {
            throw new \RuntimeException('Forced profile write failure.');
        });

        try {
            app(RegisterCitizen::class)->handle([
                'name' => 'Siti Aminah',
                'phone' => '6281234567890',
                'password' => 'rahasia-yang-kuat',
                'rt_id' => $rt->id,
                'address' => 'Jalan Melati Nomor 10',
                'terms_accepted' => true,
            ]);
            self::fail('The forced post-user write failure was not raised.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Forced profile write failure.', $exception->getMessage());
        }

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('customer_profiles', 0);
        $this->assertDatabaseCount('terms_acceptance_histories', 0);
    }

    public function test_current_consent_version_and_time_are_server_owned_not_client_owned(): void
    {
        config()->set('app.terms_version', 'v1.0');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-31 10:15:00', 'Asia/Jakarta'));

        $user = app(RegisterCitizen::class)->handle([
            'name' => 'Siti Aminah',
            'phone' => '6281234567890',
            'password' => 'rahasia-yang-kuat',
            'rt_id' => $this->activeRt()->id,
            'address' => 'Jalan Melati Nomor 10',
            'terms_accepted' => true,
        ]);

        $history = $user->termsAcceptanceHistories()->sole();

        self::assertSame('v1.0', $history->accepted_version);
        self::assertInstanceOf(CarbonImmutable::class, $history->accepted_at);
        self::assertSame(now()->format('Y-m-d H:i:s'), $history->accepted_at->format('Y-m-d H:i:s'));
        self::assertSame($user->terms_version, $history->accepted_version);
        self::assertInstanceOf(CarbonImmutable::class, $user->terms_accepted_at);
        self::assertSame($user->terms_accepted_at->format('Y-m-d H:i:s'), $history->accepted_at->format('Y-m-d H:i:s'));
    }

    public function test_public_registration_and_terms_routes_render_accessibility_contracts(): void
    {
        $this->activeRt();

        $this->get(route('terms-and-privacy'))
            ->assertOk()
            ->assertSee('Ketentuan Operasional v1.0 dan Kebijakan Privasi Ringkas v1.0')
            ->assertSee('<main id="konten-utama"', escape: false);

        $response = $this->get(route('register'))
            ->assertOk()
            ->assertSee('id="registration-title"', escape: false)
            ->assertSee('name="terms_accepted"', escape: false)
            ->assertSee('type="checkbox"', escape: false)
            ->assertSee('href="'.route('terms-and-privacy').'"', escape: false)
            ->assertSee('target="_blank" rel="noopener noreferrer"', escape: false)
            ->assertSee('aria-invalid="false"', escape: false)
            ->assertSee('autocomplete="new-password"', escape: false)
            ->assertDontSee('aria-describedby="registration-errors"', escape: false);

        self::assertMatchesRegularExpression(
            '/<h1\\b(?=[^>]*\\bid="registration-title")(?=[^>]*\\blg:text-h1\\b)[^>]*>/s',
            $response->getContent(),
        );

        $this->actingAs(User::factory()->create())
            ->get(route('register'))
            ->assertRedirect(route('home'));
    }

    public function test_registration_route_is_rate_limited_per_ip(): void
    {
        config()->set('app.registration_max_attempts_per_minute', 1);

        $this->get(route('register'))->assertOk();
        $this->get(route('register'))->assertStatus(429);
    }

    /** @param array{dusun_active?: bool, rw_active?: bool, rt_active?: bool} $states */
    private function activeRt(array $states = []): Rt
    {
        $dusun = Dusun::query()->create([
            'code' => 'DS-'.fake()->unique()->numerify('####'),
            'name' => 'Dusun Mekar',
            'is_active' => $states['dusun_active'] ?? true,
        ]);
        $rw = Rw::query()->create([
            'dusun_id' => $dusun->id,
            'code' => 'RW-'.fake()->unique()->numerify('##'),
            'name' => 'RW Mekar',
            'is_active' => $states['rw_active'] ?? true,
        ]);

        return Rt::query()->create([
            'rw_id' => $rw->id,
            'code' => 'RT-'.fake()->unique()->numerify('##'),
            'name' => 'RT Mekar',
            'is_active' => $states['rt_active'] ?? true,
        ]);
    }
}
