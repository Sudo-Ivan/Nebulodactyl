<?php

namespace Pterodactyl\Http\Controllers\Auth;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Pterodactyl\Models\User;
use Illuminate\Support\Facades\Auth;
use Pterodactyl\Facades\Activity;
use Illuminate\Http\RedirectResponse;
use Pterodactyl\Services\Auth\OidcService;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Http\Controllers\Controller;

class OidcController extends Controller
{
    /**
     * OidcController constructor.
     */
    public function __construct(private OidcService $oidc)
    {
    }

    /**
     * Redirect the browser to the configured identity provider.
     */
    public function redirect(Request $request): RedirectResponse
    {
        $this->assertEnabled();

        $authorization = $this->oidc->authorizationUrl(route('auth.oidc.callback'));

        $request->session()->put('oidc_state', $authorization['state']);
        $request->session()->put('oidc_nonce', $authorization['nonce']);

        return redirect()->away($authorization['url']);
    }

    /**
     * Handle the provider callback, resolve or create the local account,
     * and sign the user in.
     *
     * @throws DisplayException
     */
    public function callback(Request $request): RedirectResponse
    {
        $this->assertEnabled();

        if ($error = $request->query('error')) {
            throw new DisplayException('The identity provider returned an error: ' . $error);
        }

        $state = $request->session()->pull('oidc_state');
        $nonce = $request->session()->pull('oidc_nonce');
        if (empty($state) || !hash_equals($state, (string) $request->query('state', ''))) {
            throw new DisplayException('Invalid or expired sign-in state. Please try again.');
        }

        $claims = $this->oidc->userFromCode(
            (string) $request->query('code'),
            route('auth.oidc.callback'),
            (string) $nonce,
        );

        $user = $this->resolveUser($claims);

        Auth::guard()->login($user, true);
        $request->session()->regenerate();

        Activity::event('auth:login')
            ->subject($user)
            ->withRequestMetadata()
            ->property('method', 'oidc')
            ->log();

        return redirect()->intended('/');
    }

    /**
     * Find the local account for provider claims, linking by external_id
     * first and email second. Creates the account when auto-registration
     * is enabled.
     *
     * @throws DisplayException
     */
    protected function resolveUser(array $claims): User
    {
        $externalId = config('oidc.external_id_prefix') . $claims['sub'];

        if ($user = User::query()->where('external_id', $externalId)->first()) {
            return $user;
        }

        if ($user = User::query()->where('email', $claims['email'])->first()) {
            // Link the provider identity to the existing account.
            $user->update(['external_id' => $externalId]);

            return $user;
        }

        if (!config('oidc.auto_register')) {
            throw new DisplayException('No panel account is linked to this identity. Ask an administrator to create your account first.');
        }

        return User::query()->create([
            'uuid' => Str::uuid()->toString(),
            'external_id' => $externalId,
            'username' => $this->uniqueUsername($claims),
            'email' => $claims['email'],
            'name_first' => $claims['given_name'] ?? $claims['name'] ?? $claims['preferred_username'] ?? 'User',
            'name_last' => $claims['family_name'] ?? '',
            'password' => bcrypt(Str::random(64)),
            'language' => config('app.locale', 'en'),
            'root_admin' => false,
            'use_totp' => false,
        ]);
    }

    /**
     * Derive a unique username from the provider claims.
     */
    protected function uniqueUsername(array $claims): string
    {
        $base = preg_replace('/[^a-z0-9._-]/', '', strtolower(
            (string) ($claims['preferred_username'] ?? strtok((string) $claims['email'], '@'))
        )) ?: 'user';

        $username = $base;
        for ($i = 2; User::query()->where('username', $username)->exists(); $i++) {
            $username = $base . $i;
        }

        return $username;
    }

    /**
     * @throws DisplayException
     */
    protected function assertEnabled(): void
    {
        if (!config('oidc.enabled')) {
            abort(404);
        }
    }
}
