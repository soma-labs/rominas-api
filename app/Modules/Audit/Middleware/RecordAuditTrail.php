<?php

declare(strict_types=1);

namespace Rominas\Audit\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Rominas\Audit\Model\AuditLog;
use Rominas\Audit\Support\AuditHasher;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records an audit-trail row for the actions we care about. It logs in `terminate()` — after the response
 * is sent (no added latency) and off the final, rendered response, so failed and exception responses are
 * recorded with their status code too.
 *
 * Coverage combines two rules (see config/audit.php):
 *   - Opt-out for the /admin group: every state-changing request (POST/PUT/PATCH/DELETE) is logged unless
 *     its route name is in `ignore`; reads (GET) only when listed in `audited_reads`.
 *   - Opt-in everywhere else: only route names in `audited` are logged (auth + account lifecycle events).
 *
 * The actor is polymorphic: the authenticated model (admin `User` or academy `Member`, mapped to a stable
 * alias by `causer_types`), or — for a login, where no actor is authenticated during the request — the id
 * recovered from the success response body via `actor_from_response`. Unauthenticated attempts have no
 * actor; the attempted email is hashed (never stored plaintext) on the routes in `hash_emails_on`.
 *
 * Actions may enrich the entry without a dependency by pushing data into Laravel's request Context under
 * the `audit` key (e.g. `Context::add('audit', ['changes' => $model->getChanges()])`); it is merged into
 * the stored `context`. This is the path to richer old→new diffs later, with no change here.
 */
class RecordAuditTrail
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $route = $request->route();

        if (! $route instanceof Route) {
            return;
        }

        $name = $route->getName();

        if (! $this->shouldAudit($request, $name)) {
            return;
        }

        [$causerType, $causerId, $causerLabel] = $this->resolveActor($request, $response, $name);
        [$subjectType, $subjectId] = $this->resolveSubject($route);

        AuditLog::query()->create([
            'causer_type' => $causerType,
            'causer_id' => $causerId,
            'causer_label' => $causerLabel,
            'action' => $name ?? ($request->method() . ' ' . $request->path()),
            'method' => $request->method(),
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'status_code' => $response->getStatusCode(),
            'context' => $this->buildContext($request, $name),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 497) ?: null,
        ]);
    }

    private function shouldAudit(Request $request, ?string $name): bool
    {
        if ($name === null) {
            return false;
        }

        if (in_array($name, $this->config('ignore'), true)) {
            return false;
        }

        // Explicit opt-in (any method) — auth and account-lifecycle routes outside /admin.
        if (in_array($name, $this->config('audited'), true)) {
            return true;
        }

        // Admin group: opt-out for writes, opt-in for the sensitive reads.
        if (str_starts_with($name, 'api.admin.')) {
            if (in_array(strtoupper($request->method()), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                return true;
            }

            return in_array($name, $this->config('audited_reads'), true);
        }

        return false;
    }

    /**
     * The actor as [type, id, label]. The authenticated model when there is one; otherwise, for a login,
     * the id recovered from the success response. [null, null, null] for an anonymous attempt.
     *
     * @return array{0: ?string, 1: ?int, 2: ?string}
     */
    private function resolveActor(Request $request, Response $response, ?string $name): array
    {
        $user = $request->user();

        if ($user instanceof Model) {
            $label = $user->getAttribute('name');

            return [$this->causerTypeFor($user), $this->intKey($user->getKey()), is_string($label) ? $label : null];
        }

        /** @var array<string, array{type?: string, field?: string}> $map */
        $map = config('audit.actor_from_response', []);

        if ($name !== null && isset($map[$name]) && $response->getStatusCode() < 300) {
            $id = $this->idFromResponse($response, $map[$name]['field'] ?? null);

            if ($id !== null) {
                return [$map[$name]['type'] ?? null, $id, null];
            }
        }

        return [null, null, null];
    }

    private function causerTypeFor(Model $user): string
    {
        /** @var array<class-string, string> $map */
        $map = config('audit.causer_types', []);

        foreach ($map as $class => $alias) {
            if ($user instanceof $class) {
                return $alias;
            }
        }

        return Str::snake(class_basename($user));
    }

    private function idFromResponse(Response $response, ?string $field): ?int
    {
        if ($field === null) {
            return null;
        }

        $content = $response->getContent();

        if (! is_string($content) || $content === '') {
            return null;
        }

        $data = json_decode($content, true);

        if (! is_array($data)) {
            return null;
        }

        $value = $data[$field] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    private function intKey(mixed $key): ?int
    {
        return is_numeric($key) ? (int) $key : null;
    }

    /**
     * The most specific bound Eloquent model on the route (the last model parameter): its parameter name
     * and key. Returns [null, null] when the route binds no model (e.g. a top-level create).
     *
     * @return array{0: ?string, 1: ?int}
     */
    private function resolveSubject(Route $route): array
    {
        $subjectType = null;
        $subjectId = null;

        foreach ($route->parameters() as $name => $value) {
            if ($value instanceof Model) {
                $subjectType = $name;
                $subjectId = $this->intKey($value->getKey());
            }
        }

        return [$subjectType, $subjectId];
    }

    /**
     * The redacted request payload plus any Context-supplied extras, size-capped. On auth routes the
     * attempted email is hashed into `email_hash` instead of being redacted away.
     *
     * @return array<string, mixed>
     */
    private function buildContext(Request $request, ?string $name): array
    {
        $payload = $this->redact($request->all(), $this->config('redact'));

        if ($name !== null && in_array($name, $this->config('hash_emails_on'), true)) {
            $email = $request->input('email');

            if (is_string($email) && $email !== '') {
                $payload['email_hash'] = AuditHasher::emailHash($email);
            }
        }

        $context = ['request' => $payload];

        $extra = Context::get('audit');
        if (is_array($extra) && $extra !== []) {
            $context = array_merge($context, $extra);
        }

        $max = (int) config('audit.max_context_bytes', 16384);
        if (strlen((string) json_encode($context)) > $max) {
            unset($context['request']);
            $context['_truncated'] = true;
        }

        return $context;
    }

    /**
     * Recursively replace the value of any key matching a redaction pattern with "[redacted]", and an
     * uploaded file (which can't be stored as JSON, and may hold personal data) with "[file]".
     *
     * @param  array<array-key, mixed>  $data
     * @param  list<string>  $patterns
     * @return array<array-key, mixed>
     */
    private function redact(array $data, array $patterns): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && $this->isRedacted($key, $patterns)) {
                $data[$key] = '[redacted]';

                continue;
            }

            if ($value instanceof UploadedFile) {
                $data[$key] = '[file]';

                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->redact($value, $patterns);
            }
        }

        return $data;
    }

    /**
     * @param  list<string>  $patterns
     */
    private function isRedacted(string $key, array $patterns): bool
    {
        $key = strtolower($key);

        foreach ($patterns as $pattern) {
            if (Str::is(strtolower($pattern), $key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function config(string $key): array
    {
        /** @var list<string> $value */
        $value = config('audit.' . $key, []);

        return $value;
    }
}
