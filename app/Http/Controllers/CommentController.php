<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        return back()->with('status', 'Comentario enviado. Será visible una vez aprobado.');
    }
}
