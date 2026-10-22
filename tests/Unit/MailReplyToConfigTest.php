<?php

namespace Tests\Unit;

use Tests\TestCase;

class MailReplyToConfigTest extends TestCase
{
    public function test_mailer_boots_when_reply_to_address_has_no_display_name(): void
    {
        config(['mail.reply_to.address' => 'replies@example.com']);

        $this->assertArrayHasKey('name', config('mail.reply_to'));

        $this->app->forgetInstance('mail.manager');

        $mailer = $this->app->make('mail.manager')->mailer('array');

        $this->assertNotNull($mailer);
    }
}
