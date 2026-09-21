<?php

namespace Pterodactyl\Tests\Unit\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Services\Setup\SetupLinkService;
use Pterodactyl\Tests\TestCase;

class SetupLinkServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Cache::forget(SetupLinkService::CACHE_KEY);

        parent::tearDown();
    }

    public function testIssuedTokenIsValid()
    {
        $service = $this->app->make(SetupLinkService::class);

        $token = $service->issue();

        $this->assertSame(48, strlen($token));
        $this->assertTrue($service->isValid($token));
    }

    public function testRejectsWrongEmptyAndMissingTokens()
    {
        $service = $this->app->make(SetupLinkService::class);
        $service->issue();

        $this->assertFalse($service->isValid('not-the-key'));
        $this->assertFalse($service->isValid(''));
        $this->assertFalse($service->isValid(null));
    }

    public function testIssuingANewTokenInvalidatesTheOldOne()
    {
        $service = $this->app->make(SetupLinkService::class);

        $first = $service->issue();
        $second = $service->issue();

        $this->assertFalse($service->isValid($first));
        $this->assertTrue($service->isValid($second));
    }

    public function testTokenExpiresAfterOneHour()
    {
        $service = $this->app->make(SetupLinkService::class);
        $token = $service->issue();

        Carbon::setTestNow(Carbon::now()->addSeconds(SetupLinkService::TTL_SECONDS + 1));

        $this->assertFalse($service->isValid($token));
    }

    public function testUrlPointsAtSetupRouteWithKey()
    {
        $service = $this->app->make(SetupLinkService::class);

        $url = $service->url('abc123');

        $this->assertSame(rtrim(config('app.url'), '/') . '/setup?key=abc123', $url);
    }
}
