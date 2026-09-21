<?php

namespace Pterodactyl\Services\Servers;

use Carbon\CarbonImmutable;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Enums\Daemon\JwtScope;
use Pterodactyl\Models\ServerTransfer;
use Illuminate\Database\ConnectionInterface;
use Pterodactyl\Services\Nodes\NodeJWTService;
use Pterodactyl\Repositories\Wings\DaemonTransferRepository;
use Pterodactyl\Contracts\Repository\AllocationRepositoryInterface;

/**
 * Creates a server transfer record, reserves the destination allocations,
 * and notifies the source daemon. Shared by the admin single-server
 * transfer action and the node drain flow.
 */
class ServerTransferService
{
    public function __construct(
        private AllocationRepositoryInterface $allocationRepository,
        private ConnectionInterface $connection,
        private DaemonTransferRepository $daemonTransferRepository,
        private NodeJWTService $nodeJWTService,
    ) {
    }

    /**
     * Start a transfer of the server to the target node.
     *
     * @throws \Throwable
     */
    public function handle(Server $server, Node $target, int $allocationId, array $additionalAllocations = []): ServerTransfer
    {
        $server->validateTransferState();

        return $this->connection->transaction(function () use ($server, $target, $allocationId, $additionalAllocations) {
            $transfer = new ServerTransfer();

            $transfer->server_id = $server->id;
            $transfer->old_node = $server->node_id;
            $transfer->new_node = $target->id;
            $transfer->old_allocation = $server->allocation_id;
            $transfer->new_allocation = $allocationId;
            $transfer->old_additional_allocations = $server->allocations->where('id', '!=', $server->allocation_id)->pluck('id');
            $transfer->new_additional_allocations = $additionalAllocations;

            $transfer->save();

            // Add the allocations to the server, so they cannot be automatically assigned while the transfer is in progress.
            $this->assignAllocationsToServer($server, $target->id, $allocationId, $additionalAllocations);

            // Generate a token for the destination node that the source node can use to authenticate with.
            $token = $this->nodeJWTService
                ->setExpiresAt(CarbonImmutable::now()->addMinutes(15))
                ->setSubject($server->uuid)
                ->setScopes(JwtScope::ServerTransfer)
                ->handle($transfer->newNode, $server->uuid, 'sha256');

            // Notify the source node of the pending outgoing transfer.
            $this->daemonTransferRepository->setServer($server)->notify($transfer->newNode, $token);

            return $transfer;
        });
    }

    /**
     * Assigns the specified allocations to the specified server.
     *
     * The update is conditional on each row still being unassigned on the
     * target node and fails the transfer if any allocation was claimed
     * concurrently. Silently skipping them would leave the transfer record
     * pointing at allocations this server does not own.
     *
     * @throws \Throwable
     */
    private function assignAllocationsToServer(Server $server, int $nodeId, int $allocationId, array $additionalAllocations): void
    {
        $allocations = $additionalAllocations;
        $allocations[] = $allocationId;

        $reserved = Allocation::query()
            ->whereIn('id', $allocations)
            ->where('node_id', $nodeId)
            ->whereNull('server_id')
            ->update(['server_id' => $server->id]);

        if ($reserved !== count($allocations)) {
            throw new \RuntimeException('One or more requested allocations are no longer available on the target node.');
        }
    }
}
