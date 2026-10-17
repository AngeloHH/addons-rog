<?php

/**
 * Addons ROG - Request lifecycle
 *
 * - Resolve routes, authorize requests, and dispatch service actions.
 * - Normalize errors and attach response cookies and request identifiers.
 */

declare(strict_types=1);

namespace Rog;

use PDOException;
use Rog\Database\{Db, Conflict};
use Rog\Http\{Request, Response, Router, ApiException};
use Rog\Services\{AuthService, InstallService};
use RuntimeException;
use Throwable;

class App {

    // =========================================================================
    // REQUEST STATE AND SHARED SERVICES
    // =========================================================================

    private ?Db $connection = null;
    public ?array $user = null;
    public ?array $session = null;
    public array $cookies = [];
    public Request $request;

    public function __construct(public array $config, private ?Router $router = null) {}
    // Open the database lazily so setup and health checks can run before installation.
    public function db(): Db { return $this->connection ??= new Db($this->config); }
    public function service(string $class): object { return new $class($this); }

    // =========================================================================
    // REQUEST DISPATCH
    // =========================================================================

    public function handle(Request $request): Response {
        $this->request = $request; $this->user = $this->session = null; $this->cookies = [];
        try {
            $route = ($this->router ?? new Router(require dirname(__DIR__) . '/routes.php'))->match($request);
            // Installer routes have their own authorization; other routes use session permissions.
            if ($route['auth'] === 'setup') (new InstallService($this))->authorize();
            elseif ($request->path !== '/health') {
                $error = 'ROG_SECRET must contain at least 32 characters';
                if (strlen($this->config['secret']) < 32) throw new RuntimeException($error);
                $auth = new AuthService($this); $auth->load_session(); $auth->authorize($route['auth']);
                if (!in_array($request->method, ['GET', 'HEAD', 'OPTIONS'], true)) $auth->check_csrf();
            }
            $result = $this->service($route['class'])->{$route['action']}();
            $response = $result instanceof Response ? $result : Response::ok($request, $result, $route['status']);
        } catch (ApiException $error) { $response = Response::error($request, $error); }
        catch (Conflict) { $response = Response::error($request, new ApiException(409, 'REVISION_MISMATCH')); }
        catch (PDOException $error) {
            $code = $error->errorInfo[0] ?? ''; $native = $error->errorInfo[1] ?? 0;
            // Translate database-specific constraint and locking failures into stable API codes.
            $mapped = match(true) {
                Db::unique_violation($error) => 'NAME_TAKEN',
                in_array($code, ['23503', '23514', '23502', '22P02', '23000', '45000'], true)
                    || in_array($native, [3819, 4025], true) => 'VALIDATION_FAILED',
                in_array($code, ['40001', '40P01', '55P03'], true)
                    || in_array($native, [5, 6, 1205, 1213], true) => 'STATE_CONFLICT',
                default => 'INTERNAL',
            };
            $status = $mapped === 'INTERNAL' ? 500 : ($mapped === 'VALIDATION_FAILED' ? 422 : 409);
            error_log('Database request ' . $request->request_id . ': ' . $code);
            $response = Response::error($request, new ApiException($status, $mapped));
        } catch (Throwable $error) {
            error_log('Request ' . $request->request_id . ': ' . get_class($error) . ' ' . $error->getMessage());
            $response = Response::error($request, new ApiException(500, 'INTERNAL'));
        }
        // Apply shared response metadata after both successful and failed actions.
        $response->cookies = array_merge($response->cookies, $this->cookies);
        $response->headers['X-Request-Id'] = $request->request_id;
        if ($response->status === 405) $response->headers['Allow'] =
            $response->body['error']['details']['allow'] ?? 'GET';
        return $response;
    }
}
