<?php

// Seeds a demo environment for screenshots: an admin user, one location,
// one node, and a few servers. Only run this against a throwaway database.

use Ramsey\Uuid\Uuid;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Location;
use Pterodactyl\Models\Allocation;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

Illuminate\Database\Eloquent\Model::unguard();

$admin = User::query()->firstOrCreate(
    ['email' => 'admin@nebulodactyl.dev'],
    [
        'uuid' => Uuid::uuid4()->toString(),
        'username' => 'admin',
        'name_first' => 'Admin',
        'name_last' => 'User',
        'password' => bcrypt('admin'),
        'language' => 'en',
        'root_admin' => true,
        'use_totp' => false,
    ]
);

$location = Location::query()->firstOrCreate(
    ['short' => 'home'],
    ['long' => 'Homelab']
);

$node = Node::query()->firstOrCreate(
    ['name' => 'node-01'],
    [
        'uuid' => Uuid::uuid4()->toString(),
        'location_id' => $location->id,
        'fqdn' => 'node-01.internal',
        'internal_fqdn' => '10.42.0.11',
        'scheme' => 'http',
        'behind_proxy' => false,
        'public' => true,
        'maintenance_mode' => false,
        'memory' => 32768,
        'memory_overallocate' => 0,
        'disk' => 524288,
        'disk_overallocate' => 0,
        'upload_size' => 100,
        'daemon_token_id' => 'demo',
        'daemon_token' => 'demo',
        'daemonListen' => 8080,
        'daemonSFTP' => 2022,
        'daemonBase' => '/var/lib/panel/volumes',
    ]
);

$port = 25565;
$servers = [
    ['name' => 'Survival SMP', 'egg' => 'Paper', 'desc' => 'Main survival world. Paper 1.21, 12 players.'],
    ['name' => 'Creative Lobby', 'egg' => 'Vanilla Minecraft', 'desc' => 'Flat creative world for building tests.'],
    ['name' => 'Rust Staging', 'egg' => 'Rust', 'desc' => 'Wipe-day staging server.'],
];

$firstId = null;
foreach ($servers as $i => $spec) {
    $egg = Egg::query()->where('name', $spec['egg'])->firstOrFail();

    $allocation = Allocation::query()->create([
        'node_id' => $node->id,
        'ip' => '10.10.0.11',
        'port' => $port + $i,
    ]);

    $server = Server::factory()->create([
        'name' => $spec['name'],
        'description' => $spec['desc'],
        'owner_id' => $admin->id,
        'node_id' => $node->id,
        'allocation_id' => $allocation->id,
        'nest_id' => $egg->nest_id,
        'egg_id' => $egg->id,
        'memory' => 4096,
        'disk' => 20480,
        'image' => 'ghcr.io/pterodactyl/yolks:java_21',
        'startup' => 'java -Xms128M -Xmx{{SERVER_MEMORY}}M -jar {{SERVER_JARFILE}}',
    ]);

    $allocation->update(['server_id' => $server->id]);

    $firstId ??= $server->uuidShort;
}

Illuminate\Database\Eloquent\Model::reguard();

// The capture script reads this to find the console screenshot target.
fwrite(STDOUT, "SERVER_ID={$firstId}\n");
