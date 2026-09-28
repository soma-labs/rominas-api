<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Rominas\FraudMonitoring\Commands\DetectVotingFraudCommand;
use Rominas\Shared\Middleware\SetLocaleFromRequest;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        // Module commands live under app/Modules and are not auto-discovered — register them here.
        DetectVotingFraudCommand::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        // Records an audit-trail entry; attached to the /admin group (opt-out) and to the auth /
        // account-lifecycle routes it audits by name (opt-in). See config/audit.php.
        $middleware->alias([
            'audit' => \Rominas\Audit\Middleware\RecordAuditTrail::class,
        ]);

        // Translates user-facing error messages: sets the app locale from Accept-Language.
        $middleware->api(prepend: [SetLocaleFromRequest::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn(Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
