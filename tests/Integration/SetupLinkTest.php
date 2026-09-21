<?php

namespace Pterodactyl\Tests\Integration;

use Pterodactyl\Models\User;
use Pterodactyl\Services\Setup\SetupLinkService;

/**
 * The open setup surface is only reachable while the users table is empty,
 * which other tests cannot guarantee in the shared test database. These tests
 * cover the deterministic half: once any account exists the routes must 404
 * no matter what key is presented, and the link command must refuse to run.
 * The key validation itself is covered by SetupLinkServiceTest.
 */
class SetupLinkTest extends IntegrationTestCase
{
    public function testSetupRoutesAreClosedOnceAUserExists()
    {
        User::factory()->create();

        $key = $this->app->make(SetupLinkService::class)->issue();

        $this->get("/setup?key={$key}")->assertNotFound();
        $this->post("/setup?key={$key}", [
            'email' => 'setup@example.com',
            'username' => 'setupadmin',
            'name_first' => 'Setup',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertNotFound();
    }

    public function testSetupLinkCommandFailsWhenUsersExist()
    {
        User::factory()->create();

        $this->artisan('p:setup:link')->assertFailed();
    }
}
