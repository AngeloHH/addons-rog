<?php

/**
 * Addons ROG - Temporary test result paths
 *
 * - Write generated reports under the runner-provided results directory.
 * - Create the private destination safely when multiple suites start together.
 */

declare(strict_types=1);

function test_result_path(string $name): string {
    $directory = getenv('ROG_TEST_RESULTS');
    if (!$directory) throw new RuntimeException('Run tests through tests/run.php to configure the results directory');
    $error = 'Cannot create test results directory';
    if (
        !is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)
    ) throw new RuntimeException($error);
    return $directory . '/' . $name;
}
