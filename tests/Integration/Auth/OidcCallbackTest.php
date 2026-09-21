<?php

namespace Pterodactyl\Tests\Integration\Auth;

use Illuminate\Support\Facades\Http;
use Pterodactyl\Models\User;
use Pterodactyl\Tests\Integration\IntegrationTestCase;

class OidcCallbackTest extends IntegrationTestCase
{
    private const ISSUER = 'https://idp.example.com';
    private const CALLBACK = '/auth/oidc/callback';

    private array $userinfo = [];
    private string $subject = 'subject-1';

    private function fakeProvider(): void
    {
        $encode = fn (array $data) => rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');

        // The userinfo response is resolved lazily so tests can change the
        // claims between requests without stacking a second fake.
        Http::fake([
            self::ISSUER . '/.well-known/openid-configuration' => Http::response([
                'issuer' => self::ISSUER,
                'authorization_endpoint' => self::ISSUER . '/authorize',
                'token_endpoint' => self::ISSUER . '/token',
                'userinfo_endpoint' => self::ISSUER . '/userinfo',
            ]),
            self::ISSUER . '/token' => fn () => Http::response([
                'access_token' => 'at-123',
                'id_token' => $encode(['alg' => 'RS256', 'typ' => 'JWT']) . '.' . $encode([
                    'iss' => self::ISSUER,
                    'aud' => 'panel-client',
                    'exp' => time() + 600,
                    'sub' => $this->subject,
                    'nonce' => 'nonce-1',
                ]) . '.fakesig',
            ]),
            self::ISSUER . '/userinfo' => fn () => Http::response(
                array_merge(['sub' => $this->subject], $this->userinfo)
            ),
        ]);
    }

    public function setUp(): void
    {
        parent::setUp();

        config()->set('oidc.enabled', true);
        config()->set('oidc.issuer', self::ISSUER);
        config()->set('oidc.client_id', 'panel-client');
        config()->set('oidc.client_secret', 'secret');
        config()->set('oidc.require_verified_email', true);
    }

    private function callbackRequest()
    {
        return $this->withSession(['oidc_state' => 'state-1', 'oidc_nonce' => 'nonce-1'])
            ->get(self::CALLBACK . '?state=state-1&code=code-1');
    }

    public function testCallbackRejectsMismatchedState()
    {
        $this->fakeProvider();
        $this->withSession(['oidc_state' => 'state-1', 'oidc_nonce' => 'nonce-1'])
            ->get(self::CALLBACK . '?state=wrong&code=code-1')
            ->assertStatus(400);

        $this->assertGuest();
    }

    public function testCallbackLinksExistingUserOnlyWhenEmailVerified()
    {
        $this->fakeProvider();
        $user = User::factory()->create(['email' => 'admin@example.com']);

        // Without email_verified the provider could be handing out control
        // of any account, so linking must be refused.
        $this->userinfo = ['email' => 'admin@example.com'];
        $this->callbackRequest();
        $this->assertGuest();
        $this->assertNull($user->fresh()->external_id);

        // With a verified assertion the link is allowed.
        $this->userinfo = ['email' => 'admin@example.com', 'email_verified' => true];
        $this->callbackRequest();
        $this->assertAuthenticated();
        $this->assertSame('oidc:' . $this->subject, $user->fresh()->external_id);
    }

    public function testCallbackRefusesToOverwriteDifferentExternalId()
    {
        $this->subject = 'subject-overwrite';
        $this->fakeProvider();

        $user = User::factory()->create([
            'email' => 'user@example.com',
            'external_id' => 'ldap:other-subject',
        ]);

        $this->userinfo = ['email' => 'user@example.com', 'email_verified' => true];
        $this->callbackRequest();

        $this->assertGuest();
        $this->assertSame('ldap:other-subject', $user->fresh()->external_id);
    }

