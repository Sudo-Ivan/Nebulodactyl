<?php

namespace Pterodactyl\Repositories\Wings;

use Webmozart\Assert\Assert;
use Pterodactyl\Models\Backup;
use Pterodactyl\Models\Server;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\ClientException;
use Symfony\Component\HttpFoundation\Response;
use GuzzleHttp\Exception\TransferException;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

/**
 * @method \Pterodactyl\Repositories\Wings\DaemonBackupRepository setNode(\Pterodactyl\Models\Node $node)
 * @method \Pterodactyl\Repositories\Wings\DaemonBackupRepository setServer(\Pterodactyl\Models\Server $server)
 */
class DaemonBackupRepository extends DaemonRepository
{
    protected ?string $adapter;

    /**
     * Sets the backup adapter for this execution instance.
     */
    public function setBackupAdapter(string $adapter): self
    {
        $this->adapter = $adapter;

        return $this;
    }

    /**
     * Tells the remote Daemon to begin generating a backup for the server.
     *
     * @throws DaemonConnectionException
     */
    public function backup(Backup $backup): ResponseInterface
    {
        Assert::isInstanceOf($this->server, Server::class);

        try {
            return $this->getHttpClient()->post(
                sprintf('/api/servers/%s/backup', $this->server->uuid),
                [
                    'json' => [
                        'adapter' => $this->adapter ?? config('backups.default'),
                        'uuid' => $backup->uuid,
                        'ignore' => implode("\n", $backup->ignored_files),
                    ],
                ]
            );
        } catch (TransferException $exception) {
            throw new DaemonConnectionException($exception);
        }
    }

    /**
     * Checks whether a local backup archive still exists on the daemon.
     * Only call this against daemons that expose the HEAD backup route
     * (Comet); on Wings a missing route is indistinguishable from a
     * missing backup.
     *
     * @throws DaemonConnectionException
     */
    public function exists(Backup $backup): bool
    {
        Assert::isInstanceOf($this->server, Server::class);

        try {
            $this->getHttpClient()->head(
                sprintf('/api/servers/%s/backup/%s', $this->server->uuid, $backup->uuid)
            );

            return true;
        } catch (ClientException $exception) {
            if ($exception->getResponse()->getStatusCode() === Response::HTTP_NOT_FOUND) {
                return false;
            }

            throw new DaemonConnectionException($exception);
        } catch (TransferException $exception) {
            throw new DaemonConnectionException($exception);
        }
    }

    /**
     * Sends a request to Wings to begin restoring a backup for a server.
     *
     * @throws DaemonConnectionException
     */
    public function restore(Backup $backup, ?string $url = null, bool $truncate = false): ResponseInterface
    {
        Assert::isInstanceOf($this->server, Server::class);

        try {
            return $this->getHttpClient()->post(
                sprintf('/api/servers/%s/backup/%s/restore', $this->server->uuid, $backup->uuid),
                [
                    'json' => [
                        'adapter' => $backup->disk,
                        'truncate_directory' => $truncate,
                        'download_url' => $url ?? '',
                    ],
                ]
            );
        } catch (TransferException $exception) {
            throw new DaemonConnectionException($exception);
        }
    }

    /**
     * Deletes a backup from the daemon.
     *
     * @throws DaemonConnectionException
     */
    public function delete(Backup $backup): ResponseInterface
    {
        Assert::isInstanceOf($this->server, Server::class);

        try {
            return $this->getHttpClient()->delete(
                sprintf('/api/servers/%s/backup/%s', $this->server->uuid, $backup->uuid)
            );
        } catch (TransferException $exception) {
            throw new DaemonConnectionException($exception);
        }
    }
}
