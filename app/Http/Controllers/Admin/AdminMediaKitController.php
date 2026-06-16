<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\MediaKitMail;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class AdminMediaKitController extends Controller
{
    /**
     * Show the form to send the Media Kit.
     */
    public function showForm(): View
    {
        $theme = config('theme.appearance', []);
        
        $defaultLogo = $theme['media']['logo_url'] ?? asset('assets/lucille/logo.png');
        $defaultDescription = $theme['site_description'] ?? 'Seven Rock Radio: La mejor música rock 24/7.';
        
        return view('admin.media-kit.form', compact('defaultLogo', 'defaultDescription'));
    }

    /**
     * Send the Media Kit email.
     */
    public function sendMediaKit(Request $request): RedirectResponse
    {
        $request->validate([
            'recipient_email' => ['required', 'email'],
            'recipient_name'  => ['nullable', 'string', 'max:255'],
            'subject'         => ['required', 'string', 'max:255'],
            'custom_message'  => ['nullable', 'string'],
        ]);

        $recipientEmail = $request->input('recipient_email');
        $recipientName = $request->input('recipient_name');
        $subject = $request->input('subject');
        $customMessage = $request->input('custom_message');

        // You could also fetch a PDF path from settings or storage if it's uploaded via admin
        // For now, we assume it's stored in public/assets or storage/app/public
        // Let's pass the theme appearance to the Mailable
        $theme = config('theme.appearance', []);
        
        try {
            Mail::to($recipientEmail)->send(new MediaKitMail($subject, $customMessage, $theme, $recipientName));
            
            return redirect()->route('admin.media-kit.form')->with('status', 'Media Kit enviado correctamente a ' . $recipientEmail);
        } catch (\Exception $e) {
            return redirect()->route('admin.media-kit.form')->with('error', 'Error al enviar: ' . $e->getMessage());
        }
    }
}
