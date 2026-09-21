<?php

// Seeds a demo environment for screenshots: an admin user, two locations,
// two nodes (one reachable over the Nebula overlay), and several servers
// with realistic settings. Only run this against a throwaway database.

use Ramsey\Uuid\Uuid;
use Illuminate\Support\Facades\Crypt;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\Task;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Schedule;
use Pterodactyl\Models\Location;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Models\Database;
use Pterodactyl\Models\DatabaseHost;

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

$operator = User::query()->firstOrCreate(
    ['email' => 'ops@nebulodactyl.dev'],
    [
        'uuid' => Uuid::uuid4()->toString(),
        'username' => 'ops',
        'name_first' => 'Ops',
        'name_last' => 'User',
        'password' => bcrypt('ops'),
        'language' => 'en',
        'root_admin' => false,
        'use_totp' => false,
    ]
);

$home = Location::query()->firstOrCreate(['short' => 'home'], ['long' => 'Homelab']);
$overlay = Location::query()->firstOrCreate(['short' => 'nbl'], ['long' => 'Nebula overlay']);

$nodeMain = Node::query()->firstOrCreate(
    ['name' => 'node-01'],
    [
        'uuid' => Uuid::uuid4()->toString(),
        'location_id' => $home->id,
        // Point at the local mock daemon (mock-daemon.mjs) so the console
        // and resource views render a live server during captures.
        'fqdn' => '127.0.0.1',
        'internal_fqdn' => '127.0.0.1',
        'scheme' => 'http',
        'behind_proxy' => false,
        'public' => true,
        'maintenance_mode' => false,
        'memory' => 65536,
        'memory_overallocate' => 0,
        'disk' => 1048576,
        'disk_overallocate' => 0,
        'upload_size' => 100,
        'daemon_token_id' => 'demo',
        // getDecryptedKey runs Encrypter::decrypt which unserializes the
        // payload, so this must be Crypt::encrypt output, not encryptString.
        'daemon_token' => Crypt::encrypt('screenshot-node-token-0123456789abcdef'),
        'daemonType' => 'comet',
        'daemonListen' => (int) getenv('MOCK_DAEMON_PORT') ?: 8898,
        'daemonSFTP' => 2022,
        'daemonBase' => '/var/lib/comet/volumes',
    ]
);

$nodeOverlay = Node::query()->firstOrCreate(
    ['name' => 'nebula-01'],
    [
        'uuid' => Uuid::uuid4()->toString(),
        'location_id' => $overlay->id,
        'fqdn' => 'nebula-01.mesh',
        'internal_fqdn' => '192.168.100.12',
        'scheme' => 'http',
        'behind_proxy' => false,
        'public' => true,
        'maintenance_mode' => false,
        'memory' => 32768,
        'memory_overallocate' => 0,
        'disk' => 524288,
        'disk_overallocate' => 0,
        'upload_size' => 100,
        'daemon_token_id' => 'demo2',
        'daemon_token' => Crypt::encrypt('screenshot-node-token-fedcba9876543210'),
        'daemonType' => 'comet',
        'daemonListen' => 8080,
        'daemonSFTP' => 2022,
        'daemonBase' => '/var/lib/comet/volumes',
    ]
);

