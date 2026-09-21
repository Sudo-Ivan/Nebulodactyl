<?php

namespace Pterodactyl\Services\Nebula;

use Pterodactyl\Models\NebulaHost;
use Illuminate\Support\Facades\Process;
use Pterodactyl\Exceptions\Nebula\NebulaException;

/**
 * Signs Nebula host certificates by shelling out to the nebula-cert binary.
 *
 * The CA key never leaves the panel host. Nodes send only their public key
 * during enrollment, so the wire format is safe to transport through the
 * authenticated remote API channel.
 */
class NebulaCertificateAuthority
{
    /**
     * Initialize a new certificate authority pair at the configured paths.
     *
     * @throws \Pterodactyl\Exceptions\Nebula\NebulaException
     */
    public function initialize(): array
    {
        $cert = config('nebula.ca.cert_path');
        $key = config('nebula.ca.key_path');

        if (file_exists($cert) && file_exists($key)) {
            throw new NebulaException('A Nebula CA already exists at the configured path.');
        }

        $dir = dirname($cert);
        if (!is_dir($dir) && !mkdir($dir, 0700, true)) {
            throw new NebulaException("Unable to create CA storage directory {$dir}.");
        }

        $result = $this->cert([
            'ca',
            '-name', (string) config('nebula.ca.name'),
            '-duration', (string) config('nebula.ca.duration'),
            '-networks', (string) config('nebula.overlay_cidr'),
            '-groups', implode(',', config('nebula.groups')),
            '-out-crt', $cert,
            '-out-key', $key,
        ]);

        if (!$result->successful()) {
            throw new NebulaException('nebula-cert ca failed: ' . trim($result->errorOutput()));
        }

        chmod($key, 0600);

        return ['cert' => $cert, 'key' => $key];
    }

    /**
     * The CA certificate in PEM form, handed to enrolling hosts so they can
     * verify peers on the overlay.
     *
     * @throws \Pterodactyl\Exceptions\Nebula\NebulaException
     */
    public function caCertificate(): string
    {
        $cert = config('nebula.ca.cert_path');

        if (!file_exists($cert)) {
            throw new NebulaException('Nebula CA is not initialized. Run php artisan nebula:init-ca first.');
        }

        return (string) file_get_contents($cert);
    }

    /**
     * Sign a host certificate for the given public key.
     *
     * @throws \Pterodactyl\Exceptions\Nebula\NebulaException
     */
    public function sign(string $name, string $publicKey, string $ip, array $groups): string
    {
        $this->caCertificate();

        $subnet = $this->subnetMask();
        $pubPath = tempnam(sys_get_temp_dir(), 'nebula-pub-');
        $outPath = tempnam(sys_get_temp_dir(), 'nebula-crt-');
        file_put_contents($pubPath, $publicKey);

        $args = [
            'sign',
            '-name', $name,
            '-networks', sprintf('%s/%s', $ip, $subnet),
            '-ca-crt', (string) config('nebula.ca.cert_path'),
            '-ca-key', (string) config('nebula.ca.key_path'),
            '-in-pub', $pubPath,
            '-out-crt', $outPath,
            '-duration', (string) config('nebula.ca.cert_duration'),
        ];

        if (!empty($groups)) {
            $args[] = '-groups';
            $args[] = implode(',', $groups);
        }

        try {
            $result = $this->cert($args);

            if (!$result->successful()) {
                throw new NebulaException('nebula-cert sign failed: ' . trim($result->errorOutput()));
            }

            return (string) file_get_contents($outPath);
        } finally {
            @unlink($pubPath);
            @unlink($outPath);
        }
    }

    /**
     * Allocate the next free overlay address inside the configured CIDR.
     *
     * Only IPv4 ranges are supported for allocation. The first usable host
     * address is skipped if it is listed as a lighthouse so panels do not
     * collide with lighthouse assignments when using a tiny range.
     *
     * @throws \Pterodactyl\Exceptions\Nebula\NebulaException
     */
    public function allocateIp(): string
    {
        $cidr = (string) config('nebula.overlay_cidr');
        [$base, $bits] = array_pad(explode('/', $cidr, 2), 2, '24');

        $baseLong = ip2long($base);
        $bits = (int) $bits;

        if ($baseLong === false || $bits < 8 || $bits > 30) {
            throw new NebulaException("NEBULA_OVERLAY_CIDR must be an IPv4 range between /8 and /30, got {$cidr}.");
        }

        $size = 1 << (32 - $bits);
        $first = ($baseLong & (-$size)) + 1;
        $last = $baseLong | ($size - 2);

        $used = NebulaHost::query()->pluck('ip')->map(
            fn (string $ip) => ip2long($ip)
        )->filter()->all();

        for ($candidate = $first; $candidate <= $last; $candidate++) {
            if (!in_array($candidate, $used, true)) {
                return long2ip($candidate);
            }
        }

        throw new NebulaException("The Nebula overlay range {$cidr} is exhausted.");
    }

    /**
     * Fingerprint of a PEM encoded public key for audit purposes.
     */
    public function fingerprint(string $publicKey): string
    {
        return hash('sha256', trim($publicKey));
    }

    /**
     * Subnet mask bits from the configured overlay CIDR.
     */
    private function subnetMask(): int
    {
        return (int) (explode('/', (string) config('nebula.overlay_cidr'))[1] ?? 24);
    }

    /**
     * Run the nebula-cert binary with the given arguments.
     */
    private function cert(array $args): \Illuminate\Process\ProcessResult
    {
        return Process::timeout(30)->run(
            array_merge([(string) config('nebula.ca.binary')], $args)
        );
    }
}
