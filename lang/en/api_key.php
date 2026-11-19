<?php

return [

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
        'not_found' => 'Not found.',
        'invalid_input' => 'The submitted data is invalid.',
        'server_error' => 'An internal error occurred. Please try again.',
        'unknown_category' => 'One of the selected categories does not exist, belongs to another branch, or is inactive.',
        'unknown_level' => 'The selected level is invalid (read or write).',
        'level_not_public' => 'A public key can only get categories whose rules are all read-only.',
        'grants_required' => 'Select at least one category.',
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

        // categories
        'category_rules_required' => 'Each category needs at least one rule (prefix + level).',
        'too_many_rules' => 'At most :max rules are allowed per category.',
        'too_many_categories' => 'Each branch can have at most :max categories.',
        'invalid_prefix' => 'The prefix is empty or invalid.',
        'prefix_forbidden' => 'This prefix is on the never-allowed list for keys and cannot be used in any category.',
        'prefix_unknown' => 'The prefix ":value" is not in the list of selectable routes.',
        'duplicate_prefix' => 'A prefix can appear only once in a category.',
        'category_has_keys' => 'This category is attached to keys and cannot be deleted. Deactivate it or detach it from the keys first.',
    ],
];
