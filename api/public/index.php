<?php

/**
 * Addons ROG - HTTP front controller
 *
 * - Capture each request and send the application response.
 * - Convert capture failures into the same API error format.
 */

declare(strict_types=1);

use Rog\App;
use Rog\Http\{Request, Response, ApiException};

// =============================================================================
// APPLICATION SETUP
// =============================================================================

$config = require dirname(__DIR__) . '/bootstrap.php';

// =============================================================================
// REQUEST DISPATCH AND ERROR HANDLING
// =============================================================================

try {
    $request = Request::capture($config); $response = (new App($config))->handle($request);
} catch (ApiException $error) {
    $request = new Request('GET', '/'); $response = Response::error($request, $error);
} catch (Throwable $error) {
    // Log unexpected details on the server; return a generic error to the client.
    error_log(get_class($error) . ': ' . $error->getMessage());
    $request = new Request('GET', '/'); $error = new ApiException(500, 'INTERNAL');
    $response = Response::error($request, $error);
}

// Emit headers, cookies, and the selected response body.
$response->send();
