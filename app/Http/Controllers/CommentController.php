<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommentRequest;
use App\Mail\NewCommentNotification;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

final class CommentController extends Controller
{
    public function store(StoreCommentRequest $request, Post $post): RedirectResponse
    {
        $validated = $request->validated();

        $comment = new Comment($validated);
        $comment->post_id = $post->id;

        $isPending = app(\App\Services\ModerationService::class)->needsModeration('comment');
        
        if (Auth::check()) {
            $comment->user_id = Auth::id();
            $comment->author_name = Auth::user()->name;
            $comment->author_email = Auth::user()->email;
            // Si requiere moderación, todos pasan por caja. Si no, todos aprobados.
            $comment->approved = !$isPending;
        } else {
            // Anonimos requieren moderacion siempre si está activada
            $comment->approved = !$isPending;
        }

        $comment->save();

        app(\App\Services\ModerationService::class)->registerIfRequired('comment', [
            'subject_type' => Comment::class,
            'subject_id' => $comment->id,
            'title' => 'Nuevo Comentario',
            'summary' => \Illuminate\Support\Str::limit($comment->content, 50),
            'submitter_name' => $comment->author_name,
            'submitter_email' => $comment->author_email,
        ]);

        return back()->with('status', $isPending ? 'Comentario enviado. Será visible una vez aprobado.' : 'Comentario publicado.');
    }
}
