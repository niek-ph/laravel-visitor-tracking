<?php

use NiekPH\LaravelVisitorTracking\Models\Visitor;
use NiekPH\LaravelVisitorTracking\Models\VisitorEvent;

return [
    /**
     * The name of the cookie that is used to track the visitor.
     */
    'cookie_name' => 'visitor_tag',

    /**
     * The duration of the visitor cookie in seconds.
     */
    'cookie_duration' => (60 * 60 * 24) * 365,

    /**
     * Whether client hints are enabled for better device detection. See: https://developer.mozilla.org/en-US/docs/Web/HTTP/Guides/Client_hints
     */
    'enable_client_hints' => true,

    /**
     * The request header with the visitor's country code (ISO 3166-1 alpha-2), set by a proxy in front of
     * the application, like Cloudflare's CF-IPCountry. Stored as the visitor's country without any lookup.
     * Leave null to not read a country header.
     */
    'country_header' => 'CF-IPCountry',

    /**
     * The request header with the visitor's ip address, set by a proxy in front of the application, like
     * Cloudflare's CF-Connecting-IP. Only set this when every request passes through that proxy: a client
     * that reaches the application directly can send the header itself. When the header is missing or
     * invalid, $request->ip() is used. Leave null to always use $request->ip().
     */
    'ip_address_header' => null,

    /**
     * Whether gathering geo location for the ip address is enabled. Off by default: the lookup sends the
     * (anonymized) ip address to a third party. Uses https://seeip.org/ for collecting basic ip geo information.
     * The country from `country_header` takes precedence over the country of this lookup.
     */
    'enable_geo_ip_lookup' => false,

    /**
     * Whether the ip address is anonymized before it is queued or stored: the last byte of an IPv4
     * address (a /24 network) and the last 80 bits of an IPv6 address (a /48 network) are set to zero.
     */
    'anonymize_ip_address' => true,

    /**
     * Whether bots (crawlers, uptime monitors, link previews) are ignored entirely: no visitor and no
     * events are stored for them.
     */
    'ignore_bots' => true,

    /**
     * Whether a visitor is only stored once its browser sends the visitor cookie back. Clients that never
     * keep cookies, like most bots, then never create a visitor. The page views of the first request are
     * held in the cache and stored when the visitor returns within `pending_page_view_ttl` seconds.
     * Any other event (and VisitorTracking::identify()) stores the visitor right away.
     */
    'require_returning_visitor' => true,

    /**
     * How long, in seconds, the page views of a visitor that has not returned the cookie yet are held.
     */
    'pending_page_view_ttl' => 30 * 60,

    /**
     * Optional: page views of the same visitor and url are tracked only once within this number of
     * seconds, for example 30 * 60. Reloads and back navigation then do not count as new page views.
     * Leave null to track every page view.
     */
    'deduplicate_page_views_for' => null,

    /**
     * The cache store for pending page views and page view deduplication. Leave null for the default store.
     */
    'cache_store' => null,

    /**
     * Which part of the url is stored with each event.
     * Options: 'full' (scheme, host, path and query), 'path_with_query' or 'path'.
     */
    'url_format' => 'full',

    /**
     * Optional: You can exclude paths for the TrackPageView middleware using a uri or pattern. Example: /admin/* or /api/*
     */
    'excluded_paths' => [],

    /**
     * User ID configuration
     */
    'users' => [
        /**
         * The type of user ID column to create in the migration.
         * Options: 'uuid', 'ulid', 'bigInteger'
         */
        'id_type' => 'bigInteger',

        /**
         * The model a visitor is linked to. Leave null to use the model of the default user provider.
         */
        'model' => null,

        /**
         * The auth guard of the users that visitors are linked to. Leave null to use the default guard.
         */
        'guard' => null,

        /**
         * The users table name (for foreign key constraint).
         * Leave null to use default 'users' table.
         */
        'table' => 'users',

        /**
         * The column name on the users table that this references.
         * Leave null to use default 'id' column.
         */
        'column' => 'id',
    ],

    /**
     * You can optionally override the model classes.
     */
    'models' => [
        'visitor' => Visitor::class,
        'visitor_event' => VisitorEvent::class,
    ],

    'database' => [
        /**
         * The type of user ID column to create in the migration.
         * Options: 'uuid', 'ulid', 'bigInteger'
         */
        'id_type' => 'bigInteger',

        /**
         * Optional: You can customize the database connection.
         */
        'connection' => null,

        /**
         * You can optionally override the table names.
         */
        'tables' => [
            'visitors' => 'visitors',
            'visitor_events' => 'visitor_events',
        ],
    ],

    'queue' => [
        /**
         * The queue on which the tracking job should be executed.
         */
        'name' => null,

        /**
         * The queue connection on which the tracking job should be executed.
         */
        'connection' => null,
    ],
];
