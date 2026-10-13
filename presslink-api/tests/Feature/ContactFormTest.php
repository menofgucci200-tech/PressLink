<?php

namespace Tests\Feature;

use App\Mail\ContactMessage;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Formulaire de la page Contact : envoi vers contact@presslink.org, ou
 * simple lien mailto tant qu'aucun transport mail n'est configuré.
 */
class ContactFormTest extends TestCase
{
    public function test_contact_page_shows_the_support_email(): void
    {
        $this->get('/contact')->assertOk()->assertSee('contact@presslink.org');
    }

    public function test_without_a_real_mailer_the_page_falls_back_to_mailto(): void
    {
        config(['mail.default' => 'log']);

        $this->get('/contact')
            ->assertSee('mailto:contact@presslink.org', false)
            ->assertDontSee('Envoyer le message');
    }

    public function test_message_is_emailed_to_the_support_address(): void
    {
        config(['mail.default' => 'smtp']);
        Mail::fake();

        $this->get('/contact')->assertSee('Envoyer le message');

        $this->post('/contact', [
            'name' => 'Aïcha Koné',
            'profile' => 'Pressing',
            'email' => 'aicha@example.com',
            'message' => 'Bonjour, je voudrais une démonstration.',
        ])->assertRedirect('/contact')->assertSessionHas('contact_sent');

        Mail::assertSent(ContactMessage::class, fn (ContactMessage $mail) => $mail->hasTo('contact@presslink.org')
            && $mail->hasReplyTo('aicha@example.com'));
    }

    public function test_a_way_to_reply_is_required(): void
    {
        config(['mail.default' => 'smtp']);
        Mail::fake();

        $this->post('/contact', ['name' => 'X', 'profile' => 'Client', 'message' => 'Un message assez long.'])
            ->assertSessionHasErrors(['email', 'phone']);

        Mail::assertNothingSent();
    }

    public function test_bots_filling_the_honeypot_are_silently_ignored(): void
    {
        config(['mail.default' => 'smtp']);
        Mail::fake();

        $this->post('/contact', ['website' => 'spam', 'name' => 'Bot', 'profile' => 'Autre', 'email' => 'b@b.co', 'message' => 'Achetez maintenant !'])
            ->assertRedirect('/contact');

        Mail::assertNothingSent();
    }
}
