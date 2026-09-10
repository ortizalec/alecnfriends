<?php

test('public storage uses the local driver by default', function () {
    $configuration = require base_path('config/filesystems.php');

    expect($configuration['disks']['public'])
        ->toMatchArray([
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'visibility' => 'public',
        ]);
});

test('public storage can use an s3 compatible bucket without object visibility', function () {
    $originalDriver = getenv('PUBLIC_FILESYSTEM_DRIVER');
    putenv('PUBLIC_FILESYSTEM_DRIVER=s3');

    try {
        $configuration = require base_path('config/filesystems.php');
    } finally {
        $originalDriver === false
            ? putenv('PUBLIC_FILESYSTEM_DRIVER')
            : putenv('PUBLIC_FILESYSTEM_DRIVER='.$originalDriver);
    }

    expect($configuration['disks']['public'])
        ->toMatchArray(['driver' => 's3'])
        ->not->toHaveKey('visibility');
});
