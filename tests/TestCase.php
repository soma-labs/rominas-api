<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The Delivery module always sends through the `smtp` mailer, whatever MAIL_MAILER says, and
        // .env may hold real SMTP credentials. Point that mailer at the in-memory transport so no test
        // can send a real e-mail.
        config(['mail.mailers.smtp' => ['transport' => 'array']]);
    }
}
