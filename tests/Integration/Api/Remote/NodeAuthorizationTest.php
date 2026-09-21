<?php

namespace Pterodactyl\Tests\Integration\Api\Remote;

use Pterodactyl\Models\Node;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Backup;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ElytraJob;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Models\ServerTransfer;
use Pterodactyl\Tests\Integration\IntegrationTestCase;

/**
 * Regression coverage for node authorization on the remote API. Every
 * endpoint below previously resolved resources by UUID without checking
 * that the authenticated daemon node owned the server, so any node's token
 * could read or mutate another node's servers.
 */
class NodeAuthorizationTest extends IntegrationTestCase
{
    protected User $user;

    protected Server $server;

    /** @var Server A server on a different node, used as the cross-node target. */
    protected Server $foreignServer;

    public function setUp(): void
    {
        parent::setUp();

        [$user, $server] = $this->generateTestAccount();
        $this->user = $user;
        $this->server = $server;
        $this->foreignServer = $this->createServerModel();
    }

    /**
     * The server details endpoint must not return configuration, environment
     * variables, or process state for a server on a different node.
     */
    public function testServerDetailsRejectsForeignNode()
    {
        $this->setAuthorization($this->foreignServer->node);

        $this->getJson("/api/remote/servers/{$this->server->uuid}")
            ->assertForbidden();
    }

    /**
     * Both install endpoints must reject a node that does not own the server.
     */
    public function testServerInstallRejectsForeignNode()
    {
        $this->setAuthorization($this->foreignServer->node);

        $this->getJson("/api/remote/servers/{$this->server->uuid}/install")
            ->assertForbidden();

        $this->postJson("/api/remote/servers/{$this->server->uuid}/install", ['successful' => true])
            ->assertForbidden();
    }

    /**
     * A node that is not part of a transfer must not be able to report it
     * successful. A forged success deletes the source copy mid-flight.
     */
    public function testTransferSuccessRejectsForeignNode()
    {
        $transfer = $this->createTransfer($this->server, $this->foreignServer->node);

        $thirdNode = $this->createServerModel()->node;
        $this->setAuthorization($thirdNode);

        $this->postJson("/api/remote/servers/{$this->server->uuid}/transfer/success")
            ->assertForbidden();

        $this->assertFalse((bool) $transfer->fresh()->successful);
        $this->assertSame($this->server->node_id, $this->server->fresh()->node_id);
    }

    /**
     * A node that is not part of a transfer must not be able to fail it and
     * release the reserved target allocations.
     */
    public function testTransferFailureRejectsForeignNode()
    {
        $transfer = $this->createTransfer($this->server, $this->foreignServer->node);

        $thirdNode = $this->createServerModel()->node;
        $this->setAuthorization($thirdNode);

        $this->postJson("/api/remote/servers/{$this->server->uuid}/transfer/failure")
            ->assertForbidden();

        $this->assertNull($transfer->fresh()->successful);
    }

    /**
     * Either transfer endpoint node may report the outcome, since callbacks
     * arrive from the target daemon while the record still points at the
     * source.
     */
    public function testTransferFailureAcceptsEitherEndpointNode()
    {
        $transfer = $this->createTransfer($this->server, $this->foreignServer->node);

        $this->setAuthorization($this->foreignServer->node);

        $this->postJson("/api/remote/servers/{$this->server->uuid}/transfer/failure")
            ->assertNoContent();

        $this->assertFalse((bool) $transfer->fresh()->successful);
    }

    /**
     * The presigned upload URL endpoint must not issue S3 credentials for a
     * backup on another node.
     */
    public function testRemoteUploadRejectsForeignNode()
    {
        $backup = Backup::factory()->create([
            'server_id' => $this->server->id,
            'completed_at' => null,
        ]);

        $this->setAuthorization($this->foreignServer->node);

        $this->getJson("/api/remote/backups/{$backup->uuid}?size=1024")
            ->assertForbidden();
    }

