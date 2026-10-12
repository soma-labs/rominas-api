<?php

declare(strict_types=1);

namespace Rominas\EmailValidation\ZeroBounce;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Rominas\EmailValidation\DataTransferObjects\EmailValidationResult;
use Rominas\EmailValidation\EmailValidatorInterface;

/**
 * Validates one address through ZeroBounce's real-time API (`GET {base_url}/validate`). Only the address
 * is sent — no IP. Every failure (no API key, network error, timeout, an error answer such as a bad key
 * or no credits left) returns null and is logged without the address on the `zerobounce` channel, so the
 * caller can carry on.
 */
class ZeroBounceEmailValidator implements EmailValidatorInterface
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl,
        private readonly int $timeout,
    ) {}

    public function validate(string $email): ?EmailValidationResult
    {
        if ($this->apiKey === '') {
            return null;
        }

        try {
            $response = Http::timeout($this->timeout + 2)
                ->acceptJson()
                ->get(rtrim($this->baseUrl, '/') . '/validate', [
                    'api_key' => $this->apiKey,
                    'email' => $email,
                    'ip_address' => '',
                    'timeout' => $this->timeout,
                ]);
        } catch (ConnectionException $exception) {
            Log::channel('zerobounce')->warning('ZeroBounce could not be reached; the address was not validated.', [
                'reason' => $exception->getMessage(),
            ]);

            return null;
        }

        $status = $response->json('status');

        if ($response->failed() || ! is_string($status) || $status === '') {
            Log::channel('zerobounce')->warning('ZeroBounce answered with an error; the address was not validated.', [
                'http_status' => $response->status(),
                'error' => $response->json('error'),
            ]);

            return null;
        }

        $subStatus = $response->json('sub_status');

        return new EmailValidationResult(
            status: $status,
            subStatus: is_string($subStatus) && $subStatus !== '' ? $subStatus : null,
        );
    }
}
