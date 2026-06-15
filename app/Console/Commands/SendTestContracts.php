<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use App\Models\Contract;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;

class SendTestContracts extends Command
{
    protected $signature = 'mail:test-contracts {email}';
    protected $description = 'Send a test email for each contract plan (Free, Basic, Pro, Premium) to verify the new templates.';

    public function handle()
    {
        $email = $this->argument('email');
        $plans = ['free', 'basic', 'pro', 'premium'];
        $templates = config('contracts.templates');

        foreach ($plans as $plan) {
            $template = $templates[$plan];
            
            $contract = new Contract([
                'title' => $template['title'],
                'content' => $template['body'],
                'token' => Str::random(32),
                'signer_name' => 'Jose Manuel Font Acuña',
                'signer_email' => $email,
                'band_name' => 'TORBELLINO',
                'country' => 'España',
                'city' => 'Elche',
                'status' => 'signed',
                'signing_ip' => '127.0.0.1',
            ]);
            $contract->signed_at = Carbon::now();
            
            // Reemplazar placeholders temporales si es que el modelo no lo hace en tiempo real
            // El modelo lo hace on the fly con getFormattedContentAttribute()
            
            // Generar PDF
            $options = new Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            $dompdf = new Dompdf($options);
            
            $logoBase64 = null;
            try {
                $theme = \App\Models\ThemeSetting::current();
                if ($theme) {
                    $logoPath = public_path('assets/lucille/logo.png');
                    if (file_exists($logoPath)) {
                        $logoData = file_get_contents($logoPath);
                        $mime = mime_content_type($logoPath) ?: 'image/png';
                        $logoBase64 = 'data:' . $mime . ';base64,' . base64_encode($logoData);
                    }
                }
            } catch (\Exception $e) {}

            $html = view('contratos.pdf_template', [
                'contract' => $contract,
                'nombre' => $contract->signer_name,
                'fecha' => $contract->signed_at->format('d/m/Y'),
                'fecha_hora' => $contract->signed_at->toDateTimeString(),
                'ip' => $contract->signing_ip,
                'logo_base64' => $logoBase64,
            ])->render();

            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            
            $pdfContent = $dompdf->output();
            
            // Guardar temporalmente
            $pdfPath = 'contracts/test_' . $plan . '_' . time() . '.pdf';
            Storage::put($pdfPath, $pdfContent);
            $contract->pdf_path = $pdfPath;
            
            // Enviar Correo de Contrato Firmado (con el PDF adjunto)
            $mailable = new \App\Mail\ContractSignedMail($contract);
            // Sobrescribir asunto para diferenciar
            $mailable->subject('Test de Contrato: ' . strtoupper($plan));
            
            Mail::to($email)->send($mailable);
            $this->info("¡Contrato {$plan} enviado a {$email}!");
            
            // Opcional: borrar el pdf de prueba
            // Storage::delete($pdfPath);
        }

        $this->info("\n¡Todos los contratos de prueba han sido enviados exitosamente!");
    }
}
