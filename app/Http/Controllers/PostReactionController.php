<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\PostReaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

final class PostReactionController extends Controller
{
    public function toggle(Request $request, Post $post): JsonResponse
    {
        [$ownerKey, $cookie] = $this->resolveOwner($request);

        $query = PostReaction::query()
            ->where('owner_key', $ownerKey)
            ->where('post_id', $post->id)
            ->where('reaction_type', 'like');

        $existing = $query->first();

        if ($existing) {
            $existing->delete();
            $liked = false;
        } else {
            PostReaction::query()->create([
                'owner_key' => $ownerKey,
                'post_id' => $post->id,
                'reaction_type' => 'like',
            ]);
            $liked = true;
        }

        $likesCount = PostReaction::query()
            ->where('post_id', $post->id)
            ->where('reaction_type', 'like')
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'liked' => $liked,
                'likes_count' => $likesCount,
            ],
        ])->cookie($cookie);
    }

    private function resolveOwner(Request $request): array
    {
        $tokenName = 'sr_content_reactions_owner';
        $cookieValue = (string) $request->cookie($tokenName, '');

        if (trim($cookieValue) === '') {
            $cookieValue = (string) Str::uuid();
        }

        $ownerKey = Auth::check()
            ? 'user:' . (string) Auth::id()
            : 'visitor:' . $cookieValue;

        $cookie = cookie(
            $tokenName,
            $cookieValue,
            60 * 24 * 365,
            '/',
            null,
            app()->isProduction(),
            true,
            false,
            'lax'
        );

        return [$ownerKey, $cookie];
    }
}
