<?php

/**
 * Addons ROG - Date and password validation tests
 *
 * - Reject impossible dates and distinguish password suffixes beyond bcrypt's input limit.
 */

expected_exception(
    fn() => Rog\Http\Validator::date('2026-02-30T12:00:00Z', 'date'), Rog\Http\ApiException::class,
    'Reject impossible calendar date'
);
$long = str_repeat('a', 72) . 'x';
check(
    !Rog\Support\Passwords::verify(str_repeat('a', 72) . 'y', Rog\Support\Passwords::hash($long)),
    'Password suffix beyond 72 bytes matters'
);
