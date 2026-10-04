<?php

declare(strict_types=1);

use App\Domain\Operations\Services\PrivateStorageBoundaryValidator;
use Illuminate\Support\Str;
use PHPUnit\Framework\SkippedWithMessageException;

it('accepts a canonical private directory under the canonical storage root', function (): void {
    config()->set('filesystems.disks.media_private.root', storage_path('app/media'));

    $result = app(PrivateStorageBoundaryValidator::class)->validate();

    expect($result->isValid())->toBeTrue()
        ->and($result->reasonCode())->toBe('ok');
});

it('rejects storage roots that are not strict canonical children', function (): void {
    $sibling = storage_path().'_sibling-'.Str::lower(Str::random(8));
    mkdir($sibling);

    try {
        foreach ([storage_path(), $sibling, public_path()] as $root) {
            config()->set('filesystems.disks.media_private.root', $root);

            $result = app(PrivateStorageBoundaryValidator::class)->validate();

            expect($result->isValid())->toBeFalse()
                ->and($result->reasonCode())->toBe('private_storage_unavailable')
                ->and($result->reasonCode())->not->toContain($root);
        }
    } finally {
        rmdir($sibling);
    }
});

it('rejects public descendants, outside paths, missing paths, non-directories, and malformed configuration', function (): void {
    $publicChild = public_path('private-storage-child-'.Str::lower(Str::random(8)));
    $file = storage_path('app/media-file-'.Str::lower(Str::random(8)));
    mkdir($publicChild);
    file_put_contents($file, 'not a directory');

    try {
        foreach ([
            $publicChild,
            sys_get_temp_dir(),
            storage_path('app/missing-private-storage'),
            $file,
            [],
            null,
        ] as $root) {
            config()->set('filesystems.disks.media_private.root', $root);

            $result = app(PrivateStorageBoundaryValidator::class)->validate();

            expect($result->isValid())->toBeFalse()
                ->and($result->reasonCode())->toBeIn(['private_storage_configuration_invalid', 'private_storage_unavailable']);

            if (is_string($root) && $root !== '') {
                expect($result->reasonCode())->not->toContain($root);
            }
        }
    } finally {
        unlink($file);
        rmdir($publicChild);
    }
});

it('rejects a symlink or junction that resolves outside the canonical storage root when supported', function (): void {
    $target = sys_get_temp_dir().DIRECTORY_SEPARATOR.'private-storage-target-'.Str::lower(Str::random(8));
    $link = storage_path('app/private-storage-link-'.Str::lower(Str::random(8)));
    mkdir($target);

    if (! @symlink($target, $link)) {
        rmdir($target);
        throw new SkippedWithMessageException('Symlink or junction creation is not supported by this environment.');
    }

    try {
        config()->set('filesystems.disks.media_private.root', $link);

        $result = app(PrivateStorageBoundaryValidator::class)->validate();

        expect($result->isValid())->toBeFalse()
            ->and($result->reasonCode())->toBe('private_storage_unavailable')
            ->and($result->reasonCode())->not->toContain($link)
            ->and($result->reasonCode())->not->toContain($target);
    } finally {
        @unlink($link);
        @rmdir($target);
    }
});

it('rejects a non-writable private directory when the platform exposes writability', function (): void {
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'private-storage-readonly-'.Str::lower(Str::random(8));
    mkdir($directory);
    $originalMode = fileperms($directory);
    chmod($directory, 0555);

    if (is_writable($directory)) {
        chmod($directory, $originalMode & 0777);
        rmdir($directory);
        throw new SkippedWithMessageException('The current platform does not expose directory writability changes to PHP.');
    }

    try {
        config()->set('filesystems.disks.media_private.root', $directory);

        $result = app(PrivateStorageBoundaryValidator::class)->validate();

        expect($result->isValid())->toBeFalse()
            ->and($result->reasonCode())->toBe('private_storage_unavailable');
    } finally {
        chmod($directory, $originalMode & 0777);
        rmdir($directory);
    }
});
