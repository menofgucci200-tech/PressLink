<?php

namespace Tests\Feature;

use App\Mail\ContactMessage;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailLogoTest extends TestCase
{
    public function test_logo_is_embedded_in_sent_emails(): void
    {
        config(['mail.default' => 'array']);

        Mail::to('contact@presslink.org')->send(new ContactMessage([
            'name' => 'Koffi', 'profile' => 'Client', 'phone' => '', 'email' => '', 'message' => 'Bonjour',
        ]));

        $sent = Mail::mailer('array')->getSymfonyTransport()->messages()->first()->getOriginalMessage();
        $this->assertStringContainsString('src="cid:', $sent->getHtmlBody());
        $this->assertNotEmpty($sent->getAttachments());
    }
}
