<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
         | Local by Flywheel Live Link, ngrok, etc.: the tunnel sends X-Forwarded-Host and
         | X-Forwarded-Proto so redirects use https://your-tunnel-host. Without trusting the
         | proxy, Laravel keeps the internal URL (e.g. http://fleetiq.local).
         | Production: set TRUSTED_PROXIES to * or comma-separated IPs, or rely on Laravel Cloud defaults.
         | Disable: TRUSTED_PROXIES=false
         */
        $trustedProxies = $_ENV['TRUSTED_PROXIES'] ?? getenv('TRUSTED_PROXIES');
        if ($trustedProxies === false) {
            $trustedProxies = null;
        }
        // Cannot use app()->environment() here: middleware is registered before the app "env"
        // binding exists, which caused "Class env does not exist" on Live Link.
        if ($trustedProxies === null || $trustedProxies === '') {
            $appEnv = $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: '';
            if ($appEnv === 'local') {
                $trustedProxies = '*';
            }
        }
        if (is_string($trustedProxies) && strtolower($trustedProxies) !== 'false' && $trustedProxies !== '') {
            $middleware->trustProxies(at: strtolower($trustedProxies) === 'true' ? '*' : $trustedProxies);
        }

        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
