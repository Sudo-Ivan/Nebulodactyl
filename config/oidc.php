<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OpenID Connect Single Sign-On
    |--------------------------------------------------------------------------
    |
    | Configure any OIDC compliant provider (Authentik, Keycloak, PocketID,
    | Authelia, Zitadel, Dex, Google Workspace). The issuer URL must serve a
    | discovery document at {issuer}/.well-known/openid-configuration.
    |
    | Register the panel at the provider with redirect URI:
    |   {APP_URL}/auth/oidc/callback
    |
    */
    'enabled' => env('OIDC_ENABLED', false),

    'issuer' => env('OIDC_ISSUER'),

    'client_id' => env('OIDC_CLIENT_ID'),

    'client_secret' => env('OIDC_CLIENT_SECRET'),

    // Label shown on the login page button, e.g. "Keycloak".
    'display_name' => env('OIDC_DISPLAY_NAME', 'SSO'),

    'scopes' => array_filter(explode(' ', env('OIDC_SCOPES', 'openid profile email'))),

    // When true, an account is created on first login for users whose email
    // does not match an existing panel account. When false, only existing
    // accounts linked by email or external_id may sign in.
    'auto_register' => env('OIDC_AUTO_REGISTER', true),

    // Existing accounts are linked to a provider identity by email only
    // when the provider asserts email_verified. Providers that let users
    // claim arbitrary email addresses without verification must not be
    // used for linking, or anyone could take over an account by claiming
    // its email address.
    'require_verified_email' => env('OIDC_REQUIRE_VERIFIED_EMAIL', true),

    // Attribute used to link provider identities to panel accounts.
    'external_id_prefix' => 'oidc:',
];
