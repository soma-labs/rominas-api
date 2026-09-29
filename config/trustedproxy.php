<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted proxies
    |--------------------------------------------------------------------------
    |
    | Read by Laravel's TrustProxies middleware on every request. The public
    | voting frontend proxies /api/voting/* through its own Next.js server, so
    | without trusting that hop $request->ip() is the Next server's address and
    | every ballot's ip_hash would be identical (fraud detection groups on it).
    | Trusting it makes Laravel take the client IP from X-Forwarded-For.
    |
    | A comma-separated list of IPs / CIDR ranges (e.g. the Next host and, in
    | production, Cloudflare's published ranges), or `*` to trust the calling
    | address whatever it is. Only use `*` when the API is not reachable except
    | through a trusted proxy — otherwise anyone can forge X-Forwarded-For.
    | Unset (the default) trusts no proxy.
    |
    */

    'proxies' => env('TRUSTED_PROXIES'),

];
