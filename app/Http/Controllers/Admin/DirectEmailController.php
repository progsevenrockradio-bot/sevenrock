<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\DirectAdminEmail;
use App\Models\OutreachTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class DirectEmailController extends Controller
{
    public function create(Request $request): View
    {
        $templates = OutreachTemplate::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'subject', 'body']);

        $defaultRecipient = (string) $request->query('to', '');

        return view('admin.direct-email.compose', [
            'templates' => $templates,
            'defaultRecipient' => $defaultRecipient,
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        $request->validate([
            'recipients' => ['required', 'string'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ], [
            'recipients.required' => 'Debes ingresar al menos un correo electrónico.',
            'subject.required' => 'El asunto es obligatorio.',
            'body.required' => 'El cuerpo del mensaje no puede estar vacío.',
        ]);

        $rawRecipients = explode(',', $request->input('recipients'));
        $validEmails = [];
        $invalidEmails = [];

        foreach ($rawRecipients as $raw) {
            $email = trim($raw);
            if ($email === '') {
                continue;
            }

            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $validEmails[] = $email;
            } else {
                $invalidEmails[] = $email;
            }
        }

        if (empty($validEmails)) {
            return back()
                ->withInput()
                ->withErrors(['recipients' => 'Ninguno de los correos ingresados tiene un formato válido.']);
        }

        $subject = (string) $request->input('subject');
        $body = (string) $request->input('body');

        $sentCount = 0;
        foreach ($validEmails as $recipientEmail) {
            try {
                Mail::to($recipientEmail)->send(new DirectAdminEmail(
                    subjectLine: $subject,
                    bodyHtml: nl2br($body)
                ));
                $sentCount++;
            } catch (\Throwable $e) {
                // Log error or continue
                report($e);
            }
        }

        $statusMsg = "Correo enviado exitosamente a {$sentCount} destinatario(s).";
        if (!empty($invalidEmails)) {
            $statusMsg .= ' Direcciones omitidas por formato inválido: ' . implode(', ', $invalidEmails);
        }

        return redirect()->route('admin.direct-email.create')->with('status', $statusMsg);
    }
}
