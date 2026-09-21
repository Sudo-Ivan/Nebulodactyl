<?php

namespace Pterodactyl\Http\Controllers\Admin\Servers;

use Illuminate\Http\Request;
use Pterodactyl\Models\Server;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Repositories\Eloquent\NodeRepository;
use Pterodactyl\Services\Servers\ServerTransferService;

class ServerTransferController extends Controller
{
    /**
     * ServerTransferController constructor.
     */
    public function __construct(
        private AlertsMessageBag $alert,
        private NodeRepository $nodeRepository,
        private ServerTransferService $transferService,
    ) {
    }

    /**
     * Starts a transfer of a server to a new node.
     *
     * @throws \Throwable
     */
    public function transfer(Request $request, Server $server): RedirectResponse
    {
        $validatedData = $request->validate([
            'node_id' => 'required|exists:nodes,id',
            'allocation_id' => 'required|bail|unique:servers|exists:allocations,id',
            'allocation_additional' => 'nullable',
        ]);

        $node_id = $validatedData['node_id'];
        $allocation_id = intval($validatedData['allocation_id']);
        $additional_allocations = array_map('intval', $validatedData['allocation_additional'] ?? []);

        // Check if the node is viable for the transfer.
        $node = $this->nodeRepository->getNodeWithResourceUsage($node_id);
        if (!$node->isViable($server->memory, $server->disk)) {
            $this->alert->danger(trans('admin/server.alerts.transfer_not_viable'))->flash();

            return redirect()->route('admin.servers.view.manage', $server->id);
        }

        $this->transferService->handle($server, $node, $allocation_id, $additional_allocations);

        $this->alert->success(trans('admin/server.alerts.transfer_started'))->flash();

        return redirect()->route('admin.servers.view.manage', $server->id);
    }
}
