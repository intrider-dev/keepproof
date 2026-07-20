<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Aws\Exception\AwsException;
use Aws\S3\S3Client;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Throwable;

final class PreparePrivateStorage extends Command
{
    private const string SENTINEL_KEY = '.keepproof/ready';

    /** @var string */
    protected $signature = 'keepproof:storage:prepare
        {--create : Create a missing S3 bucket (intended for local setup)}';

    /** @var string */
    protected $description = 'Verify private receipt storage and install its readiness sentinel';

    public function handle(FilesystemFactory $filesystems): int
    {
        $disk = config('receipt_processing.disk');
        if (! is_string($disk) || $disk === '') {
            $this->components->error('The receipt storage disk is not configured.');

            return self::FAILURE;
        }

        if ($disk === 's3' && ! $this->ensureS3Bucket()) {
            return self::FAILURE;
        }

        try {
            $storage = $filesystems->disk($disk);
            $written = $storage->put(self::SENTINEL_KEY, "ready\n", [
                'visibility' => 'private',
                'ContentType' => 'text/plain',
            ]);

            if (! $written || ! $storage->exists(self::SENTINEL_KEY)) {
                $this->components->error('Receipt storage is reachable but not writable.');

                return self::FAILURE;
            }
        } catch (Throwable) {
            $this->components->error('Receipt storage could not be verified.');

            return self::FAILURE;
        }

        $this->components->info(sprintf('Private receipt storage "%s" is ready.', $disk));

        return self::SUCCESS;
    }

    private function ensureS3Bucket(): bool
    {
        $configuration = config('filesystems.disks.s3');
        if (! is_array($configuration)) {
            $this->components->error('The S3 receipt disk is incomplete.');

            return false;
        }

        $bucket = $this->requiredString($configuration, 'bucket');
        $key = $this->requiredString($configuration, 'key');
        $secret = $this->requiredString($configuration, 'secret');
        $region = $this->requiredString($configuration, 'region');

        if ($bucket === null || $key === null || $secret === null || $region === null) {
            $this->components->error('The S3 bucket, region, and credentials must be configured.');

            return false;
        }

        /** @var array<string, mixed> $clientConfiguration */
        $clientConfiguration = [
            'version' => 'latest',
            'region' => $region,
            'credentials' => ['key' => $key, 'secret' => $secret],
            'use_path_style_endpoint' => (bool) ($configuration['use_path_style_endpoint'] ?? false),
        ];
        $endpoint = $configuration['endpoint'] ?? null;
        if (is_string($endpoint) && $endpoint !== '') {
            $clientConfiguration['endpoint'] = $endpoint;
        }

        $client = new S3Client($clientConfiguration);

        try {
            $client->headBucket(['Bucket' => $bucket]);

            return true;
        } catch (AwsException $exception) {
            $missing = $exception->getStatusCode() === 404
                || in_array($exception->getAwsErrorCode(), ['NoSuchBucket', 'NotFound'], true);

            if (! $missing) {
                $this->components->error('The configured S3 bucket could not be inspected.');

                return false;
            }
        }

        if ($this->option('create') !== true) {
            $this->components->error('The configured S3 bucket does not exist. Provision it or pass --create.');

            return false;
        }

        try {
            $arguments = ['Bucket' => $bucket];
            if ($region !== 'us-east-1') {
                $arguments['CreateBucketConfiguration'] = ['LocationConstraint' => $region];
            }
            $client->createBucket($arguments);
            $this->components->info('Created the private S3 bucket.');

            return true;
        } catch (Throwable) {
            $this->components->error('The private S3 bucket could not be created.');

            return false;
        }
    }

    /**
     * @param  array<mixed>  $configuration
     */
    private function requiredString(array $configuration, string $key): ?string
    {
        $value = $configuration[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
