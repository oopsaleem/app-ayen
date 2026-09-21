<?php

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

test('the r2 disk is configured to use the s3 driver with path-style endpoint', function () {
    $config = config('filesystems.disks.r2');

    expect($config['driver'])->toBe('s3')
        ->and($config['use_path_style_endpoint'])->toBeTrue()
        ->and($config['throw'])->toBeFalse();
});

test('the r2 disk reads its credentials and bucket from R2-prefixed env vars', function () {
    config([
        'filesystems.disks.r2.key' => 'test-key',
        'filesystems.disks.r2.secret' => 'test-secret',
        'filesystems.disks.r2.bucket' => 'test-bucket',
        'filesystems.disks.r2.region' => 'auto',
        'filesystems.disks.r2.endpoint' => 'https://example.r2.cloudflarestorage.com',
    ]);

    $config = config('filesystems.disks.r2');

    expect($config['key'])->toBe('test-key')
        ->and($config['secret'])->toBe('test-secret')
        ->and($config['bucket'])->toBe('test-bucket')
        ->and($config['region'])->toBe('auto')
        ->and($config['endpoint'])->toBe('https://example.r2.cloudflarestorage.com');
});

test('the r2 disk resolves to an s3-backed filesystem instance', function () {
    config([
        'filesystems.disks.r2.key' => 'test-key',
        'filesystems.disks.r2.secret' => 'test-secret',
        'filesystems.disks.r2.bucket' => 'test-bucket',
        'filesystems.disks.r2.region' => 'auto',
        'filesystems.disks.r2.endpoint' => 'https://example.r2.cloudflarestorage.com',
    ]);

    expect(Storage::disk('r2'))->toBeInstanceOf(FilesystemAdapter::class);
});
