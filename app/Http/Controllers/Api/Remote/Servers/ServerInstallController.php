<?php

namespace Pterodactyl\Http\Controllers\Api\Remote\Servers;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Pterodactyl\Models\Server;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Repositories\Eloquent\ServerRepository;
use Pterodactyl\Exceptions\Http\HttpForbiddenException;
use Pterodactyl\Events\Server\Installed as ServerInstalled;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use Pterodactyl\Http\Requests\Api\Remote\InstallationDataRequest;

class ServerInstallController extends Controller
{
    /**
     * ServerInstallController constructor.
     */
    public function __construct(private ServerRepository $repository, private EventDispatcher $eventDispatcher) {}

    /**
     * Get server install info.
     *
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function index(Request $request, string $uuid): JsonResponse
    {
        $server = $this->repository->getByUuid($uuid);
        $this->assertNodeAccess($request, $server);
        $egg = $server->egg;

        return new JsonResponse([
            'container_image' => $egg->copy_script_container,
            'entrypoint' => $egg->copy_script_entry,
            'script' => $egg->copy_script_install,
        ]);
    }

    /**
     * Report server install result.
     *
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     * @throws \Pterodactyl\Exceptions\Model\DataValidationException
     */
    public function store(InstallationDataRequest $request, string $uuid): JsonResponse
    {
        $server = $this->repository->getByUuid($uuid);
        $this->assertNodeAccess($request, $server);
        $status = null;

        // Make sure the type of failure is accurate
        if (!$request->boolean('successful')) {
            $status = Server::STATUS_INSTALL_FAILED;

            if ($request->boolean('reinstall')) {
                $status = Server::STATUS_REINSTALL_FAILED;
            }
        }

        // Keep the server suspended if it's already suspended
        if ($server->status === Server::STATUS_SUSPENDED) {
            $status = Server::STATUS_SUSPENDED;
        }

        $this->repository->update($server->id, ['status' => $status, 'installed_at' => CarbonImmutable::now()], true, true);

        Activity::event($request->boolean('successful') ? 'server:install.completed' : 'server:install.failed')
            ->subject($server)
            ->property('reinstall', $request->boolean('reinstall'))
            ->log();

        // If the server successfully installed, fire installed event.
        // This logic allows individually disabling install and reinstall notifications separately.
        $isInitialInstall = is_null($server->installed_at);
        if ($isInitialInstall && config()->get('pterodactyl.email.send_install_notification', true)) {
            $this->eventDispatcher->dispatch(new ServerInstalled($server));
        } elseif (!$isInitialInstall && config()->get('pterodactyl.email.send_reinstall_notification', true)) {
            $this->eventDispatcher->dispatch(new ServerInstalled($server));
        }

        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }

    /**
     * Only the node that hosts the server (or a node participating in an
     * active transfer of it) may read install scripts or report results.
     */
    private function assertNodeAccess(Request $request, Server $server): void
    {
        $node = $request->attributes->get('node');
        if (is_null($node)) {
            throw new HttpForbiddenException('You do not have permission to access this server.');
        }

        if ($server->node_id === $node->id) {
            return;
        }

        $transfer = $server->transfer;
        if (!is_null($transfer) && ($transfer->new_node === $node->id || $transfer->old_node === $node->id)) {
            return;
        }

        throw new HttpForbiddenException('You do not have permission to access this server.');
    }
}
