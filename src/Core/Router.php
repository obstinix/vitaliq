<?php

declare(strict_types=1);

namespace Vitaliq\Core;

/**
 * Small pattern router. Placeholders: {id} matches digits, {token} matches
 * URL-safe characters, {ref} matches a testing reference.
 */
final class Router
{
    /** @var list<array{method:string,regex:string,handler:array{0:class-string,1:string},middleware:list<string>,pattern:string}> */
    private array $routes = [];

    /** @param list<string> $middleware */
    public function get(string $pattern, array $handler, array $middleware = []): void
    {
        $this->add('GET', $pattern, $handler, $middleware);
    }

    /** @param list<string> $middleware */
    public function post(string $pattern, array $handler, array $middleware = []): void
    {
        $this->add('POST', $pattern, $handler, $middleware);
    }

    /** @param list<string> $middleware */
    public function add(string $method, string $pattern, array $handler, array $middleware = []): void
    {
        $regex = preg_replace_callback('/\{(\w+)\}/', static function (array $m): string {
            return match ($m[1]) {
                'id' => '(?P<id>[1-9][0-9]{0,9})',
                'token' => '(?P<token>[A-Za-z0-9_-]{20,128})',
                'ref' => '(?P<ref>[A-Za-z0-9-]{5,20})',
                default => '(?P<' . $m[1] . '>[^/]+)',
            };
        }, $pattern);
        $this->routes[] = [
            'method' => $method,
            'regex' => '#^' . $regex . '$#',
            'handler' => $handler,
            'middleware' => $middleware,
            'pattern' => $pattern,
        ];
    }

    /**
     * @return array{route:array{method:string,regex:string,handler:array{0:class-string,1:string},middleware:list<string>,pattern:string},params:array<string,string>}
     * @throws HttpException 404 or 405
     */
    public function match(string $method, string $path): array
    {
        $allowed = false;
        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $m)) {
                continue;
            }
            $verb = $method === 'HEAD' ? 'GET' : $method;
            if ($route['method'] !== $verb) {
                $allowed = true;
                continue;
            }
            $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            return ['route' => $route, 'params' => $params];
        }
        throw new HttpException($allowed ? 405 : 404);
    }

    /** @return list<array{method:string,pattern:string,middleware:list<string>}> */
    public function list(): array
    {
        return array_map(static fn ($r) => [
            'method' => $r['method'],
            'pattern' => $r['pattern'],
            'middleware' => $r['middleware'],
        ], $this->routes);
    }
}
