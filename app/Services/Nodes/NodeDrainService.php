<?php

namespace Pterodactyl\Services\Nodes;

use Pterodactyl\Models\Node;
use Pterodactyl\Services\Servers\ServerTransferService;
use Pterodactyl\Contracts\Repository\AllocationRepositoryInterface;

/**
 * Drains a node: marks it as draining so no new deployments land on it,
 * then starts a transfer for every transferable server to the target
 * node using automatically selected allocations.
 */
class NodeDrainService
{
    public function __construct(
        private AllocationRepositoryInterface $allocationRepository,
        private ServerTransferService $transferService,
    ) {
    }

    /**
     * Mark the source node as draining and transfer its servers to the
     * target node. Returns a summary with per-server outcomes.
     *
     * @return array{transferred: int, skipped: array<string, string>}
     */
    public function handle(Node $source, Node $target): array
    {
        if ($source->id === $target->id) {
            throw new \InvalidArgumentException('Cannot drain a node onto itself.');
        }

        if ($target->draining || $target->maintenance_mode) {
            throw new \InvalidArgumentException('The target node is draining or in maintenance mode.');
        }

        $source->update(['draining' => true]);

        $available = $this->allocationRepository->getUnassignedAllocationIds($target->id);

        $result = ['transferred' => 0, 'skipped' => []];

        foreach ($source->servers()->with('allocations')->get() as $server) {
            $needed = 1 + max(0, $server->allocations->count() - 1);
            if (count($available) < $needed) {
                $result['skipped'][$server->name] = 'not enough free allocations on the target node';
                continue;
            }

            $allocationId = array_shift($available);
            $additional = array_splice($available, 0, $needed - 1);

            try {
                $this->transferService->handle($server, $target, $allocationId, $additional);
                $result['transferred']++;
            } catch (\Throwable $exception) {
                $result['skipped'][$server->name] = $exception->getMessage();
            }
        }

        return $result;
    }
}
