<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class CommentController extends Controller
{
    public function index(): View
    {
        return view('admin.comments.index', [
            'comments' => Comment::query()
                ->with('post')
                ->orderBy('created_at', 'desc')
                ->get(),
            'pendingCount' => Comment::query()->where('approved', false)->count(),
        ]);
    }

    public function edit(Comment $comment): View
    {
        return view('admin.comments.edit', [
            'comment' => $comment->load('post'),
            'posts' => Post::query()->orderBy('title')->get(),
        ]);
    }

    public function update(Request $request, Comment $comment): RedirectResponse
    {
        $validated = $request->validate([
            'author_name' => ['nullable', 'string', 'max:255'],
            'author_email' => ['nullable', 'string', 'email', 'max:255'],
            'content' => ['required', 'string', 'min:1', 'max:1000'],
            'approved' => ['nullable', 'boolean'],
        ]);

        $validated['approved'] = (bool) ($validated['approved'] ?? false);

        $comment->update($validated);

        return redirect()->route('admin.comments.index')
            ->with('status', 'Comentario actualizado correctamente.');
    }

    public function approve(Comment $comment): RedirectResponse
    {
        $comment->update(['approved' => true]);

        return redirect()->route('admin.comments.index')
            ->with('status', 'Comentario aprobado correctamente.');
    }

    public function unapprove(Comment $comment): RedirectResponse
    {
        $comment->update(['approved' => false]);

        return redirect()->route('admin.comments.index')
            ->with('status', 'Comentario desaprobado.');
    }

    public function destroy(Comment $comment): RedirectResponse
    {
        $comment->delete();

        return redirect()->route('admin.comments.index')
            ->with('status', 'Comentario eliminado.');
    }
}
