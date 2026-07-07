<?php
/**
 * Simple Router
 * Payment Gateway SaaS Multi Merchant
 */

class Router
{
    private static array $routes = [];

    /**
     * Register a GET route
     */
    public static function get(string $path, callable $handler): void
    {
        self::$routes['GET'][$path] = $handler;
    }

    /**
     * Register a POST route
     */
    public static function post(string $path, callable $handler): void
    {
        self::$routes['POST'][$path] = $handler;
    }

    /**
     * Register route for any method
     */
    public static function any(string $path, callable $handler): void
    {
        self::$routes['GET'][$path] = $handler;
        self::$routes['POST'][$path] = $handler;
    }

    /**
     * Dispatch the request
     */
    public static function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        // Remove trailing slash
        $uri = rtrim($uri, '/') ?: '/';
        
        if (isset(self::$routes[$method][$uri])) {
            call_user_func(self::$routes[$method][$uri]);
            return;
        }
        
        // Try with parameter matching
        foreach (self::$routes[$method] ?? [] as $route => $handler) {
            $pattern = preg_replace('/\{([^}]+)\}/', '([^/]+)', $route);
            $pattern = '#^' . $pattern . '$#';
            
            if (preg_match($pattern, $uri, $matches)) {
                array_shift($matches);
                call_user_func_array($handler, $matches);
                return;
            }
        }
        
        // 404
        http_response_code(404);
        if (is_ajax()) {
            json_response(['error' => 'Not Found'], 404);
        }
        echo '<!DOCTYPE html><html><head><title>404 Not Found</title></head><body><h1>404 - Page Not Found</h1></body></html>';
    }
}
