<?php

return [
    /*
    | Inactive accounts (app:mark-inactive-accounts): after this many days
    | without a login the person is asked by email whether they still need
    | the account, and it is marked for deletion. Empty turns the check off.
    */
    'inactive_after_days' => env('ACCOUNT_INACTIVE_AFTER_DAYS') !== null && env('ACCOUNT_INACTIVE_AFTER_DAYS') !== ''
        ? (int) env('ACCOUNT_INACTIVE_AFTER_DAYS')
        : null,

    /* Days the person has to log in after the email to keep the account. */
    'deletion_grace_days' => (int) env('ACCOUNT_DELETION_GRACE_DAYS', 30),
];
