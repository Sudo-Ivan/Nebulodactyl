<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Nebula Overlay Network
    |--------------------------------------------------------------------------
    |
    | Optional integration for running panel-to-node traffic over a Nebula
    | overlay network instead of public addresses. Nebula provides mutually
    | authenticated, encrypted peer-to-peer tunnels between the panel and
    | each node. See examples/nebula/config.example.yml for a host config.
    |
    */

    'enabled' => env('NEBULA_ENABLED', false),

    // Path to the nebula host configuration on this machine.
    'config_path' => env('NEBULA_CONFIG_PATH', '/etc/nebula/config.yml'),

    // Overlay CIDR assigned to the panel deployment (cert network range).
    'overlay_cidr' => env('NEBULA_OVERLAY_CIDR', '10.42.0.0/16'),

    // Comma-separated list of lighthouse overlay IPs in static_host_map.
    'lighthouses' => array_filter(explode(',', env('NEBULA_LIGHTHOUSES', ''))),

    // UDP listen port for the nebula daemon on this host.
    'port' => (int) env('NEBULA_PORT', 4242),

    // Comma-separated Nebula cert groups applied to panel and node hosts.
    // Firewall rules can then allow node ports only within these groups.
    'groups' => array_filter(explode(',', env('NEBULA_GROUPS', 'nebulodactyl'))),

    // When true, panel-to-daemon HTTP requests prefer the node's overlay IP.
    'prefer_overlay' => env('NEBULA_PREFER_OVERLAY', true),
];
