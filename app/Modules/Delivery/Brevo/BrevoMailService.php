<?php

declare(strict_types=1);

namespace Rominas\Delivery\Brevo;

use Brevo\Brevo;
use Brevo\Exceptions\BrevoApiException;
use Brevo\Exceptions\BrevoException;
use Brevo\TransactionalEmails\Requests\SendTransacEmailRequest;
use Brevo\TransactionalEmails\Types\SendTransacEmailRequestAttachmentItem;
use Brevo\TransactionalEmails\Types\SendTransacEmailRequestCcItem;
use Brevo\TransactionalEmails\Types\SendTransacEmailRequestSender;
use Brevo\TransactionalEmails\Types\SendTransacEmailRequestToItem;
use InvalidArgumentException;
use Rominas\Delivery\DeliveryPayloadInterface;
use Rominas\Delivery\DeliveryServiceInterface;
use Rominas\Delivery\MailPayload;
use Rominas\Delivery\EmailContact;
use Rominas\Delivery\MailServiceInterface;
use RuntimeException;

/**
 * Sends e-mail through Brevo's transactional HTTP API (`POST /v3/smtp/email`), chosen with
 * `MAIL_DELIVERY_TRANSPORT=brevo`. A refused request becomes a RuntimeException naming Brevo's HTTP status
 * and message, since the SDK's own exception only says "API request failed".
 */
class BrevoMailService implements DeliveryServiceInterface, MailServiceInterface
{
    public function __construct(
        private readonly Brevo $brevo,
    ) {}

    public function send(DeliveryPayloadInterface $payload): void
    {
        if (! ($payload instanceof MailPayload)) {
            throw new InvalidArgumentException(sprintf('BrevoMailService expects a MailPayload, got %s.', get_debug_type($payload)));
        }

        $attachments = array_map(
            static fn(array $attachment): SendTransacEmailRequestAttachmentItem => filter_var($attachment['path'], FILTER_VALIDATE_URL)
                ? new SendTransacEmailRequestAttachmentItem(['url' => $attachment['path'], 'name' => $attachment['name']])
                : new SendTransacEmailRequestAttachmentItem([
                    'content' => base64_encode((string) file_get_contents($attachment['path'])),
                    'name' => $attachment['name'],
                ]),
            $payload->attachments,
        );

        $cc = array_map(
            static fn(EmailContact $contact): SendTransacEmailRequestCcItem => new SendTransacEmailRequestCcItem([
                'email' => $contact->email,
                'name' => $contact->name,
            ]),
            $payload->cc,
        );

        $to = array_map(
            static fn(string $recipient): SendTransacEmailRequestToItem => new SendTransacEmailRequestToItem([
                'email' => $recipient,
            ]),
            $payload->recipients,
        );

        try {
            $this->brevo->transactionalEmails->sendTransacEmail(new SendTransacEmailRequest([
                'sender' => new SendTransacEmailRequestSender([
                    'email' => $payload->sender->email,
                    'name' => $payload->sender->name,
                ]),
                'to' => $to,
                'cc' => $cc === [] ? null : $cc,
                'subject' => $payload->subject,
                'htmlContent' => $payload->body,
                'attachment' => $attachments === [] ? null : $attachments,
                'tags' => $payload->tags === [] ? null : $payload->tags,
            ]));
        } catch (BrevoApiException $e) {
            throw new RuntimeException(
                "Brevo API request failed ({$e->getCode()}): {$this->apiErrorMessage($e)}",
                0,
                $e,
            );
        } catch (BrevoException $e) {
            throw new RuntimeException("Brevo API request failed: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Brevo answers an error with `{"code": "...", "message": "..."}`; fall back to the raw body.
     */
    private function apiErrorMessage(BrevoApiException $e): string
    {
        $body = $e->getBody();
        $decoded = is_string($body) ? json_decode($body, true) : $body;

        if (is_array($decoded) && is_string($decoded['message'] ?? null)) {
            return $decoded['message'];
        }

        return is_string($body) && $body !== '' ? $body : $e->getMessage();
    }
}
