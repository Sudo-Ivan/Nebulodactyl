<?php

namespace Pterodactyl\Services\Auth;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Pterodactyl\Exceptions\DisplayException;

/**
 * Minimal OIDC authorization-code client. The provider is discovered from
 * its well-known document and the code exchange runs server-to-server over
 * TLS, so identity claims are trusted via the back-channel plus iss/aud/exp
 * validation on the ID token.
 */
class OidcService
{
    /**
     * Build the provider authorization URL. The returned state and nonce
     * must be stored in the session and checked on the callback.
     *
     * @return array{url: string, state: string, nonce: string}
     */
    public function authorizationUrl(string $redirectUri): array
    {
        $discovery = $this->discover();
        $state = Str::random(32);
        $nonce = Str::random(32);

        $query = [
            'response_type' => 'code',
            'client_id' => config('oidc.client_id'),
            'redirect_uri' => $redirectUri,
            'scope' => implode(' ', config('oidc.scopes')),
            'state' => $state,
            'nonce' => $nonce,
        ];

        return [
            'url' => $discovery['authorization_endpoint'] . '?' . http_build_query($query),
            'state' => $state,
            'nonce' => $nonce,
        ];
    }

    /**
     * Exchange an authorization code for tokens and return the verified
     * identity claims: sub, email, preferred_username, name.
     *
     * @throws DisplayException
     */
    public function userFromCode(string $code, string $redirectUri, string $expectedNonce): array
    {
        $discovery = $this->discover();

        $response = Http::asForm()->post($discovery['token_endpoint'], [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => config('oidc.client_id'),
            'client_secret' => config('oidc.client_secret'),
        ]);

        if (!$response->ok()) {
            throw new DisplayException('The identity provider rejected the authorization code.');
        }

        $tokens = $response->json();
        $claims = $this->validateIdToken(Arr::get($tokens, 'id_token', ''), $expectedNonce);

        if (!empty($discovery['userinfo_endpoint']) && !empty($tokens['access_token'])) {
            $userinfo = Http::withToken($tokens['access_token'])
                ->get($discovery['userinfo_endpoint'])
                ->json();

            if (is_array($userinfo) && Arr::get($userinfo, 'sub') === $claims['sub']) {
                $claims = array_merge($claims, $userinfo);
            }
        }

        if (empty($claims['email'])) {
            throw new DisplayException('The identity provider did not return an email address. Add the email scope to OIDC_SCOPES.');
        }

        return $claims;
    }

    /**
     * Fetch and cache the provider discovery document.
     */
    protected function discover(): array
    {
        $issuer = rtrim((string) config('oidc.issuer'), '/');
        if (empty($issuer)) {
            throw new DisplayException('OIDC is not configured on this panel.');
        }

        return Cache::remember("oidc:discovery:$issuer", 3600, function () use ($issuer) {
            $response = Http::timeout(10)->get("$issuer/.well-known/openid-configuration");

            if (!$response->ok()) {
                throw new DisplayException('Could not fetch the OIDC discovery document. Check OIDC_ISSUER.');
            }

            $document = $response->json();
            foreach (['authorization_endpoint', 'token_endpoint'] as $key) {
                if (empty($document[$key])) {
                    throw new DisplayException("OIDC discovery document is missing $key.");
                }
            }

            return $document;
        });
    }

    /**
     * Decode the ID token payload and validate the claims that matter for a
     * confidential back-channel exchange: issuer, audience, expiry, nonce.
     * The token itself arrived over TLS directly from the issuer.
     *
     * @throws DisplayException
     */
    protected function validateIdToken(string $idToken, string $expectedNonce): array
    {
        $parts = explode('.', $idToken);
        if (count($parts) !== 3) {
            throw new DisplayException('The identity provider returned a malformed ID token.');
        }

        $claims = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
        if (!is_array($claims) || empty($claims['sub'])) {
            throw new DisplayException('The identity provider returned an invalid ID token.');
        }

        $issuer = rtrim((string) config('oidc.issuer'), '/');
        if (rtrim((string) ($claims['iss'] ?? ''), '/') !== $issuer) {
            throw new DisplayException('ID token issuer does not match the configured provider.');
        }

        $audience = Arr::wrap($claims['aud'] ?? []);
        if (!in_array(config('oidc.client_id'), $audience, true)) {
            throw new DisplayException('ID token was not issued for this panel.');
        }

        if (empty($claims['exp']) || $claims['exp'] < time()) {
            throw new DisplayException('ID token has expired.');
        }

        if (array_key_exists('nonce', $claims) && !hash_equals($expectedNonce, (string) $claims['nonce'])) {
            throw new DisplayException('ID token nonce mismatch.');
        }

        return $claims;
    }
}
