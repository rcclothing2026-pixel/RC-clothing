<?php

return [
    // When false, the website uses the no-op client + local cached stock.
    'enabled' => env('STOCKKEEPING_ENABLED', false),

    'base_url' => env('STOCKKEEPING_BASE_URL'),
    'api_key' => env('STOCKKEEPING_API_KEY'),

    // When true, the catalog import only creates/updates products that currently
    // have stock available in StoqS; out-of-stock products are skipped.
    'import_in_stock_only' => env('STOCKKEEPING_IMPORT_IN_STOCK_ONLY', true),

    // Stock locations (warehouses and/or shops) this site mirrors, as a CSV of
    // "<type>:<id>" tokens, e.g. "warehouse:1,shop:3". Availability shown on the
    // storefront is the SUM across these. Empty = whatever the API key allows.
    'locations' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('STOCKKEEPING_LOCATIONS', ''))
    ))),

    // The single location a paid order is reported to / decremented from. Must be
    // one of the tokens above. Defaults to the first location when unset.
    'fulfillment_location' => env('STOCKKEEPING_FULFILLMENT_LOCATION'),

    // Shared secret StoqS signs outgoing webhooks with (X-Stoqs-Signature).
    'webhook_secret' => env('STOCKKEEPING_WEBHOOK_SECRET'),

    // Legacy: the key itself identifies the brand/tenant now, so this is unused.
    'tenant_id' => env('STOCKKEEPING_TENANT_ID'),

    // Unique identifier for this Chiiaco instance. Sent as X-Instance-ID on all
    // outgoing requests so StoqS webhooks carry source_instance_id back, letting
    // us ignore our own echoes and prevent update loops.
    'instance_id' => env('STOCKKEEPING_INSTANCE_ID', 'chiiaco_' . md5(env('APP_URL', 'default'))),

    /*
    |--------------------------------------------------------------------------
    | Resilience & Performance
    |--------------------------------------------------------------------------
    |
    | Configuration for the ResilientStockKeepingClient which adds caching and
    | circuit breaker logic to prevent StoqS API issues from slowing down the site.
    |
    */

    'cache_ttl' => env('STOCKKEEPING_CACHE_TTL', 120), // seconds

    'circuit_breaker' => [
        'enabled' => env('STOCKKEEPING_CIRCUIT_BREAKER', true),
        'failure_threshold' => (int) env('STOCKKEEPING_FAILURE_THRESHOLD', 5),
        'reset_timeout' => (int) env('STOCKKEEPING_RESET_TIMEOUT', 60), // seconds
    ],
];
