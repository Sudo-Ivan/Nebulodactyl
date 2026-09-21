<?php

namespace Pterodactyl\Services\Backups;

use Pterodactyl\Models\Backup;
use Pterodactyl\Enums\BackupAdapter;
use Pterodactyl\Facades\Activity;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Extensions\Backups\BackupManager;
use Pterodactyl\Repositories\Wings\DaemonBackupRepository;

/**
 * Verifies that a backup the panel believes is healthy still exists on its
 * backing storage. S3 backups are checked with a fileExists call against
 * the node's configured bucket; daemon-local backups are checked through a
 * HEAD request against the Comet backup endpoint. Adapters that cannot be
 * checked remotely are reported as unverifiable rather than failing.
 */
class BackupVerificationService
{
    public const STATE_OK = 'ok';
    public const STATE_MISSING = 'missing';
    public const STATE_UNVERIFIABLE = 'unverifiable';
    public const STATE_ERROR = 'error';

    public function __construct(
        private BackupManager $backupManager,
        private DaemonBackupRepository $daemonRepository,
    ) {
    }

    /**
     * Verify a single backup and persist the outcome on the record.
     */
    public function handle(Backup $backup): string
    {
        try {
            $state = $this->check($backup);
        } catch (\Throwable $exception) {
            Log::warning('Backup verification errored', [
                'backup' => $backup->uuid,
                'error' => $exception->getMessage(),
            ]);
            $state = self::STATE_ERROR;
        }

        $backup->update([
            'verified_at' => now(),
            'verify_state' => $state,
        ]);

        if ($state === self::STATE_MISSING) {
            Log::warning('Backup missing from backing storage', [
                'backup' => $backup->uuid,
                'server' => $backup->server->uuid,
                'disk' => $backup->disk?->value,
            ]);

            Activity::event('server:backup.missing')
                ->subject($backup, $backup->server)
                ->property(['backup' => $backup->uuid, 'disk' => $backup->disk?->value])
                ->log('Scheduled verification could not find this backup on its backing storage.');
        }

        return $state;
    }

    protected function check(Backup $backup): string
    {
        return match (true) {
            $backup->disk === BackupAdapter::S3 => $this->checkS3($backup),
            $backup->disk === BackupAdapter::Wings || $backup->disk === BackupAdapter::Elytra => $this->checkDaemonLocal($backup),
            default => self::STATE_UNVERIFIABLE,
        };
    }

    protected function checkS3(Backup $backup): string
    {
        $bucket = $backup->server->node->s3Bucket;
        if (is_null($bucket)) {
            return self::STATE_UNVERIFIABLE;
        }

        $adapter = $this->backupManager->createS3Adapter($bucket->toS3Config());
        $key = sprintf('%s/%s.tar.gz', $backup->server->uuid, $backup->uuid);

        return $adapter->fileExists($key) ? self::STATE_OK : self::STATE_MISSING;
    }

    protected function checkDaemonLocal(Backup $backup): string
    {
        $node = $backup->server->node;
        // Only Comet exposes the HEAD backup route; on Wings a missing
        // route is indistinguishable from a missing backup.
        if ($node->daemonType !== 'comet') {
            return self::STATE_UNVERIFIABLE;
        }

        $exists = $this->daemonRepository
            ->setServer($backup->server)
            ->setNode($node)
            ->exists($backup);

        return $exists ? self::STATE_OK : self::STATE_MISSING;
    }
}
