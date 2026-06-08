<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use App\Mail\ContactMail;
use Tests\TestCase;

final class HoneypotSpamTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_legitimate_user_can_submit_contact_form(): void
    {
        Mail::fake();

        $response = $this->post(route('contact.send'), [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '123456789',
            'message' => 'This is a legitimate message of at least ten characters.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', '¡Mensaje enviado correctamente!');

        Mail::assertSent(ContactMail::class);
    }

    public function test_spam_bot_is_blocked_by_honeypot(): void
    {
        Mail::fake();

        $response = $this->post(route('contact.send'), [
            'name' => 'Spam Bot',
            'email' => 'bot@spammer.com',
            'phone' => '00000000',
            'message' => 'Buy cheap links now! Click here.',
            'user_website' => 'http://spam-link-site.com', // Relleno por el bot
        ]);

        // Debe redirigir de vuelta simulando éxito para engañar al bot
        $response->assertRedirect();
        $response->assertSessionHas('success', '¡Mensaje enviado correctamente!');

        // Pero NO debe enviarse ningún correo
        Mail::assertNotSent(ContactMail::class);
    }
}
