<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Branding
    |--------------------------------------------------------------------------
    |
    | Rebranding knobs for the client UI. The panel name comes from APP_NAME
    | and the logo from the admin settings page, these values cover the rest.
    | All values are optional, unset values fall back to the defaults in the
    | stylesheet.
    |
    */

    // Accent color used for primary actions, links and highlights. Any CSS
    // color value works, hex recommended.
    'accent' => env('PANEL_ACCENT'),

    // Text color rendered on top of the accent color.
    'accent_foreground' => env('PANEL_ACCENT_FOREGROUND'),

    // Default color theme for new visitors: dark, light or system.
    'default_theme' => env('PANEL_DEFAULT_THEME', 'dark'),
];
