<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Mail\NewCommentNotification;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

final class CommentController extends Controller
{
    public function store(Request $request, Post $post): RedirectResponse
    {
        $validated = $request->validate([
            'content' => ['required', 'string', 'min:5', 'max:1000'],
            'author_name' => ['nullable', 'string', 'max:255'],
            'author_email' => ['nullable', 'string', 'email', 'max:255'],
        ]);

        $comment = new Comment($validated);
        $comment->post_id = $post->id;

        if (Auth::check()) {
            $comment->user_id = Auth::id();
            $comment->author_name = Auth::user()->name;
            $comment->author_email = Auth::user()->email;
            $comment->approved = true;
        } else {
            $comment->approved = false;
        }

        $comment->save();

        // Notificar a todos los administradores
        try {
            $admins = User::query()
                ->where('role', 'admin')
                ->whereNotNull('email')
                ->get();

            foreach ($admins as $admin) {
                Mail::to($admin->email)
                    ->send(new NewCommentNotification($comment));
            }
        } catch (\Throwable $e) {
            // Si falla el envío de email, no interrumpimos el flujo
            logger()->error('Error enviando notificación de comentario: ' . $e->getMessage());
        }

        return back()->with('status', 'Comentario enviado. Será visible una vez aprobado.');
    }
}
