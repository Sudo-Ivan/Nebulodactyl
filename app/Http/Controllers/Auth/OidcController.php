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
use Pterodactyl\Services\Users\UserCreationService;

class OidcController extends Controller
{
    /**
     * OidcController constructor.
     */
    public function __construct(
        private OidcService $oidc,
        private UserCreationService $creationService,
    ) {
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
            return $this->syncAdminRole($user, $claims, false);
        }

        if ($user = User::query()->where('email', $claims['email'])->first()) {
            // Linking by email is only safe when the provider asserts that
            // it verified the address. Without that claim an IdP that lets
            // users pick arbitrary emails could hand out control of any
            // panel account, including admins.
            if (
                config('oidc.require_verified_email', true)
                && !filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN)
            ) {
                throw new DisplayException(
                    'Your identity provider did not confirm ownership of this email address. '
                    . 'Ask an administrator to link your account or enable email verification at the provider.'
                );
            }

            // Never overwrite a link to a different provider identity.
            if (!is_null($user->external_id) && $user->external_id !== $externalId) {
                throw new DisplayException('This account is already linked to a different identity provider.');
            }

            $user->update(['external_id' => $externalId]);

            return $this->syncAdminRole($user, $claims, true);
        }

        if (!config('oidc.auto_register')) {
            throw new DisplayException('No panel account is linked to this identity. Ask an administrator to create your account first.');
        }

        // No password is passed so the service generates one and sends the
        // new account email, which also gives the SSO user a way to set a
        // local password for API clients that do not support OIDC.
        $user = $this->creationService->handle([
            'external_id' => $externalId,
            'username' => $this->uniqueUsername($claims),
            'email' => $claims['email'],
            'name_first' => $claims['given_name'] ?? $claims['name'] ?? $claims['preferred_username'] ?? 'User',
            'name_last' => $claims['family_name'] ?? '',
            'password' => Str::random(64),
        ]);

        return $this->syncAdminRole($user, $claims, true);
    }

    /**
     * Map provider group membership onto the root_admin role. With sync
     * enabled the role mirrors the mapped groups on every login and is
     * removed when the user leaves them. Without sync, membership grants
     * the role once when the account is first linked or created and is
     * never revoked.
     */
    protected function syncAdminRole(User $user, array $claims, bool $firstLink): User
    {
        $adminGroups = config('oidc.admin_groups', []);
        if (empty($adminGroups)) {
            return $user;
        }

        $groups = $claims[config('oidc.groups_claim', 'groups')] ?? [];
        $isAdmin = is_array($groups) && !empty(array_intersect($groups, $adminGroups));

        if (config('oidc.sync_admin_role')) {
            if ($user->root_admin !== $isAdmin) {
                $user->update(['root_admin' => $isAdmin]);
            }
        } elseif ($firstLink && $isAdmin && !$user->root_admin) {
            $user->update(['root_admin' => true]);
        }

        return $user;
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
