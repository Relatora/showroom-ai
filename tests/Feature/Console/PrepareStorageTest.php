<?php

it('skips quietly when the documents disk is not backed by S3', function () {
    config()->set('filesystems.disks.documents', [
        'driver' => 'local',
        'root' => storage_path('framework/testing/documents'),
    ]);

    $this->artisan('showroom:storage-init')
        ->expectsOutputToContain('not S3-backed')
        ->assertSuccessful();
});
