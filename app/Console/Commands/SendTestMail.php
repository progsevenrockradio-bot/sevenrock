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
        
        // 1. Correo de Prueba (Layout general)
        $mailable1 = new class extends Mailable {
            public function build()
            {
                return $this->subject('Prueba 1: Diseño General - Seven Rock Radio')
                            ->markdown('emails.test-layout');
            }
        };

        // 2. Correo de Contacto
        $mailable2 = new \App\Mail\ContactMail(
            'John Doe', 
            'john@example.com', 
            '+34 600 000 000', 
            '¡Hola! El nuevo diseño de correos está brutal. Quería contactar con vosotros para felicitaros.', 
            'Formulario de Contacto'
        );

        // 3. Correo de Marketing (Boletín)
        $mailable3 = new \App\Mail\MarketingMail(
            'newsletter',
            'Prueba 3: Nuevo Lanzamiento de Seven Rock',
            'Este es un ejemplo de cómo se ven tus correos promocionales y boletines con el nuevo **modo oscuro**. ¡El rock no para!',
            'Escuchar ahora',
            url('/'),
            config('mail.from.address', 'hello@sevenrockradio.com'),
            config('mail.from.name', 'Seven Rock Radio'),
            'Amante del Rock'
        );

        // 4. Correo de Alerta de Podcast
        $dummyProgram = \App\Models\RadioProgram::first() ?? new \App\Models\RadioProgram([
            'titulo_programa' => 'Metal Vortex',
            'numero_episodio' => 42,
            'live_title' => 'Especial de Thrash Metal de los 80s',
            'archivo_mp3' => 'metal-vortex-ep42.mp3',
        ]);
        $mailable4 = new \App\Mail\PodcastUploadedMail(
            $dummyProgram,
            '/var/www/uploads/metal-vortex-ep42.mp3',
            'remote/path',
            true,
            true,
            'delivery_verified'
        );

        // 5. Correo de Firma de Contrato
        $dummyContract = new \App\Models\Contract([
            'title' => 'Acuerdo de Distribución Musical',
            'signer_name' => 'Dave Mustaine',
            'signer_email' => $email,
            'token' => 'dummy-token-1234',
        ]);
        $mailable5 = new \App\Mail\ContractSignRequestMail($dummyContract);

        try {
            Mail::to($email)->send($mailable1);
            $this->info("¡Correo de prueba 1 (Diseño General) enviado a {$email}!");

            Mail::to($email)->send($mailable2);
            $this->info("¡Correo de prueba 2 (Contacto) enviado a {$email}!");

            Mail::to($email)->send($mailable3);
            $this->info("¡Correo de prueba 3 (Marketing) enviado a {$email}!");

            Mail::to($email)->send($mailable4);
            $this->info("¡Correo de prueba 4 (Podcast Upload) enviado a {$email}!");

            Mail::to($email)->send($mailable5);
            $this->info("¡Correo de prueba 5 (Firma de Contrato) enviado a {$email}!");

            $this->info("\n¡Todos los correos de prueba han sido enviados exitosamente!");
        } catch (\Throwable $e) {
            $this->error("Hubo un error al enviar los correos: " . $e->getMessage());
        }
    }
}