$servers = [
    [
        'name' => 'Survival SMP',
        'egg' => 'Paper',
        'node' => $nodeMain,
        'ip' => '10.42.0.11',
        'port' => 25565,
        'desc' => 'Main survival world. Paper 1.21, whitelist only.',
        'image' => 'ghcr.io/pterodactyl/yolks:java_21',
        'startup' => 'java -Xms128M -Xmx{{SERVER_MEMORY}}M -jar {{SERVER_JARFILE}}',
        'memory' => 8192,
        'disk' => 61440,
        'cpu' => 200,
    ],
    [
        'name' => 'Proxy Edge',
        'egg' => 'Velocity',
        'node' => $nodeMain,
        'ip' => '10.42.0.11',
        'port' => 25577,
        'desc' => 'Velocity proxy in front of the network servers.',
        'image' => 'ghcr.io/pterodactyl/yolks:java_21',
        'startup' => 'java -Xms128M -Xmx{{SERVER_MEMORY}}M -jar {{SERVER_JARFILE}}',
        'memory' => 1024,
        'disk' => 10240,
        'cpu' => 100,
    ],
    [
        'name' => 'Modded Fabric',
        'egg' => 'Fabric',
        'node' => $nodeMain,
        'ip' => '10.42.0.11',
        'port' => 25570,
        'desc' => 'Create + Terralith modpack, seasonal world.',
        'image' => 'ghcr.io/pterodactyl/yolks:java_21',
        'startup' => 'java -Xms128M -Xmx{{SERVER_MEMORY}}M -jar {{SERVER_JARFILE}}',
        'memory' => 12288,
        'disk' => 81920,
        'cpu' => 300,
    ],
    [
        'name' => 'Rust Main',
        'egg' => 'Rust',
        'node' => $nodeMain,
        'ip' => '10.42.0.11',
        'port' => 28015,
        'desc' => 'Monthly wipe, 4k map, 100 slots.',
        'image' => 'ghcr.io/pterodactyl/yolks:rust_latest',
        'startup' => './RustDedicated -batchmode +server.port {{SERVER_PORT}}',
        'memory' => 16384,
        'disk' => 102400,
        'cpu' => 400,
    ],
    [
        'name' => 'Vintage Story',
        'egg' => 'Vintage Story',
        'node' => $nodeOverlay,
        'ip' => '192.168.100.12',
        'port' => 42420,
        'desc' => 'Co-op world reachable over the Nebula overlay.',
        'image' => 'ghcr.io/pterodactyl/yolks:dotnet_8',
        'startup' => './VintagestoryServer',
        'memory' => 6144,
        'disk' => 40960,
        'cpu' => 200,
    ],
    [
        'name' => 'TeamSpeak',
        'egg' => 'Teamspeak3 Server',
        'node' => $nodeOverlay,
        'ip' => '192.168.100.12',
        'port' => 9987,
        'desc' => 'Voice server for the group.',
        'image' => 'ghcr.io/pterodactyl/yolks:voice_teamspeak',
        'startup' => './ts3server',
        'memory' => 512,
        'disk' => 8192,
        'cpu' => 50,
    ],
];

$firstId = null;
$firstServer = null;
foreach ($servers as $spec) {
    $egg = Egg::query()->where('name', $spec['egg'])->firstOrFail();
    $node = $spec['node'];

    $allocation = Allocation::query()->create([
        'node_id' => $node->id,
        'ip' => $spec['ip'],
        'port' => $spec['port'],
    ]);

    $server = Server::factory()->create([
        'name' => $spec['name'],
        'description' => $spec['desc'],
        'owner_id' => $admin->id,
        'node_id' => $node->id,
        'allocation_id' => $allocation->id,
        'nest_id' => $egg->nest_id,
        'egg_id' => $egg->id,
        'memory' => $spec['memory'],
        'disk' => $spec['disk'],
        'cpu' => $spec['cpu'],
        'image' => $spec['image'],
        'startup' => $spec['startup'],
    ]);

    $allocation->update(['server_id' => $server->id]);

    $firstId ??= $server->uuidShort;
    $firstServer ??= $server;
}

// Extra allocations so the networking page shows a real list.
foreach ([25566, 25567, 8123] as $port) {
    Allocation::query()->create([
        'node_id' => $nodeMain->id,
        'server_id' => $firstServer->id,
        'ip' => '10.42.0.11',
        'port' => $port,
    ]);
}

// A daily restart schedule with one command task.
$schedule = Schedule::factory()->create([
    'server_id' => $firstServer->id,
    'name' => 'Daily restart',
    'cron_minute' => '0',
    'cron_hour' => '5',
    'cron_day_of_month' => '*',
    'cron_day_of_week' => '*',
    'is_active' => true,
    'is_processing' => false,
]);

Task::factory()->create([
    'schedule_id' => $schedule->id,
    'action' => 'command',
    'payload' => 'say Restarting in 60 seconds',
    'time_offset' => 0,
    'sequence_id' => 1,
]);

Task::factory()->create([
    'schedule_id' => $schedule->id,
    'action' => 'power',
    'payload' => 'restart',
    'time_offset' => 60,
    'sequence_id' => 2,
]);

// A database host plus databases for the two biggest servers.
$dbHost = DatabaseHost::factory()->create([
    'name' => 'db-01',
    'host' => '10.42.0.20',
    'port' => 3306,
    'username' => 'panel',
    'password' => 'panel',
    'node_id' => $nodeMain->id,
]);

foreach (['Survival SMP', 'Modded Fabric'] as $name) {
    $server = Server::query()->where('name', $name)->firstOrFail();
    Database::factory()->create([
        'server_id' => $server->id,
        'database_host_id' => $dbHost->id,
        'database' => 's' . $server->id . '_main',
        'username' => 'u' . $server->id . '_main',
    ]);
}

// Give the operator account subuser access to the Rust server.
$rust = Server::query()->where('name', 'Rust Main')->firstOrFail();
Pterodactyl\Models\Subuser::factory()->create([
    'server_id' => $rust->id,
    'user_id' => $operator->id,
    'permissions' => ['*'],
]);

Illuminate\Database\Eloquent\Model::reguard();

// The capture script reads this to find the console screenshot target.
fwrite(STDOUT, "SERVER_ID={$firstId}\n");
