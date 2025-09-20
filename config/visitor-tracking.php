<?php

return [
    /**
     * The type of user ID column to create in the migration.
     * Options: 'uuid', 'ulid', 'bigInteger'
     */
    'id_type' => 'bigInteger',

    /**
     * Optional: You can customize the database connection.
     */
    'db_connection_name' => null,

    /**
     * Optional: Provide a custom prefix for the table names.
     */
    'table_prefix' => null,

    /**
     * The queue on which the tracking job should be executed.
     */
    'queue_name' => null,

    /**
     * The queue connection on which the tracking job should be executed.
     */
    'queue_connection' => null,

    /**
     * If the tracking job should use the dispatchAfterResponse() method.
     * Some environments like AWS lambda do not support dispatchAfterResponse().
     * Falls back to dispatch() when disabled.
     */
    'queue_dispatch_after_response' => true,

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
     * Optional: You can exclude paths using a uri or pattern. Example: /admin/* or /api/*
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

];
