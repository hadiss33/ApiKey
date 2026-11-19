<?php

return [

    /*
    |--------------------------------------------------------------------------
    | System Log Language
    |--------------------------------------------------------------------------
    | Labels for the activity log, error log and trace. Keys are the lower-cased enum case names.
    | Event types (types.*) are not listed here: the type code itself is used when a translation is missing.
    */

    // Messages returned to legacy Visa::addSystemReport callers
    'report_created' => 'An error occurred. Please inform the IT department. Tracking code: :code',
    'report_failed' => 'An error occurred. Please inform the IT department. Tracking code: 500',
    // Default message when a manual report carries no text
    'unspecified' => 'Report without a message',

    // Error notification text. SMS is short; bots (HTML) are fuller.
    'notify' => [
        'sms' => 'System error (:level - :category) ref: :ref path: :location message: :message',
        'bot' => "🔴 System error — :level\nCategory: :category | Source: :source\n:message\n\nPath: :location\nBranch: :branch | Occurrences: :count\nRef: :ref",
    ],

    // Access configuration and enforcement messages
    'access' => [
        'denied' => 'You do not have access to errors.',
        'manager_only' => 'Only the system manager can configure access.',
        'operator_not_found' => 'Operator not found.',
        'operator_not_eligible' => 'Only an active admin operator of the management branch can have access to errors.',
        'category_invalid' => 'Invalid category (at least one existing category is required).',
        'channel_invalid' => 'Invalid notification channel.',
        'cannot_remove_self' => 'You cannot remove your own access.',
    ],

    // Error category create/update
    'category' => [
        'invalid' => 'The category data is invalid.',
        'exists' => 'This category already exists.',
    ],

    // Panel API messages
    'api' => [
        'invalid_input' => 'The input is invalid.',
        'not_found' => 'Not found.',
        'server_error' => 'An internal error occurred.',
        'no_branch' => 'The request branch is unknown.',
        'key_only' => 'This endpoint can only be used with an API key.',
        'audit_not_recorded' => 'The activity log was not recorded. SystemLog internal recording is not enabled.',
    ],

    'levels' => [
        'debug' => 'Debug',
        'info' => 'Info',
        'warning' => 'Warning',
        'error' => 'Error',
        'critical' => 'Critical',
    ],

    'statuses' => [
        'new' => 'New',
        'investigating' => 'Investigating',
        'resolved' => 'Resolved',
        'ignored' => 'Ignored',
    ],

];
