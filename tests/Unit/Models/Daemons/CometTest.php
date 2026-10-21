<?php

namespace Pterodactyl\Tests\Unit\Models\Daemons;

use Pterodactyl\Models\Node;
use Pterodactyl\Models\Daemons\Comet;
use Pterodactyl\Tests\TestCase;
use Illuminate\Database\Eloquent\Collection;

class CometTest extends TestCase
{
    public function testGetConfigurationStripsTrailingSlashFromAllowedOrigins()
    {
        config(['app.url' => 'https://panel.example.com/']);

        $node = Node::factory()->make();
        $node->setRelation('mounts', new Collection());

        $configuration = (new Comet())->getConfiguration($node);

        $this->assertSame(['https://panel.example.com'], $configuration['allowed_origins']);
    }
}
