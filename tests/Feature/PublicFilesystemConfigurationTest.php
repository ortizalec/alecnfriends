<?php

use Illuminate\Foundation\CloudBootstrapper;

test('public storage uses the local disk outside Laravel Cloud', function () {
    expect(config('filesystems.disks.public'))
        ->toMatchArray([
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'visibility' => 'public',
        ]);
});

test('Laravel Cloud replaces the public disk with its injected bucket configuration', function () {
    $_SERVER['LARAVEL_CLOUD_DISK_CONFIG'] = json_encode([[
        'disk' => 'public',
        'access_key_id' => 'access-key',
        'access_key_secret' => 'secret-key',
        'bucket' => 'cast-photos',
        'url' => 'https://images.example.com',
        'endpoint' => 'https://storage.example.com',
    ]], JSON_THROW_ON_ERROR);

    try {
        CloudBootstrapper::configureDisks(app());

        expect(config('filesystems.disks.public'))
            ->toMatchArray([
                'driver' => 's3',
                'bucket' => 'cast-photos',
                'url' => 'https://images.example.com',
            ])
            ->not->toHaveKey('visibility');
    } finally {
        unset($_SERVER['LARAVEL_CLOUD_DISK_CONFIG']);
    }
});
