<?php

declare(strict_types=1);

namespace App\Support\Seeding;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use RuntimeException;

/** Private, literal data only. No statements from the original SQL dump are executed. */
final readonly class MasterData
{
    public const COLUMNS = [
        'dusun' => ['id', 'code', 'name', 'is_active'],
        'rw' => ['id', 'dusun_id', 'code', 'name', 'is_active'],
        'rt' => ['id', 'rw_id', 'code', 'name', 'is_active'],
        'service_areas' => ['id', 'name', 'is_active'],
        'service_area_rt' => ['service_area_id', 'rt_id'],
        'users' => ['id', 'name', 'username', 'email', 'email_verified_at', 'password', 'phone', 'status', 'role', 'verified_at', 'rejection_reason', 'terms_version', 'terms_accepted_at', 'deleted_at', 'created_at', 'updated_at'],
        'customer_profiles' => ['user_id', 'customer_number', 'rt_id', 'address', 'joined_at', 'created_at', 'updated_at'],
        'staff_profiles' => ['user_id', 'staff_number', 'service_area_id', 'active_from', 'active_to', 'created_at', 'updated_at'],
        'staff_service_areas' => ['id', 'staff_profile_user_id', 'service_area_id', 'active_from', 'active_to', 'created_at', 'updated_at'],
        'waste_categories' => ['id', 'code', 'name', 'sort_order', 'is_active'],
        'waste_units' => ['id', 'code', 'name', 'symbol', 'classification', 'is_active', 'conversion_factor_to_kg'],
        'waste_conditions' => ['id', 'code', 'name', 'description', 'sort_order', 'is_active'],
        'waste_types' => ['id', 'waste_category_id', 'waste_unit_id', 'code', 'name', 'education_description', 'sort_order', 'is_plastic', 'is_active'],
        'waste_type_conditions' => ['waste_type_id', 'waste_condition_id'],
        'waste_prices' => ['id', 'waste_type_id', 'waste_condition_id', 'price', 'effective_from', 'effective_to', 'created_by', 'rounding_version', 'created_at', 'updated_at'],
    ];

    /** @param array<string, list<array<string, mixed>>> $tables */
    private function __construct(public array $tables) {}

    public static function read(string $path): self
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('Data utama tidak ditemukan. Siapkan JSON privat dari SQL melalui documents/extract-master-data.mjs; atur MASTER_SEED_PATH bila diperlukan.');
        }
        $contents = file_get_contents($path);
        $data = json_decode($contents === false ? '' : $contents, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($data) || ($data['schema_version'] ?? null) !== 1 || ! is_array($data['tables'] ?? null)
            || array_diff(array_keys($data['tables']), array_keys(self::COLUMNS)) !== []
            || array_diff(array_keys(self::COLUMNS), array_keys($data['tables'])) !== []) {
            throw new RuntimeException('Format/tabel data utama tidak sesuai.');
        }
        $tables = $data['tables'];
        foreach (self::COLUMNS as $table => $columns) {
            if (! is_array($tables[$table]) || ! array_is_list($tables[$table])) {
                throw new RuntimeException('Daftar baris data utama tidak sesuai.');
            }
            foreach ($tables[$table] as $row) {
                if (! is_array($row) || array_diff(array_keys($row), $columns) !== []
                    || count(array_filter($row, static fn (mixed $value): bool => ! is_scalar($value) && $value !== null)) > 0) {
                    throw new RuntimeException('Kolom data utama tidak diizinkan.');
                }
            }
        }
        $master = new self($tables);
        $master->validate();

        return $master;
    }

    private function validate(): void
    {
        $rules = [];
        foreach (self::COLUMNS as $table => $columns) {
            if (in_array('id', $columns, true)) {
                $rules[$table.'.*.id'] = ['required', 'integer', 'min:1', 'distinct'];
            }
            foreach (['code', 'name', 'is_active'] as $column) {
                if (in_array($column, $columns, true)) {
                    $rules[$table.'.*.'.$column] = $column === 'is_active'
                        ? ['required', 'boolean'] : ['required', 'string', 'max:255'];
                }
            }
            if (in_array('code', $columns, true)) {
                $rules[$table.'.*.code'][] = 'distinct';
            }
        }
        $rules += [
            'users' => ['required', 'array', 'min:1'],
            'users.*.username' => ['required', 'string', 'min:3', 'max:60', 'regex:/^[a-z0-9._-]+$/', 'distinct'],
            'users.*.role' => ['required', Rule::in(['warga', 'petugas', 'bendahara', 'admin'])],
            'users.*.status' => ['required', Rule::in(['aktif', 'nonaktif', 'ditolak', 'menunggu_verifikasi'])],
            'users.*.password' => ['required', 'string'],
            'users.*.email' => ['nullable', 'email', 'distinct'],
            'users.*.phone' => ['nullable', 'regex:/^62[0-9]{8,16}$/', 'distinct'],
            'customer_profiles.*.user_id' => ['required', 'integer', 'distinct'],
            'customer_profiles.*.customer_number' => ['required', 'regex:/^(?:CST|SH)-[0-9]+$/', 'distinct'],
            'customer_profiles.*.address' => ['required', 'string', 'min:5', 'max:500', 'not_regex:/[\p{Cc}]/u'],
            'staff_profiles.*.user_id' => ['required', 'integer', 'distinct'],
            'staff_profiles.*.staff_number' => ['required', 'string', 'max:40', 'distinct'],
            'staff_profiles.*.active_from' => ['nullable', 'date_format:Y-m-d'],
            'staff_profiles.*.active_to' => ['nullable', 'date_format:Y-m-d'],
            'staff_service_areas.*.active_from' => ['nullable', 'date_format:Y-m-d'],
            'staff_service_areas.*.active_to' => ['nullable', 'date_format:Y-m-d'],
            'waste_units.*.classification' => ['required', Rule::in(['weight', 'count'])],
            'waste_prices.*.price' => ['required', 'integer', 'min:0'],
            'waste_prices.*.effective_from' => ['required', 'date_format:Y-m-d H:i:s'],
            'waste_prices.*.effective_to' => ['nullable', 'date_format:Y-m-d H:i:s'],
            'waste_prices.*.rounding_version' => ['required', Rule::in(['half_up_v1'])],
        ];
        // Validate references before any reset or INSERT, even though the database is empty.
        foreach ([
            ['rw', 'dusun_id', 'dusun', 'id'], ['rt', 'rw_id', 'rw', 'id'],
            ['service_area_rt', 'service_area_id', 'service_areas', 'id'], ['service_area_rt', 'rt_id', 'rt', 'id'],
            ['customer_profiles', 'user_id', 'users', 'id'], ['customer_profiles', 'rt_id', 'rt', 'id'],
            ['staff_profiles', 'user_id', 'users', 'id'], ['staff_profiles', 'service_area_id', 'service_areas', 'id'],
            ['staff_service_areas', 'staff_profile_user_id', 'staff_profiles', 'user_id'], ['staff_service_areas', 'service_area_id', 'service_areas', 'id'],
            ['waste_types', 'waste_category_id', 'waste_categories', 'id'], ['waste_types', 'waste_unit_id', 'waste_units', 'id'],
            ['waste_type_conditions', 'waste_type_id', 'waste_types', 'id'], ['waste_type_conditions', 'waste_condition_id', 'waste_conditions', 'id'],
            ['waste_prices', 'waste_type_id', 'waste_types', 'id'], ['waste_prices', 'waste_condition_id', 'waste_conditions', 'id'], ['waste_prices', 'created_by', 'users', 'id'],
        ] as [$table, $column, $target, $key]) {
            $rules[$table.'.*.'.$column] = ['required', 'integer', Rule::in(array_column($this->tables[$target], $key))];
        }
        Validator::make($this->tables, $rules)->validate();
        $users = collect($this->tables['users'])->keyBy('id');
        if ($users->pluck('role')->unique()->sort()->values()->all() !== ['admin', 'bendahara', 'petugas', 'warga']) {
            throw new RuntimeException('Data utama harus menyediakan keempat peran.');
        }
        if (! $users->contains(fn (array $user): bool => $user['role'] === 'admin' && $user['status'] === 'aktif' && ($user['deleted_at'] ?? null) === null)) {
            throw new RuntimeException('Data utama harus memiliki Admin aktif.');
        }
        foreach ($users as $user) {
            if (password_get_info($user['password'])['algo'] === null) {
                throw new RuntimeException('Kata sandi sumber harus berupa hash yang dikenali, bukan plaintext.');
            }
            $role = $user['role'];
            $profiles = array_filter($this->tables['customer_profiles'], fn (array $row): bool => $row['user_id'] === $user['id']);
            $staff = array_filter($this->tables['staff_profiles'], fn (array $row): bool => $row['user_id'] === $user['id']);
            if (($role === 'warga' && count($profiles) !== 1) || ($role !== 'warga' && $profiles !== [])
                || (in_array($role, ['petugas', 'bendahara'], true) && count($staff) !== 1)
                || (! in_array($role, ['petugas', 'bendahara'], true) && $staff !== [])) {
                throw new RuntimeException('Profil harus sesuai satu peran pengguna.');
            }
        }
        foreach (['service_area_rt' => ['service_area_id', 'rt_id'], 'staff_service_areas' => ['staff_profile_user_id', 'service_area_id'], 'waste_type_conditions' => ['waste_type_id', 'waste_condition_id']] as $table => $keys) {
            $seen = [];
            foreach ($this->tables[$table] as $row) {
                $key = implode(':', array_map(fn (string $field): string => (string) $row[$field], $keys));
                if (isset($seen[$key])) {
                    throw new RuntimeException('Relasi master/penugasan duplikat.');
                }
                $seen[$key] = true;
            }
        }
        foreach ($this->tables['waste_prices'] as $price) {
            if ($users[$price['created_by']]['role'] !== 'admin'
                || (($price['effective_to'] ?? null) !== null && $price['effective_to'] <= $price['effective_from'])
                || ! collect($this->tables['waste_type_conditions'])->contains(fn (array $pair): bool => $pair['waste_type_id'] === $price['waste_type_id'] && $pair['waste_condition_id'] === $price['waste_condition_id'])) {
                throw new RuntimeException('Pembuat, periode, atau pasangan kondisi harga tidak valid.');
            }
        }
    }
}
