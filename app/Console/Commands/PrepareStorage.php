<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Makes sure the documents bucket exists.
 *
 * A fresh RustFS container starts with no buckets, and the first upload would otherwise fail
 * with a fairly unhelpful "NoSuchBucket". Running this is part of local setup. It's safe to run
 * as often as you like, and against real S3 it just confirms the bucket is there.
 */
#[Signature('showroom:storage-init')]
#[Description('Create the documents bucket if it does not exist yet')]
class PrepareStorage extends Command
{
    public function handle(): int
    {
        $disk = Storage::disk('documents');

        if (! $disk instanceof AwsS3V3Adapter) {
            $this->components->info('Documents disk is not S3-backed, nothing to prepare.');

            return self::SUCCESS;
        }

        $bucket = (string) config('filesystems.disks.documents.bucket');
        $client = $disk->getClient();

        try {
            if ($client->doesBucketExistV2($bucket, false)) {
                $this->components->info("Bucket [{$bucket}] already exists.");

                return self::SUCCESS;
            }

            $client->createBucket(['Bucket' => $bucket]);
        } catch (Throwable $e) {
            $this->components->error("Could not reach object storage: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->components->info("Created bucket [{$bucket}].");

        return self::SUCCESS;
    }
}
