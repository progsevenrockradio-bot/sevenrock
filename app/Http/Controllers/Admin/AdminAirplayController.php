<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AirplayNotice;
use App\Models\AirplaySchedule;
use App\Models\AirplayWeek;
use App\Models\ThemeSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminAirplayController extends Controller
{
    public function index(Request $request): View
    {
        $weeks = AirplayWeek::orderBy('semana', 'desc')->get();
        $selectedWeek = $request->query('semana', $weeks->first()?->semana ?: date('Y-\WW'));
        $search = $request->query('search');
        $tipoItem = $request->query('tipo_item');
        $primerPase = $request->boolean('es_primer_pase');

        $query = AirplaySchedule::with(['talent', 'notices'])
            ->where('semana', $selectedWeek);

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('artista', 'like', "%{$search}%")
                  ->orWhere('titulo', 'like', "%{$search}%")
                  ->orWhere('sello', 'like', "%{$search}%")
                  ->orWhere('album', 'like', "%{$search}%");
            });
        }

        if (!empty($tipoItem)) {
            $query->where('tipo_item', $tipoItem);
        }

        if ($primerPase) {
            $query->where('es_primer_pase', true);
        }

        $schedules = $query->orderBy('dia')->orderBy('hora')->orderBy('posicion')->paginate(50)->withQueryString();
        $currentWeekModel = AirplayWeek::where('semana', $selectedWeek)->first();
        $token = ThemeSetting::get('airplay_api_token');

        return view('admin.airplay.index', compact(
            'weeks',
            'selectedWeek',
            'schedules',
            'currentWeekModel',
            'search',
            'tipoItem',
            'primerPase',
            'token'
        ));
    }

    public function notices(Request $request): View
    {
        $status = $request->query('estado');
        $search = $request->query('search');

        $query = AirplayNotice::with(['schedule']);

        if (!empty($status)) {
            $query->where('estado', $status);
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                  ->orWhereHas('schedule', function ($sqid) use ($search) {
                        $sqid->where('artista', 'like', "%{$search}%")
                            ->orWhere('titulo', 'like', "%{$search}%");
                    });
            });
        }

        $notices = $query->orderBy('id', 'desc')->paginate(30)->withQueryString();

        return view('admin.airplay.notices', compact('notices', 'status', 'search'));
    }

    public function sendNotices(Request $request): RedirectResponse
    {
        $semana = $request->input('semana');
        $force = $request->boolean('force');

        $exitCode = Artisan::call('airplay:send-notices', [
            '--semana' => $semana,
            '--force'  => $force,
        ]);

        $output = Artisan::output();

        if ($exitCode === 0) {
            return redirect()->back()->with('success', 'Envío de notificaciones completado. ' . trim($output));
        }

        return redirect()->back()->with('error', 'Error durante el envío de notificaciones: ' . trim($output));
    }

    public function generateToken(Request $request): RedirectResponse
    {
        $newToken = Str::random(40);
        ThemeSetting::set('airplay_api_token', $newToken);

        return redirect()->back()->with('success', 'Nuevo Token API generado correctamente (40 caracteres).');
    }
}
