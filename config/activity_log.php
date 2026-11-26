<?php

return [
    /*
    | Days an activity log entry is kept before model:prune deletes it.
    | Empty keeps entries forever.
    */
    'retention_days' => env('ACTIVITY_LOG_RETENTION_DAYS') !== null && env('ACTIVITY_LOG_RETENTION_DAYS') !== ''
        ? (int) env('ACTIVITY_LOG_RETENTION_DAYS')
        : null,

    /*
    | Days a sign-in (login.*) is kept, usually shorter than the rest.
    | Empty applies retention_days to sign-ins too.
    */
    'login_retention_days' => env('ACTIVITY_LOG_LOGIN_RETENTION_DAYS') !== null && env('ACTIVITY_LOG_LOGIN_RETENTION_DAYS') !== ''
        ? (int) env('ACTIVITY_LOG_LOGIN_RETENTION_DAYS')
        : null,
];
