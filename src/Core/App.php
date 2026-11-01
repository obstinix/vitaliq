<?php

declare(strict_types=1);

namespace Vitaliq\Core;

use PDOException;
use RuntimeException;
use Throwable;
use Vitaliq\Auth\Auth;
use Vitaliq\Auth\Permissions;

/**
 * Application kernel: owns configuration, the database handle and the
 * request lifecycle. Services are created lazily and cached per request.
 */
final class App
{
    private static ?App $instance = null;

    public readonly Router $router;
    public readonly View $view;
    public readonly Logger $logger;
    public readonly Clock $clock;
    public string $requestId = '';

    private ?Session $session = null;
    private ?Request $request = null;
    private ?Csrf $csrf = null;
    private ?Auth $auth = null;
    /** @var array<class-string,object> */
    private array $services = [];

    /** @param array<string,mixed> $config */
    public function __construct(
        public readonly array $config,
        public readonly Database $db,
        ?Clock $clock = null,
    ) {
        $this->clock = $clock ?? new Clock();
        $this->router = new Router();
        $this->view = new View(VQ_ROOT . '/views');
        $this->logger = new Logger(VQ_ROOT . '/storage/logs');
        (require VQ_ROOT . '/src/routes.php')($this->router);
        self::$instance = $this;
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            throw new RuntimeException('Application has not been created.');
        }
        return self::$instance;
    }

    public static function hasInstance(): bool
    {
        return self::$instance !== null;
    }

    public function session(): Session
    {
        if ($this->session === null) {
            $storage = [];
            $this->session = new Session($storage);
        }
        return $this->session;
    }

    public function request(): Request
    {
        return $this->request ?? new Request('GET', '/');
    }

    public function csrf(): Csrf
    {
        return $this->csrf ??= new Csrf($this->session());
    }

    public function auth(): Auth
    {
        return $this->auth ??= new Auth($this);
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    public function service(string $class): object
    {
        if (!isset($this->services[$class])) {
            $this->services[$class] = new $class($this);
        }
        /** @var T */
        return $this->services[$class];
    }

    public function permissions(): Permissions
    {
        return $this->services[Permissions::class] ??= new Permissions($this);
    }

    public function can(string $permission): bool
    {
        return $this->permissions()->allows($this->auth()->user(), $permission);
    }

    public function rateLimiter(): RateLimiter
    {
        return $this->services[RateLimiter::class] ??= new RateLimiter($this->db, $this->clock);
    }

    public function mailer(): Mailer
    {
        return $this->services[Mailer::class] ??= new Mailer(
            (string) $this->config['mail']['driver'],
            (string) $this->config['mail']['from'],
            VQ_ROOT . '/storage/mail'
        );
    }

    /** Keyed hash of an IP address, so raw addresses are never stored. */
    public function ipHash(?string $ip = null): string
    {
        return hash_hmac('sha256', $ip ?? $this->request()->ip(), (string) $this->config['key']);
    }

    public function isProduction(): bool
    {
        return $this->config['env'] === 'production';
    }

    public function handle(Request $request, Session $session): Response
    {
        $this->request = $request;
        $this->session = $session;
        $this->csrf = new Csrf($session);
        $this->auth = new Auth($this);
        $this->requestId = bin2hex(random_bytes(6));
        unset($this->services[Permissions::class]);

        try {
            if (!str_starts_with($request->path, '/api/')) {
                $session->ageFlash();
            }
            $match = $this->router->match($request->method, $request->path);
            if ($request->method === 'POST' && !$this->csrf()->verify($request)) {
                throw new HttpException(419);
            }
            $this->auth()->enforceIdleTimeout((int) $this->config['session']['idle_minutes']);

            foreach ($match['route']['middleware'] as $middleware) {
                $blocked = $this->runMiddleware($middleware, $request);
                if ($blocked !== null) {
                    return $this->finalise($blocked, $request);
                }
            }

            [$class, $method] = $match['route']['handler'];
            $controller = new $class($this);
            $response = $controller->{$method}($request, ...array_values($match['params']));
        } catch (HttpException $e) {
            $response = $this->errorResponse($e->status, $e->getMessage(), $request);
        } catch (DatabaseUnavailable | PDOException $e) {
            $this->logger->exception($e, $this->requestId);
            $response = $this->errorResponse(503, HttpException::defaultMessage(503), $request);
        } catch (Throwable $e) {
            $this->logger->exception($e, $this->requestId);
            $message = $this->config['debug'] && !$this->isProduction()
                ? get_class($e) . ': ' . $e->getMessage()
                : HttpException::defaultMessage(500);
            $response = $this->errorResponse(500, $message, $request);
        }

        return $this->finalise($response, $request);
    }

    private function runMiddleware(string $middleware, Request $request): ?Response
    {
        $auth = $this->auth();
        [$name, $arg] = array_pad(explode(':', $middleware, 2), 2, '');

        if ($name === 'guest') {
            return $auth->check() ? Response::redirect(url($auth->homePath())) : null;
        }

        if ($name === 'auth' || $name === 'role') {
            if (!$auth->check()) {
                if ($request->wantsJson()) {
                    return Response::json(['error' => 'Authentication required.'], 401);
                }
                if ($request->method === 'GET') {
                    $this->session()->set('intended', $request->path);
                }
                $this->session()->flash('notice', 'Please sign in to continue.');
                return Response::redirect(url('/login'));
            }
            if ($name === 'role') {
                $roles = explode(',', $arg);
                if (!in_array($auth->role(), $roles, true)) {
                    throw new HttpException(403);
                }
            }
        }

        if ($name === 'perm') {
            if (!$auth->check()) {
                return $this->runMiddleware('auth', $request);
            }
            if (!$this->can($arg)) {
                throw new HttpException(403);
            }
        }
        return null;
    }

    public function errorResponse(int $status, string $message, Request $request): Response
    {
        if ($request->wantsJson()) {
            return Response::json(['error' => $message], $status);
        }
        try {
            $html = $this->view->render('errors/error', [
                'title' => $status === 404 ? 'Page not found' : 'Something needs attention',
                'status' => $status,
                'message' => $message,
                'currentUser' => null,
                'unreadCount' => 0,
                'requestId' => $this->requestId,
            ], 'public');
        } catch (Throwable $e) {
            $html = '<!doctype html><meta charset="utf-8"><title>Error</title><p>' . e($message) . '</p>';
        }
        return Response::html($html, $status);
    }

    private function finalise(Response $response, Request $request): Response
    {
        $headers = [
            'Content-Security-Policy' => "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'",
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ];
        if ($request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000';
        }
        foreach ($headers as $name => $value) {
            if ($response->header($name) === null) {
                $response->withHeader($name, $value);
            }
        }
        if ($response->header('Cache-Control') === null) {
            $response->withHeader('Cache-Control', 'no-store, private');
        }
        return $response;
    }
}