    public function testCallbackRegistersNewUserWhenAutoRegisterEnabled()
    {
        $this->subject = 'subject-register';
        $this->fakeProvider();
        config()->set('oidc.auto_register', true);
        $this->userinfo = [
            'email' => 'new@example.com',
            'email_verified' => true,
            'preferred_username' => 'newbie',
        ];

        $this->callbackRequest()->assertRedirect();

        $user = User::query()->where('email', 'new@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('oidc:subject-register', $user->external_id);
        $this->assertFalse($user->root_admin);
        $this->assertAuthenticatedAs($user);
    }

    public function testCallbackRejectsUnknownUserWhenAutoRegisterDisabled()
    {
        $this->subject = 'subject-ghost';
        $this->fakeProvider();
        config()->set('oidc.auto_register', false);
        $this->userinfo = [
            'email' => 'ghost@example.com',
            'email_verified' => true,
        ];

        $this->callbackRequest();
        $this->assertGuest();
        $this->assertNull(User::query()->where('email', 'ghost@example.com')->first());
    }

    public function testGroupMemberIsGrantedRootAdminOnFirstLogin()
    {
        $this->subject = 'subject-admin';
        $this->fakeProvider();
        config()->set('oidc.auto_register', true);
        config()->set('oidc.admin_groups', ['panel-admins']);
        $this->userinfo = [
            'email' => 'newadmin@example.com',
            'email_verified' => true,
            'groups' => ['panel-admins', 'users'],
        ];

        $this->callbackRequest()->assertRedirect();

        $user = User::query()->where('email', 'newadmin@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->root_admin);
    }

    public function testNonMemberIsNotGrantedRootAdmin()
    {
        $this->subject = 'subject-member';
        $this->fakeProvider();
        config()->set('oidc.auto_register', true);
        config()->set('oidc.admin_groups', ['panel-admins']);
        $this->userinfo = [
            'email' => 'member@example.com',
            'email_verified' => true,
            'groups' => ['users'],
        ];

        $this->callbackRequest()->assertRedirect();

        $user = User::query()->where('email', 'member@example.com')->first();
        $this->assertNotNull($user);
        $this->assertFalse($user->root_admin);
    }

    public function testGroupMemberIsGrantedRootAdminWhenLinkingByEmail()
    {
        $this->subject = 'subject-link-admin';
        $this->fakeProvider();
        config()->set('oidc.admin_groups', ['panel-admins']);

        $user = User::factory()->create(['email' => 'linked@example.com']);

        $this->userinfo = [
            'email' => 'linked@example.com',
            'email_verified' => true,
            'groups' => ['panel-admins'],
        ];
        $this->callbackRequest();

        $this->assertAuthenticated();
        $this->assertTrue($user->fresh()->root_admin);
    }

    public function testSyncAdminRoleRemovesRootAdminWhenGroupsNoLongerMatch()
    {
        $this->subject = 'subject-ex-admin';
        $this->fakeProvider();
        config()->set('oidc.sync_admin_role', true);
        config()->set('oidc.admin_groups', ['panel-admins']);

        $user = User::factory()->create([
            'external_id' => 'oidc:' . $this->subject,
            'root_admin' => true,
        ]);

        $this->userinfo = [
            'email' => $user->email,
            'email_verified' => true,
            'groups' => ['users'],
        ];
        $this->callbackRequest();

        $this->assertAuthenticatedAs($user);
        $this->assertFalse($user->fresh()->root_admin);
    }

    public function testSyncAdminRoleGrantsRootAdminOnLogin()
    {
        $this->subject = 'subject-sync-admin';
        $this->fakeProvider();
        config()->set('oidc.sync_admin_role', true);
        config()->set('oidc.admin_groups', ['panel-admins']);

        $user = User::factory()->create([
            'external_id' => 'oidc:' . $this->subject,
        ]);

        $this->userinfo = [
            'email' => $user->email,
            'email_verified' => true,
            'groups' => ['panel-admins'],
        ];
        $this->callbackRequest();

        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->fresh()->root_admin);
    }

    public function testWithoutSyncAdminRoleRootAdminIsNeverRemoved()
    {
        $this->subject = 'subject-keep-admin';
        $this->fakeProvider();
        config()->set('oidc.sync_admin_role', false);
        config()->set('oidc.admin_groups', ['panel-admins']);

        $user = User::factory()->create([
            'external_id' => 'oidc:' . $this->subject,
            'root_admin' => true,
        ]);

        $this->userinfo = [
            'email' => $user->email,
            'email_verified' => true,
            'groups' => ['users'],
        ];
        $this->callbackRequest();

        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->fresh()->root_admin);
    }
}
