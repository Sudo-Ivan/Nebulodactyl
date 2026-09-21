<?php

namespace Pterodactyl\Tests\Unit\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\Auth\OidcService;
use Pterodactyl\Tests\TestCase;

class OidcServiceTest extends TestCase
{
    private const ISSUER = 'https://idp.example.com';

    public function setUp(): void
    {
        parent::setUp();

        config()->set('oidc.enabled', true);
        config()->set('oidc.issuer', self::ISSUER);
        config()->set('oidc.client_id', 'panel-client');
        config()->set('oidc.client_secret', 'secret');
        config()->set('oidc.scopes', ['openid', 'profile', 'email']);

        Cache::forget('oidc:discovery:' . self::ISSUER);
    }

    private function fakeDiscovery(): void
    {
        Http::fake([
            self::ISSUER . '/.well-known/openid-configuration' => Http::response([
                'issuer' => self::ISSUER,
                'authorization_endpoint' => self::ISSUER . '/authorize',
                'token_endpoint' => self::ISSUER . '/token',
                'userinfo_endpoint' => self::ISSUER . '/userinfo',
            ]),
        ]);
    }

    private function idToken(array $claims): string
    {
        $encode = fn (array $data) => rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');

        return $encode(['alg' => 'RS256', 'typ' => 'JWT']) . '.' . $encode($claims) . '.fakesig';
    }

    public function testAuthorizationUrlBuildsFromDiscovery()
    {
        $this->fakeDiscovery();
        Http::preventStrayRequests();

        $result = $this->app->make(OidcService::class)->authorizationUrl('https://panel.test/auth/oidc/callback');

        $this->assertStringStartsWith(self::ISSUER . '/authorize?', $result['url']);
        $this->assertStringContainsString('client_id=panel-client', $result['url']);
        $this->assertStringContainsString('response_type=code', $result['url']);
        $this->assertStringContainsString('state=' . $result['state'], $result['url']);
        $this->assertStringContainsString('nonce=' . $result['nonce'], $result['url']);
    }

    public function testUserFromCodeReturnsClaims()
    {
        $this->fakeDiscovery();
        Http::fake([
            self::ISSUER . '/.well-known/openid-configuration' => Http::response([
                'issuer' => self::ISSUER,
                'authorization_endpoint' => self::ISSUER . '/authorize',
                'token_endpoint' => self::ISSUER . '/token',
                'userinfo_endpoint' => self::ISSUER . '/userinfo',
            ]),
            self::ISSUER . '/token' => Http::response([
                'access_token' => 'at-123',
                'id_token' => $this->idToken([
                    'iss' => self::ISSUER,
                    'aud' => 'panel-client',
                    'exp' => time() + 600,
                    'sub' => 'abc-123',
                    'nonce' => 'nonce-1',
                ]),
            ]),
            self::ISSUER . '/userinfo' => Http::response([
                'sub' => 'abc-123',
                'email' => 'user@example.com',
                'preferred_username' => 'jdoe',
            ]),
        ]);

        $claims = $this->app->make(OidcService::class)
            ->userFromCode('code-1', 'https://panel.test/auth/oidc/callback', 'nonce-1');

        $this->assertSame('abc-123', $claims['sub']);
        $this->assertSame('user@example.com', $claims['email']);
        $this->assertSame('jdoe', $claims['preferred_username']);
    }

    public function testRejectsWrongIssuer()
    {
        $this->fakeDiscovery();
        Http::fake([
            self::ISSUER . '/.well-known/openid-configuration' => Http::response([
                'issuer' => self::ISSUER,
                'authorization_endpoint' => self::ISSUER . '/authorize',
                'token_endpoint' => self::ISSUER . '/token',
                'userinfo_endpoint' => self::ISSUER . '/userinfo',
            ]),
            self::ISSUER . '/token' => Http::response([
                'access_token' => 'at-123',
                'id_token' => $this->idToken([
                    'iss' => 'https://evil.example.com',
                    'aud' => 'panel-client',
                    'exp' => time() + 600,
                    'sub' => 'abc-123',
                ]),
            ]),
        ]);

        $this->expectException(DisplayException::class);
        $this->app->make(OidcService::class)->userFromCode('code-1', 'https://panel.test/', Str::random(32));
    }

    public function testRejectsMissingEmail()
    {
        $this->fakeDiscovery();
        Http::fake([
            self::ISSUER . '/.well-known/openid-configuration' => Http::response([
                'issuer' => self::ISSUER,
                'authorization_endpoint' => self::ISSUER . '/authorize',
                'token_endpoint' => self::ISSUER . '/token',
                'userinfo_endpoint' => self::ISSUER . '/userinfo',
            ]),
            self::ISSUER . '/token' => Http::response([
                'access_token' => 'at-123',
                'id_token' => $this->idToken([
                    'iss' => self::ISSUER,
                    'aud' => 'panel-client',
                    'exp' => time() + 600,
                    'sub' => 'abc-123',
                    'nonce' => 'n',
                ]),
            ]),
            self::ISSUER . '/userinfo' => Http::response(['sub' => 'abc-123']),
        ]);

        $this->expectException(DisplayException::class);
        $this->app->make(OidcService::class)->userFromCode('code-1', 'https://panel.test/', 'n');
    }

    public function testOidcRouteIs404WhenDisabled()
    {
        config()->set('oidc.enabled', false);

        $this->get('/auth/oidc')->assertNotFound();
    }
}
