<?php

namespace Pterodactyl\Console\Commands\Nebula;

use Illuminate\Console\Command;
use Pterodactyl\Exceptions\Nebula\NebulaException;
use Pterodactyl\Services\Nebula\NebulaCertificateAuthority;

class InitializeNebulaCaCommand extends Command
{
    protected $description = 'Initialize the Nebula certificate authority used to sign node certificates.';

    protected $signature = 'nebula:init-ca {--force : Overwrite an existing CA pair}';

    public function __construct(private NebulaCertificateAuthority $ca)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->option('force')) {
            foreach ([config('nebula.ca.cert_path'), config('nebula.ca.key_path')] as $path) {
                if (is_string($path) && file_exists($path)) {
                    unlink($path);
                }
            }
        }

        try {
            $paths = $this->ca->initialize();
        } catch (NebulaException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Nebula CA initialized.');
        $this->line("Certificate: {$paths['cert']}");
        $this->line("Key: {$paths['key']}");
        $this->warn('Keep the key backed up and private. It signs every node certificate on the overlay.');

        return self::SUCCESS;
    }
}
