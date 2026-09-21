<?php

namespace Pterodactyl\Http\Controllers\Admin\Nodes;

use Illuminate\Http\Request;
use Pterodactyl\Models\Node;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Nodes\NodeDrainService;

class NodeDrainController extends Controller
{
    /**
     * NodeDrainController constructor.
     */
    public function __construct(
        private AlertsMessageBag $alert,
        private NodeDrainService $drainService,
    ) {
    }

    /**
     * Mark the node as draining and transfer its servers to the target node.
     */
    public function __invoke(Request $request, Node $node): RedirectResponse
    {
        $validated = $request->validate([
            'target_node_id' => 'required|exists:nodes,id|not_in:' . $node->id,
        ]);

        $target = Node::findOrFail($validated['target_node_id']);

        try {
            $result = $this->drainService->handle($node, $target);
        } catch (\InvalidArgumentException $exception) {
            $this->alert->danger($exception->getMessage())->flash();

            return redirect()->route('admin.nodes.view.servers', $node->id);
        }

        $message = sprintf('Node is now draining. Started transfers for %d server(s).', $result['transferred']);
        if (!empty($result['skipped'])) {
            $message .= ' Skipped: ' . collect($result['skipped'])
                ->map(fn ($reason, $name) => sprintf('%s (%s)', $name, $reason))
                ->implode(', ');
        }

        $this->alert->success($message)->flash();

        return redirect()->route('admin.nodes.view.servers', $node->id);
    }
}
