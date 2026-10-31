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
        // En entorno local de pruebas, si hay un escenario activo fijado en la sesión o en caché,
        // devolverlo directamente para permitir recorrer los 6 estados de forma determinista y sin dependencias externas.
        if (app()->environment(['local', 'testing']) && \Illuminate\Support\Facades\Cache::has('dev_player_simulated_status')) {
            $simulated = \Illuminate\Support\Facades\Cache::get('dev_player_simulated_status');
            if (is_array($simulated)) {
                return response()->json([
                    'success' => true,
                    'data' => $simulated,
                ]);
            }
        }

        $status = $this->playerService->resolve();

        return response()->json([
            'success' => true,
            'data' => $status,
        ]);
    }
}
