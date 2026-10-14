<?php

declare(strict_types=1);

use Brevo\Brevo;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Rominas\Delivery\Brevo\BrevoMailService;
use Rominas\Delivery\DeliveryPayloadInterface;
use Rominas\Delivery\EmailContact;
use Rominas\Delivery\MailPayload;

/**
 * A BrevoMailService whose client answers from the given responses and records each request, so
 * nothing leaves the process.
 *
 * @param  list<Response>  $responses
 * @param  list<array{request: RequestInterface}>  $history
 */
function brevoServiceAnswering(array $responses, array &$history): BrevoMailService
{
    $stack = HandlerStack::create(new MockHandler($responses));
    $stack->push(Middleware::history($history));

    return new BrevoMailService(new Brevo(
        apiKey: 'test-api-key',
        options: ['client' => new Client(['handler' => $stack]), 'maxRetries' => 0],
    ));
}

function brevoTestPayload(): MailPayload
{
    return new MailPayload(
        sender: new EmailContact(email: 'no-reply@example.test', name: 'The Romanian Music Industry Awards'),
        recipients: ['voter@example.test'],
        subject: 'Link de vot The Romanian Music Industry Awards',
        body: '<p>Votează acum</p>',
        tags: ['voting-link'],
    );
}

it('sends the e-mail to Brevo\'s transactional endpoint with the API key', function (): void {
    $history = [];
    $service = brevoServiceAnswering([new Response(201, [], '{"messageId":"<1@smtp-relay.mailin.fr>"}')], $history);

    $service->send(brevoTestPayload());

    expect($history)->toHaveCount(1);
    $request = $history[0]['request'];

    expect($request->getMethod())->toBe('POST')
        ->and((string) $request->getUri())->toBe('https://api.brevo.com/v3/smtp/email')
        ->and($request->getHeaderLine('api-key'))->toBe('test-api-key')
        ->and(json_decode((string) $request->getBody(), true))->toEqual([
            'sender' => ['email' => 'no-reply@example.test', 'name' => 'The Romanian Music Industry Awards'],
            'subject' => 'Link de vot The Romanian Music Industry Awards',
            'htmlContent' => '<p>Votează acum</p>',
            'tags' => ['voting-link'],
            'to' => [['email' => 'voter@example.test']],
        ]);
});

it('reports Brevo\'s status and message when the request is refused', function (): void {
    $history = [];
    $service = brevoServiceAnswering([new Response(401, [], '{"code":"unauthorized","message":"Key not found"}')], $history);

    expect(fn() => $service->send(brevoTestPayload()))
        ->toThrow(RuntimeException::class, 'Brevo API request failed (401): Key not found');
});

it('refuses a payload that is not an e-mail', function (): void {
    $history = [];
    $service = brevoServiceAnswering([], $history);

    expect(fn() => $service->send(new class implements DeliveryPayloadInterface {}))
        ->toThrow(InvalidArgumentException::class, 'BrevoMailService expects a MailPayload');

    expect($history)->toBe([]);
});
