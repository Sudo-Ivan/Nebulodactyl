<?php

namespace Pterodactyl\Console\Commands;

use Illuminate\Console\Command;
use Pterodactyl\Models\User;
use Pterodactyl\Services\Setup\SetupLinkService;

class SetupLinkCommand extends Command
{
    protected $description = 'Print a fresh first-run setup link, valid for one hour.';

    protected $signature = 'p:setup:link';

    public function __construct(private SetupLinkService $links)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if (User::query()->exists()) {
            $this->components->warn('An account already exists. The setup flow is disabled.');

            return self::FAILURE;
        }

        $url = $this->links->url($this->links->issue());

        $this->components->info('Open this link to create the first administrator account:');
        $this->line('');
        $this->line("    {$url}");
        $this->line('');
        $this->components->warn('The link expires in one hour. Run this command again for a new one.');

        return self::SUCCESS;
    }
}
