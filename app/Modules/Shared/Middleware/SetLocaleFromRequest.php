<?php

declare(strict_types=1);

namespace Rominas\Shared\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the app locale from the client's `Accept-Language` header, so a user-facing error message
 * (`__()`-wrapped `ValidationException` text, `lang/*.json` strings) renders in the caller's language.
 * Falls back to `config('app.locale')` when the header is absent or names nothing in
 * `config('app.supported_locales')` — e.g. an admin client that sends no header stays on the default.
 */
class SetLocaleFromRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var list<string> $supported */
        $supported = config('app.supported_locales', []);

        $preferred = $request->getPreferredLanguage($supported);

        if ($preferred !== null) {
            App::setLocale($preferred);
        }

        return $next($request);
    }
}
