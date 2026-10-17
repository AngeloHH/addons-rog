<?php

/**
 * Addons ROG - Structured API failures
 *
 * - Carry the HTTP status, stable error code, and optional validation details.
 * - Expose retry timing when a caller should delay its next attempt.
 */

declare(strict_types=1);

namespace Rog\Http;

use RuntimeException;

class ApiException extends RuntimeException {
    /**
     * Keep error metadata separate from the response serialization.
     */
    public function __construct(
        public int $status, public string $error_code, public array $fields = [],
        public array $details = [], public ?int $retry_after = null
    ) { parent::__construct($error_code); }
}
