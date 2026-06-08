<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class PreventSpamWithHoneypot
{
    public function handle(Request $request, Closure $next, string $fieldName = 'user_website'): Response
    {
        if ($request->filled($fieldName)) {
            // Log the spam block
            logger()->warning(sprintf(
                'Spam blocked from IP %s. Honeypot field [%s] was filled. Request URI: %s',
                $request->ip(),
                $fieldName,
                $request->getRequestUri()
            ));

            // Respond based on target route / request type
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Processed successfully.',
                ]);
            }

            $routeName = $request->route()?->getName();

            if ($routeName === 'home.contact.send') {
                return redirect()->route('home')->with('success', 'Mensaje enviado correctamente. Nos pondremos en contacto pronto.');
            }

            if ($routeName === 'contact.send') {
                return redirect()->back()->with('success', '¡Mensaje enviado correctamente!');
            }

            if ($routeName === 'posts.comments.store') {
                return redirect()->back()->with('status', 'Comentario enviado. Será visible una vez aprobado.');
            }

            if ($routeName === 'talents.register.store') {
                return redirect()->route('talents.dashboard')->with('status', 'Cuenta creada.');
            }

            return redirect()->back()->with('success', 'Procesado correctamente.');
        }

        return $next($request);
    }
}
