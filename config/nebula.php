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
    | each node. Nodes enroll through the remote API and the panel signs a
    | host certificate for them with the nebula-cert binary.
    |
    | The node agent (agent/ in this repository, nebulod) enrolls using the
    | node daemon token, writes the issued certificate, and supervises the
    | local nebula process.
    |
    */

    'enabled' => env('NEBULA_ENABLED', false),

    // Path to the nebula host configuration on this machine.
    'config_path' => env('NEBULA_CONFIG_PATH', '/etc/nebula/config.yml'),

    // Overlay CIDR assigned to the deployment (cert network range). Node
    // addresses are allocated sequentially from this range at enrollment.
    'overlay_cidr' => env('NEBULA_OVERLAY_CIDR', '10.42.0.0/16'),

    // Comma-separated list of lighthouse overlay IPs in static_host_map.
    'lighthouses' => array_filter(explode(',', env('NEBULA_LIGHTHOUSES', ''))),

    // UDP listen port for the nebula daemon on this host.
    'port' => (int) env('NEBULA_PORT', 4242),

    // Comma-separated Nebula cert groups applied to enrolled hosts.
    // Firewall rules can then allow node ports only within these groups.
    'groups' => array_filter(explode(',', env('NEBULA_GROUPS', 'nebulodactyl'))),

    // When true, panel-to-daemon HTTP requests prefer the node's overlay IP.
    // The daemon must listen on the overlay interface for this to work. TLS
    // certificates issued for the node fqdn will not cover the overlay IP,
    // so deployments that verify the daemon certificate should keep the
    // scheme as https only if the cert carries an IP SAN, or run the daemon
    // on plaintext over the overlay since the tunnel is already encrypted.
    'prefer_overlay' => env('NEBULA_PREFER_OVERLAY', true),

    'ca' => [
        // Path to the nebula-cert binary used to sign host certificates.
        'binary' => env('NEBULA_CERT_BINARY', 'nebula-cert'),

        // Storage location for the certificate authority pair. Initialize
        // with: php artisan nebula:init-ca
        'cert_path' => env('NEBULA_CA_CERT', storage_path('app/nebula/ca.crt')),
        'key_path' => env('NEBULA_CA_KEY', storage_path('app/nebula/ca.key')),

        // Friendly name embedded in the CA certificate.
        'name' => env('NEBULA_CA_NAME', 'Nebulodactyl'),

        // Validity periods for the CA and issued host certificates.
        'duration' => env('NEBULA_CA_DURATION', '87600h'),
        'cert_duration' => env('NEBULA_CERT_DURATION', '2160h'),
    ],

    // Address the node agent binds its status API to once the overlay is
    // up. Enrolled as a cert group rule target.
    'agent_port' => (int) env('NEBULA_AGENT_PORT', 9770),

    // Static host map entries handed to enrolling nodes, in the form
    // "overlay_ip:public_ip:port" comma separated.
    'static_hosts' => array_filter(explode(',', env('NEBULA_STATIC_HOSTS', ''))),

    // Firewall rules returned to enrolling nodes. Defaults allow the panel
    // group to reach the daemon and agent ports on nodes, and allow nodes
    // to answer ICMP plus outbound responses.
    'firewall' => [
        'outbound' => [
            ['port' => 'any', 'proto' => 'any', 'host' => 'any'],
        ],
        'inbound' => [
            ['port' => 'icmp', 'proto' => 'icmp', 'host' => 'any'],
            ['port' => '8080', 'proto' => 'tcp', 'groups' => ['nebulodactyl']],
            ['port' => '2022', 'proto' => 'tcp', 'groups' => ['nebulodactyl']],
            ['port' => '9770', 'proto' => 'tcp', 'groups' => ['nebulodactyl']],
        ],
    ],
];