    /**
     * Restore reporting must not clear server state or log audit events for a
     * backup owned by a different node.
     */
    public function testBackupRestoreRejectsForeignNode()
    {
        $backup = Backup::factory()->create(['server_id' => $this->server->id]);
        $this->server->update(['status' => Server::STATUS_RESTORING_BACKUP]);

        $this->setAuthorization($this->foreignServer->node);

        $this->postJson("/api/remote/backups/{$backup->uuid}/restore", ['successful' => true])
            ->assertForbidden();

        $this->assertSame(Server::STATUS_RESTORING_BACKUP, $this->server->fresh()->status);
    }

    /**
     * The owning node may still report a restore normally.
     */
    public function testBackupRestoreAcceptsOwningNode()
    {
        $backup = Backup::factory()->create(['server_id' => $this->server->id]);
        $this->server->update(['status' => Server::STATUS_RESTORING_BACKUP]);

        $this->setAuthorization($this->server->node);

        $this->postJson("/api/remote/backups/{$backup->uuid}/restore", ['successful' => true])
            ->assertNoContent();

        $this->assertNull($this->server->fresh()->status);
    }

    /**
     * A node must not be able to push status updates for a job belonging to a
     * server on another node.
     */
    public function testElytraJobStatusRejectsForeignNode()
    {
        $job = ElytraJob::create([
            'server_id' => $this->server->id,
            'user_id' => $this->user->id,
            'job_type' => 'backup_create',
            'job_data' => ['backup_uuid' => (string) \Illuminate\Support\Str::uuid()],
            'status' => ElytraJob::STATUS_RUNNING,
            'elytra_job_id' => 'elytra-job-1',
        ]);

        $this->setAuthorization($this->foreignServer->node);

        $this->putJson("/api/remote/elytra-jobs/elytra-job-1", [
            'successful' => true,
            'job_type' => 'backup_create',
            'status' => 'completed',
        ])->assertOk();

        // The controller returns success but the service must have ignored the
        // update since the node does not own the job's server.
        $this->assertSame(ElytraJob::STATUS_RUNNING, $job->fresh()->status);
    }

    /**
     * The request-supplied job_type must never override the persisted job
     * record. Reporting a benign job while claiming job_type=delete_all would
     * otherwise wipe every backup record for the server.
     */
    public function testElytraJobStatusUsesPersistedJobType()
    {
        $existing = Backup::factory()->create(['server_id' => $this->server->id]);

        $job = ElytraJob::create([
            'server_id' => $this->server->id,
            'user_id' => $this->user->id,
            'job_type' => 'backup_create',
            'job_data' => ['backup_uuid' => (string) \Illuminate\Support\Str::uuid()],
            'status' => ElytraJob::STATUS_RUNNING,
            'elytra_job_id' => 'elytra-job-2',
        ]);

        $this->setAuthorization($this->server->node);

        $this->putJson("/api/remote/elytra-jobs/elytra-job-2", [
            'successful' => true,
            'job_type' => 'backup_delete_all',
            'status' => 'completed',
        ])->assertOk();

        $this->assertTrue($existing->fresh()->exists() ?? $existing->exists());
        $this->assertSame(ElytraJob::STATUS_COMPLETED, $job->fresh()->status);
    }

    /**
     * Sets the authorization header to authenticate as the given node.
     */
    protected function setAuthorization(Node $node): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $node->daemon_token_id . '.' . decrypt($node->daemon_token));
    }

    /**
     * Creates a transfer record moving the server to the given target node.
     */
    private function createTransfer(Server $server, Node $target): ServerTransfer
    {
        $newAllocation = Allocation::factory()->create(['node_id' => $target->id]);

        return ServerTransfer::create([
            'server_id' => $server->id,
            'old_node' => $server->node_id,
            'new_node' => $target->id,
            'old_allocation' => $server->allocation_id,
            'new_allocation' => $newAllocation->id,
            'old_additional_allocations' => [],
            'new_additional_allocations' => [],
        ]);
    }
}
