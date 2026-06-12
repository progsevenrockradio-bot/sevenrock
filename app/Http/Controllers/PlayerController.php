<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class PlayerController extends Controller
{
    public function show(): View
    {
        return view('player.popup');
    }

    public function registerShare(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
    {
        $title = trim((string) $request->input('title', ''));
        $artist = trim((string) $request->input('artist', ''));
        $cover = trim((string) $request->input('cover', ''));
        $program = trim((string) $request->input('program', ''));

        // Generate an 8-character unique key based on the metadata and current microtime
        $key = substr(md5($title . '|' . $artist . '|' . $cover . '|' . $program . '|' . microtime()), 0, 8);

        \Illuminate\Support\Facades\Cache::put("share_track_{$key}", [
            'title' => $title,
            'artist' => $artist,
            'cover' => $cover,
            'program' => $program,
        ], now()->addDays(30));

        return response()->json(['key' => $key]);
    }
}
