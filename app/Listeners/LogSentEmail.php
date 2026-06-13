<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Queue\InteractsWithQueue;
use App\Models\EmailLog;
use Illuminate\Support\Str;

class LogSentEmail
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(MessageSent $event): void
    {
        try {
            $message = $event->message;
            $to = collect($message->getTo())->map(function ($address) {
                return $address->getAddress();
            })->implode(', ');

            $subject = $message->getSubject();
            $body = $message->getHtmlBody() ?: $message->getTextBody();
            
            EmailLog::create([
                'to_email' => Str::limit($to, 250),
                'subject' => Str::limit((string) $subject, 250),
                'body' => $body,
                'status' => 'sent',
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Fallo al registrar email_logs: ' . $e->getMessage());
        }
    }
}
