<?php

/**
 * Addons ROG - Concurrent database changes
 *
 * - Signal stale model writes or unavailable application locks.
 * - Let the request lifecycle map these conflicts to a stable API error.
 */

declare(strict_types=1);
namespace Rog\Database;
class Conflict extends \RuntimeException {}
