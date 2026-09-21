<?php

namespace Pterodactyl\Console\Commands\Maintenance;

use Illuminate\Console\Command;
use Pterodactyl\Models\Backup;
use Pterodactyl\Services\Backups\BackupVerificationService;

class VerifyBackupsCommand extends Command
{
    protected $description = 'Verify that successful backups still exist on their backing storage.';

    protected $signature = 'p:backup:verify
        {--limit=500 : Maximum number of backups to check in one run}
        {--since= : Only verify backups completed within this many hours (default: all unverified or stale)}';

    /**
     * Handle command execution.
     */
    public function handle(BackupVerificationService $service): int
    {
        $query = Backup::query()
            ->successful()
            ->with('server.node.s3Bucket')
            ->orderByDesc('completed_at')
            ->limit((int) $this->option('limit'));

        if ($since = $this->option('since')) {
            $query->where('completed_at', '>=', now()->subHours((int) $since));
        } else {
            // Default sweep: never verified, or last checked over a day ago.
            $query->where(function ($q) {
                $q->whereNull('verified_at')
                    ->orWhere('verified_at', '<', now()->subDay());
            });
        }

        $counts = [];
        $query->chunkById(50, function ($backups) use ($service, &$counts) {
            foreach ($backups as $backup) {
                $state = $service->handle($backup);
                $counts[$state] = ($counts[$state] ?? 0) + 1;
            }
        });

        foreach ($counts as $state => $count) {
            $this->info(sprintf('%s: %d', $state, $count));
        }

        if (empty($counts)) {
            $this->info('No backups needed verification.');
        }

        return self::SUCCESS;
    }
}
