<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Mail\Mailable;

class SendTestMail extends Command
{
    protected $signature = 'mail:test {email}';
    protected $description = 'Send a test email using the markdown layout to check the aesthetic.';

    public function handle()
    {
        $email = $this->argument('email');
        
        $mailable = new class extends Mailable {
            public function build()
            {
                return $this->subject('Prueba de Diseño - Seven Rock Radio')
                            ->markdown('emails.test-layout');
            }
        };

        Mail::to($email)->send($mailable);

        $this->info("¡Correo de prueba enviado a {$email}!");
    }
}
