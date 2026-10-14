<?php

declare(strict_types=1);

namespace Rominas\Delivery\SMTP;

use Rominas\Delivery\DeliveryPayloadInterface;
use Rominas\Delivery\DeliveryServiceInterface;
use Rominas\Delivery\MailPayload;
use Rominas\Delivery\MailServiceInterface;
use Exception;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use RuntimeException;

class SmtpMailService implements DeliveryServiceInterface, MailServiceInterface
{
    public function __construct(
        public string $host,
        public int $port,
        public string $username,
        public string $password,
        public string $encryption = 'tls',
        public bool $debug = false,
    ) {}

    public function send(DeliveryPayloadInterface $payload): void
    {
        if (! ($payload instanceof MailPayload)) {
            throw new InvalidArgumentException(sprintf('SmtpMailService expects a MailPayload, got %s.', get_debug_type($payload)));
        }

        try {
            Mail::mailer('smtp')->send([], [], function (Message $message) use ($payload) {
                // Set sender
                $message->from($payload->sender->email, $payload->sender->name);

                // Set recipients
                foreach ($payload->recipients as $recipient) {
                    $message->to($recipient);
                }

                // Set CC recipients
                if (! empty($payload->cc)) {
                    foreach ($payload->cc as $cc) {
                        $message->cc($cc->email, $cc->name);
                    }
                }

                // Set subject and body
                $message->subject($payload->subject);
                $message->html($payload->body);

                // Add attachments
                if (! empty($payload->attachments)) {
                    foreach ($payload->attachments as $attachment) {
                        if (filter_var($attachment['path'], FILTER_VALIDATE_URL)) {
                            // Download temp file
                            $tempFile = tempnam(sys_get_temp_dir(), 'attachment');
                            file_put_contents($tempFile, file_get_contents($attachment['path']));

                            $message->attach($tempFile, [
                                'as' => $attachment['name'],
                                'mime' => 'application/pdf',
                            ]);
                        } else {
                            // For local file paths
                            $message->attach($attachment['path'], [
                                'as' => $attachment['name'],
                                'mime' => 'application/pdf',
                            ]);
                        }
                    }
                }
            });
        } catch (Exception $e) {
            throw new RuntimeException("Message could not be sent. Error: {$e->getMessage()}", 0, $e);
        }
    }
}
