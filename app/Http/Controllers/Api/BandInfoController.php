<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\BandInfoResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BandInfoController extends Controller
{
    public function __construct(private readonly BandInfoResolver $resolver)
    {
    }

    public function show(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'artist' => ['required', 'string', 'max:255'],
        ]);

        $payload = $this->resolver->resolve($validated['artist']);

        return response()->json([
            'success' => true,
            'data' => $payload,
        ]);
    }
}
