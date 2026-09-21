<?php

namespace Pterodactyl\Http\Controllers\Api\Remote;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Pterodactyl\Models\Node;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Models\NebulaHost;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Exceptions\Nebula\NebulaException;
use Pterodactyl\Services\Nebula\NebulaCertificateAuthority;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class NebulaEnrollmentController extends Controller
{
    public function __construct(private NebulaCertificateAuthority $ca)
    {
    }

    /**
     * Enroll this node onto the Nebula overlay.
     *
     * The node agent (nebulod) calls this endpoint with its daemon token and
     * a freshly generated X25519 public key. The panel signs a certificate
     * bound to the node name and returns everything the agent needs to bring
     * the tunnel up: the signed certificate, the CA certificate, the assigned
     * overlay address, the lighthouse map, and the firewall ruleset.
     *
     * Re-enrolling with the same key is idempotent and returns the existing
     * certificate. Re-enrolling with a new key revokes the previous
     * certificate and issues a fresh one on the same overlay address.
     */
    public function enroll(Request $request): JsonResponse
    {
        $this->assertEnabled();

        $node = $this->node($request);
        $publicKey = (string) $request->input('public_key');

        $decoded = base64_decode($publicKey, true);
        if ($decoded === false || strlen($decoded) !== 32) {
            throw new BadRequestHttpException('public_key must be a base64 encoded X25519 public key.');
        }

        $groups = $request->input('groups', config('nebula.groups'));
        $groups = is_array($groups) ? array_values(array_filter($groups, 'is_string')) : config('nebula.groups');

        /** @var NebulaHost|null $host */
        $host = NebulaHost::query()->where('node_id', $node->id)->whereNull('revoked_at')->first();

        if ($host && hash_equals((string) $host->public_key, $publicKey) && $host->isActive()) {
            $host->update(['last_seen_at' => CarbonImmutable::now()]);

            return $this->respond($host);
        }

        $ip = $host?->ip ?? $this->ca->allocateIp();
        $certificate = $this->ca->sign($node->uuid, $publicKey, $ip, $groups);

        if ($host) {
            $host->update(['revoked_at' => CarbonImmutable::now()]);
        }

        $host = NebulaHost::create([
            'node_id' => $node->id,
            'name' => $node->uuid,
            'ip' => $ip,
            'public_key' => $publicKey,
            'fingerprint' => $this->ca->fingerprint($publicKey),
            'certificate' => $certificate,
            'groups' => $groups,
            'expires_at' => CarbonImmutable::now()->addSeconds($this->durationSeconds()),
            'last_seen_at' => CarbonImmutable::now(),
        ]);

        return $this->respond($host);
    }

    /**
     * Return the current enrollment state for this node without rotating
     * anything. Agents poll this to learn about CA or lighthouse changes.
     */
    public function show(Request $request): JsonResponse
    {
        $this->assertEnabled();

        $host = NebulaHost::query()
            ->where('node_id', $this->node($request)->id)
            ->whereNull('revoked_at')
            ->first();

        if (!$host || !$host->isActive()) {
            throw new NotFoundHttpException('This node is not enrolled on the Nebula overlay.');
        }

        $host->update(['last_seen_at' => CarbonImmutable::now()]);

        return $this->respond($host);
    }

    /**
     * Revoke the active certificate for this node, forcing re-enrollment.
     */
    public function destroy(Request $request): JsonResponse
    {
        $this->assertEnabled();

        NebulaHost::query()
            ->where('node_id', $this->node($request)->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => CarbonImmutable::now()]);

        return new JsonResponse([], JsonResponse::HTTP_NO_CONTENT);
    }

    private function node(Request $request): Node
    {
        /** @var Node $node */
        $node = $request->attributes->get('node');

        return $node;
    }

    private function assertEnabled(): void
    {
        if (!config('nebula.enabled')) {
            throw new ServiceUnavailableHttpException(null, 'Nebula overlay support is not enabled on this panel.');
        }
    }

    private function respond(NebulaHost $host): JsonResponse
    {
        return new JsonResponse([
            'name' => $host->name,
            'certificate' => $host->certificate,
            'ca_certificate' => $this->ca->caCertificate(),
            'ip' => $host->ip,
            'cidr' => sprintf('%s/%d', $host->ip, $this->subnetMask()),
            'groups' => $host->groups ?? [],
            'lighthouses' => array_values(config('nebula.lighthouses')),
            'static_host_map' => $this->staticHostMap(),
            'listen_port' => (int) config('nebula.port'),
            'agent_port' => (int) config('nebula.agent_port'),
            'firewall' => config('nebula.firewall'),
            'expires_at' => $host->expires_at?->toIso8601String(),
            'fingerprint' => $host->fingerprint,
        ]);
    }

    /**
     * Build the static_host_map structure: overlay address to underlay
     * endpoints, from the NEBULA_STATIC_HOSTS entries.
     */
    private function staticHostMap(): array
    {
        $map = [];
        foreach (config('nebula.static_hosts') as $entry) {
            $parts = explode(':', (string) $entry);
            if (count($parts) >= 2) {
                $ip = array_shift($parts);
                $map[$ip] = [implode(':', $parts)];
            }
        }

        return $map;
    }

    private function subnetMask(): int
    {
        return (int) (explode('/', (string) config('nebula.overlay_cidr'))[1] ?? 24);
    }

    private function durationSeconds(): int
    {
        $duration = (string) config('nebula.ca.cert_duration');
        if (preg_match('/^(\d+)h$/', $duration, $m) === 1) {
            return ((int) $m[1]) * 3600;
        }

        return 90 * 24 * 3600;
    }
}
