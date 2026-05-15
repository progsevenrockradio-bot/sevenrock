<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notice;
use App\Models\PlayHistory;
use App\Models\Program;
use App\Models\Song;
use App\Services\RadioPlayerService;
use App\Support\PublicMediaUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

class PlayerStatusController extends Controller
{
    public function __construct(
        private readonly RadioPlayerService $playerService,
    ) {
    }

    public function show(): JsonResponse
    {
        $status = $this->playerService->resolve();

        return response()->json([
            'success' => true,
            'data' => $status,
        ]);
    }
}
