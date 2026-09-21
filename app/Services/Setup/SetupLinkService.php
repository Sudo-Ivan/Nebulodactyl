<?php

namespace Pterodactyl\Services\Setup;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Cache\Repository;

/**
 * Issues and validates one-time setup links for the first-run admin flow.
 *
 * The /setup routes only exist while the users table is empty. On top of that,
 * this service requires a key in the URL so the link printed at install time is
 * the only door in. Keys are stored hashed in the cache and expire after one
 * hour, after which a new one can be minted with php artisan p:setup:link.
 */
class SetupLinkService
{
    public const CACHE_KEY = 'nebulodactyl:setup-link';

    public const TTL_SECONDS = 3600;

    public function __construct(private Repository $cache) {}

    /**
     * Mint a fresh setup key and return the raw token. Any previously issued
     * key is invalidated by overwriting the cached hash.
     */
    public function issue(): string
    {
        $token = Str::random(48);

        $this->cache->put(self::CACHE_KEY, hash('sha256', $token), self::TTL_SECONDS);

        return $token;
    }

    /**
     * The full setup URL for a raw token.
     */
    public function url(string $token): string
    {
        return rtrim(config('app.url'), '/') . '/setup?key=' . $token;
    }

    /**
     * True when the given token matches the currently issued key. Missing or
     * expired keys fail closed.
     */
    public function isValid(?string $token): bool
    {
        $stored = $this->cache->get(self::CACHE_KEY);

        return is_string($stored)
            && is_string($token)
            && $token !== ''
            && hash_equals($stored, hash('sha256', $token));
    }
}
