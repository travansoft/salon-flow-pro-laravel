<?php

use App\Http\Middleware\EnsureBranchRequest;
use App\Http\Middleware\EnsureSuperAdminRequest;
use App\Http\Middleware\EnsureTenantRequest;
use App\Http\Middleware\EnsureUserBelongsToTenant;
use App\Http\Middleware\ResolveBranch;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->web(prepend: [
            ResolveTenant::class,
        ]);

        $middleware->web(append: [
            EnsureUserBelongsToTenant::class,
            ResolveBranch::class,
        ]);

        $middleware->alias([
            'super_admin.only' => EnsureSuperAdminRequest::class,
            'tenant.only' => EnsureTenantRequest::class,
            'branch.only' => EnsureBranchRequest::class,
            'permission' => PermissionMiddleware::class,
            'role' => RoleMiddleware::class,
        ]);

        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->attributes->get('is_super_admin_request')) {
                $path = $request->getHost() === config('tenancy.main_domain') ? '/admin/login' : '/login';

                return $request->getSchemeAndHttpHost().$path;
            }

            if ($request->getHost() !== config('tenancy.main_domain')) {
                return $request->getSchemeAndHttpHost().'/login';
            }

            $slugPrefix = $request->attributes->get('tenant_slug_prefix');

            if ($slugPrefix) {
                return $request->getSchemeAndHttpHost()."/{$slugPrefix}/login";
            }

            return $request->getSchemeAndHttpHost().'/';
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
