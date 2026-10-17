<?php

/**
 * Addons ROG - Route matching
 *
 * - Match HTTP methods and named path placeholders from the route registry.
 * - Distinguish missing resources from paths that require another method.
 */

declare(strict_types=1);
namespace Rog\Http;

class Router {
    public function __construct(private array $routes) {}
    /**
     * Populate decoded path parameters and return the matched service action.
     */
    public function match(Request $request): array {
        $methods = [];
        foreach ($this->routes as $route) {
            [$method,$path,$class,$action,$auth] = $route;
            // Escape literal path text before replacing named placeholders with captures.
            $pattern = preg_replace('/\\\\\{([A-Za-z]+)\\\\\}/', '(?P<$1>[^/]+)', preg_quote($path,'~'));
            if (!preg_match('~^' . $pattern . '$~D', $request->path, $matches)) continue;
            // Remember allowed methods so an existing path can return 405 instead of 404.
            if ($method !== $request->method) { $methods[] = $method; continue; }
            foreach ($matches as $name => $value) if (is_string($name)) {
                // Reject encoded separators and null bytes after decoding each path segment.
                $value = rawurldecode($value);
                if (str_contains($value,'/') || str_contains($value,"\0")) throw new ApiException(400,'BAD_REQUEST');
                $request->params[$name] = $value;
            }
            return ['class'=>$class,'action'=>$action,'auth'=>$auth,'status'=>$route[5] ?? 200];
        }
        throw new ApiException($methods ? 405 : 404, $methods ? 'METHOD_NOT_ALLOWED' : 'NOT_FOUND', [], $methods ? ['allow'=>implode(', ',array_unique($methods))] : []);
    }
}
