<?php

return [

    'exclude' => [
        'directories' => [
            // '/home/',
        ],
    ],

    'verify_ssl_certificate' => true,

    // Cron expressions for the sync commands, e.g. '0 3 * * *'. Null disables scheduling.
    'schedule' => [
        'sync' => null,
        'sync_new' => null,
    ],

];
