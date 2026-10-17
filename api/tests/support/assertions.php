<?php

/**
 * Addons ROG - Shared test assertions
 *
 * - Record named successful checks and fail immediately on a false condition.
 * - Verify expected exception classes without accepting a missing exception.
 */

function check(bool $condition, string $name): void {
    global $checks;
    if (!$condition) throw new RuntimeException('FAILED: ' . $name);
    $checks[] = $name;
}

function expected_exception(callable $call, string $class, string $name): void {
    try { $call(); } catch (Throwable $error) {
        check($error instanceof $class, $name . ' exception class: ' . get_class($error));
        return;
    }
    check(false, $name);
}
