<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Authorization\PermissionChecker;
use App\Domain\AuditReconciliation\Services\AuditLogger;
use App\Domain\Identity\Models\CustomerProfile;
use App\Models\User;
use App\Support\Auth\PhoneNumber;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final readonly class UpdateSelfServiceProfile
{
    public function __construct(
        private PermissionChecker $permissions,
        private AuditLogger $auditLogger,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(User $actor, array $data): User
    {
        $this->assertAllowedKeys($data);
        $normalized = $this->normalize($data);

        return DB::transaction(function () use ($actor, $normalized): User {
            /** @var User $lockedActor */
            $lockedActor = User::query()->lockForUpdate()->findOrFail($actor->getKey());
            Gate::forUser($lockedActor)->authorize('update', $lockedActor);

            $userAttributes = $this->validatedUserAttributes($lockedActor, [
                'name' => $normalized['name'],
                'phone' => $normalized['phone'],
            ]);
            $profile = null;
            $profileAttributes = [];

            if (array_key_exists('address', $normalized)) {
                if (! $this->permissions->allows($lockedActor, 'customer.update')) {
                    throw new AuthorizationException('Anda tidak memiliki akses mengubah alamat nasabah.');
                }

                $profile = CustomerProfile::query()
                    ->whereKey($lockedActor->getKey())
                    ->lockForUpdate()
                    ->first();

                if (! $profile instanceof CustomerProfile) {
                    throw ValidationException::withMessages(['address' => 'Profil nasabah tidak ditemukan.']);
                }

                $profileAttributes = $this->validatedAddress($normalized['address']);
            }

            $lockedActor->forceFill($userAttributes)->save();
            if ($profile instanceof CustomerProfile) {
                $profile->forceFill($profileAttributes)->save();
            }

            $fields = array_keys($userAttributes);
            if ($profile instanceof CustomerProfile) {
                $fields[] = 'address';
            }
            $this->auditLogger->record(
                $lockedActor,
                'identity.profile.updated.self_service',
                $lockedActor,
                ['fields' => $fields],
                ['fields' => $fields],
                $this->correlationId(),
            );

            return $lockedActor->fresh('customerProfile');
        });
    }

    /** @param array<string, mixed> $data */
    private function assertAllowedKeys(array $data): void
    {
        foreach (array_keys($data) as $key) {
            if (! in_array($key, ['name', 'phone', 'address'], true)) {
                throw ValidationException::withMessages(['profile' => 'Data profil tidak valid.']);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{name: string, phone: string, address?: string}
     */
    private function normalize(array $data): array
    {
        $name = preg_replace('/\s+/u', ' ', trim($this->stringValue($data['name'] ?? null))) ?? trim($this->stringValue($data['name'] ?? null));
        $normalized = [
            'name' => $name,
            'phone' => PhoneNumber::normalize($this->stringValue($data['phone'] ?? null)),
        ];

        if (array_key_exists('address', $data)) {
            $normalized['address'] = trim($this->stringValue($data['address']));
        }

        return $normalized;
    }

    /**
     * @param  array{name: string, phone: string}  $data
     * @return array{name: string, phone: string}
     */
    private function validatedUserAttributes(User $actor, array $data): array
    {
        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'min:2', 'max:120', 'regex:/\S/u', 'not_regex:/[\p{Cc}]/u'],
            'phone' => ['required', 'string', 'regex:/^62[0-9]{8,16}$/', Rule::unique('users', 'phone')->ignore($actor->getKey())],
        ])->validate();

        return [
            'name' => (string) $validated['name'],
            'phone' => (string) $validated['phone'],
        ];
    }

    /** @return array{address: string} */
    private function validatedAddress(string $address): array
    {
        $validated = Validator::make(['address' => $address], [
            'address' => ['required', 'string', 'min:5', 'max:500', 'regex:/\S/u', 'not_regex:/[\p{Cc}]/u'],
        ])->validate();

        return ['address' => (string) $validated['address']];
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : (is_scalar($value) ? (string) $value : '');
    }

    private function correlationId(): string
    {
        $value = request()->attributes->get('correlation_id');

        return is_string($value) && Str::isUuid($value) ? strtolower($value) : (string) Str::uuid();
    }
}
