<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\ContactMail;
use App\Mail\TalentProspectMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_form_sends_admin_notification_on_general_subject(): void
    {
        Mail::fake();

        $response = $this->post(route('contact.send'), [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '123456789',
            'subject' => 'general',
            'message' => 'This is a general inquiry about rock programs.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        Mail::assertSent(ContactMail::class, function (ContactMail $mail) {
            return $mail->senderName === 'John Doe' &&
                   $mail->senderEmail === 'john@example.com' &&
                   $mail->senderPhone === '123456789' &&
                   $mail->source === 'Contacto' &&
                   is_null($mail->bandName);
        });

        Mail::assertNotSent(TalentProspectMail::class);
    }

    public function test_contact_form_sends_admin_notification_and_prospect_info_on_join_radio(): void
    {
        Mail::fake();

        $response = $this->post(route('contact.send'), [
            'name' => 'Rock Star',
            'email' => 'star@rock.com',
            'phone' => '987654321',
            'subject' => 'join_radio',
            'band_name' => 'The Metalheads',
            'message' => 'We want to register our band on your radio!',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        Mail::assertSent(ContactMail::class, function (ContactMail $mail) {
            return $mail->senderName === 'Rock Star' &&
                   $mail->senderEmail === 'star@rock.com' &&
                   $mail->senderPhone === '987654321' &&
                   $mail->source === 'Contacto' &&
                   $mail->bandName === 'The Metalheads';
        });

        Mail::assertSent(TalentProspectMail::class, function (TalentProspectMail $mail) {
            return $mail->senderName === 'Rock Star' &&
                   $mail->bandName === 'The Metalheads';
        });
    }

    public function test_contact_form_requires_band_name_on_join_radio_subject(): void
    {
        Mail::fake();

        $response = $this->post(route('contact.send'), [
            'name' => 'Rock Star',
            'email' => 'star@rock.com',
            'phone' => '987654321',
            'subject' => 'join_radio',
            // band_name is missing
            'message' => 'We want to register our band on your radio!',
        ]);

        $response->assertSessionHasErrors('band_name');
        Mail::assertNotSent(ContactMail::class);
        Mail::assertNotSent(TalentProspectMail::class);
    }

    public function test_home_contact_form_sends_mails_on_join_radio(): void
    {
        Mail::fake();

        $response = $this->post(route('home.contact.send'), [
            'name' => 'Home Star',
            'email' => 'home@rock.com',
            'phone' => '5551234',
            'subject' => 'join_radio',
            'band_name' => 'Home Rockers',
            'message' => 'Sending message from the home page contact form!',
        ]);

        $response->assertRedirect(route('home'));
        $response->assertSessionHas('success');

        Mail::assertSent(ContactMail::class, function (ContactMail $mail) {
            return $mail->senderName === 'Home Star' &&
                   $mail->senderEmail === 'home@rock.com' &&
                   $mail->senderPhone === '5551234' &&
                   $mail->source === 'Inicio' &&
                   $mail->bandName === 'Home Rockers';
        });

        Mail::assertSent(TalentProspectMail::class, function (TalentProspectMail $mail) {
            return $mail->senderName === 'Home Star' &&
                   $mail->bandName === 'Home Rockers';
        });
    }
}
