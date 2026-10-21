<?php

declare(strict_types=1);

namespace App\Support\Seeding;

use Illuminate\Support\Facades\DB;
use LogicException;

final class HostingDemoGuard
{
    public function validateTarget(string $typedDatabase): void
    {
        $expected = config('operations.hosting_demo.database');
        $connection = DB::connection();
        if (config('operations.hosting_demo.enabled') !== true || ! is_string($expected)
            || preg_match('/^[a-zA-Z0-9_]{1,64}$/D', $expected) !== 1
            || ! hash_equals($expected, $typedDatabase)
            || ! in_array(config('app.env'), ['production', 'testing'], true)
            || ! in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)
            || $connection->getDatabaseName() !== $expected
            || $connection->getConfig('database') !== $expected
            || ! is_string(config('app.key')) || config('app.key') === '') {
            throw new LogicException('Ditolak: aktifkan izin dummy sementara dan cocokkan nama database hosting persis. Hanya production atau testing MySQL terpisah.');
        }
        if (config('app.env') === 'testing' && (preg_match('/^bank_sampah_test(?:_[a-z0-9]+)*$/D', $expected) !== 1
            || ! in_array($connection->getConfig('host'), ['localhost', '127.0.0.1', '::1'], true))) {
            throw new LogicException('Testing hanya menerima database disposable loopback.');
        }
        $actual = $connection->selectOne('SELECT DATABASE() AS database_name');
        if ($actual === null || $actual->database_name !== $expected) {
            throw new LogicException('Database koneksi aktual berbeda; tidak ada data yang diubah.');
        }
    }

    public function source(): MasterData
    {
        $path = realpath((string) config('app.master_seed_path'));
        $publicRoots = [realpath(public_path()), realpath((string) config('operations.deployment.document_root'))];
        foreach ($publicRoots as $root) {
            if ($path !== false && $root !== false && str_starts_with(strtolower(str_replace('\\', '/', $path)), rtrim(strtolower(str_replace('\\', '/', $root)), '/').'/')) {
                throw new LogicException('Sumber data privat tidak boleh berada di document root publik.');
            }
        }

        return MasterData::read($path === false ? '' : $path);
    }
}
