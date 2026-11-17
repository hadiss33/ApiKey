<?php

return [

    'groups' => [
        'system_log' => 'System log',
    ],

    // category = route prefix; levels: read (GET) and write (every other method)
    'categories' => [
        'system_log' => 'System log (errors, activity logs, access and categories)',
        'system_log_ingest' => 'Send errors and activity logs to the system log',
    ],

    'presets' => [
        'system_log_frontend' => 'System log: frontend (send errors + view)',
        'system_log_monitoring' => 'System log: monitoring (read only)',
        'system_log_full' => 'System log: full access',
    ],

    'auth' => [
        'invalid_key' => 'The API key is invalid.',
        'creator_inactive' => 'The operator who created this key is inactive or removed; the key does not work.',
        'source_denied' => 'This request did not come from an address or domain allowed for this key.',
        'scope_denied' => 'This key does not have the permission required for this action.',
        'too_many_failures' => 'Too many failed requests. Please try again later.',
        'rate_limited' => 'This key has reached its request limit. Please try again shortly.',
    ],

    'errors' => [
        'forbidden' => 'Only a branch administrator can manage API keys.',
        'not_found' => 'Key not found.',
        'invalid_input' => 'The submitted data is invalid.',
        'server_error' => 'An internal error occurred. Please try again.',
        'unknown_preset' => 'The selected preset does not exist.',
        'unknown_category' => 'One of the selected categories does not exist or is inactive.',
        'unknown_level' => 'One of the selected levels does not exist for that category.',
        'level_not_public' => 'A public key can only have levels allowed for frontends.',
        'grants_required' => 'Select at least one category with one level (read or write).',
        'invalid_type' => 'The key type is invalid.',
        'invalid_environment' => 'The key environment is invalid.',
        'max_keys' => 'Each branch can have at most :max active keys.',
        'revoked' => 'This key is revoked and cannot be changed.',
        'not_active' => 'Only an active key can be rotated. If it has expired, extend its expiry first.',
        'origins_only_public' => 'Allowed domains are only for public keys. Use IP restrictions for secret keys.',
        'origins_required' => 'Enter at least one allowed domain for a public key.',
        'too_many_origins' => 'At most :max domains are allowed per key.',
        'invalid_origin' => 'The domain ":value" is invalid. Example: https://shop.example.ir',
        'origin_taken' => 'The domain :value is already registered on another key. Each domain belongs to one key only.',
        'too_many_ips' => 'At most :max IP addresses are allowed per key.',
        'invalid_ip' => 'The IP address ":value" is invalid. Example: 203.0.113.7 or 203.0.113.0/24',
        'invalid_rate_limit' => 'The request limit must be between 1 and :max per minute.',
        'expiry_past' => 'The expiry date must be in the future.',
    ],
];
