<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MasterProgram;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class MasterProgramController extends Controller
{
    public function index(): View
    {
        $masterPrograms = MasterProgram::adminListing();

        if ($search = trim((string) request()->input('search', ''))) {
            $masterPrograms = $masterPrograms->filter(function (MasterProgram $program) use ($search): bool {
                return str_contains(mb_strtolower($program->name), mb_strtolower($search))
                    || str_contains(mb_strtolower((string) $program->program_code), mb_strtolower($search))
                    || str_contains(mb_strtolower((string) $program->conductor), mb_strtolower($search))
                    || str_contains(mb_strtolower((string) $program->email_notificacion), mb_strtolower($search));
            })->values();
        }

        $dayTabs = [
            'LUNES' => 'Lunes',
            'MARTES' => 'Martes',
            'MIERCOLES' => 'Miércoles',
            'JUEVES' => 'Jueves',
            'VIERNES' => 'Viernes',
            'SABADO' => 'Sábado',
            'DOMINGO' => 'Domingo',
        ];

        $programsByDay = collect($dayTabs)
            ->mapWithKeys(function (string $label, string $day) use ($masterPrograms): array {
                return [
                    $day => $masterPrograms->where('dia_transmision', $day)->values(),
                ];
            });

        return view('admin.master-programs.index', [
            'masterPrograms' => $masterPrograms,
            'dayTabs' => $dayTabs,
            'programsByDay' => $programsByDay,
            'activeDay' => $this->currentDayKey(),
            'search' => (string) request()->input('search', ''),
        ]);
    }

    public function report(): View
    {
        $masterPrograms = MasterProgram::adminListing();

        $dayTabs = [
            'LUNES' => 'Lunes',
            'MARTES' => 'Martes',
            'MIERCOLES' => 'Miércoles',
            'JUEVES' => 'Jueves',
            'VIERNES' => 'Viernes',
            'SABADO' => 'Sábado',
            'DOMINGO' => 'Domingo',
        ];

        $programsByDay = collect($dayTabs)
            ->mapWithKeys(function (string $label, string $day) use ($masterPrograms): array {
                return [
                    $day => $masterPrograms->where('dia_transmision', $day)->values(),
                ];
            });

        return view('admin.master-programs.report', [
            'masterPrograms' => $masterPrograms,
            'dayTabs' => $dayTabs,
            'programsByDay' => $programsByDay,
        ]);
    }

    public function grid(): View
    {
        $masterPrograms = MasterProgram::adminListing()->where('activo', true);

        $dayTabs = [
            'LUNES' => 'Lunes',
            'MARTES' => 'Martes',
            'MIERCOLES' => 'Miercoles',
            'JUEVES' => 'Jueves',
            'VIERNES' => 'Viernes',
            'SABADO' => 'Sábado',
            'DOMINGO' => 'Domingo',
        ];

        $uniqueTimes = $masterPrograms->pluck('hora_transmision')->filter()->unique()->sort()->values();

        $blocks = [
            'MADRUGADA' => [],
            'MAÑANA' => [],
            'MEDIODIA' => [],
            'TARDE' => [],
            'NOCHE' => [],
        ];

        foreach ($uniqueTimes as $time) {
            $hour = (int) substr($time, 0, 2);
            if ($hour >= 6 && $hour < 12) {
                $blocks['MAÑANA'][] = $time;
            } elseif ($hour >= 12 && $hour < 14) {
                $blocks['MEDIODIA'][] = $time;
            } elseif ($hour >= 14 && $hour < 19) {
                $blocks['TARDE'][] = $time;
            } elseif ($hour >= 19 && $hour <= 23) {
                $blocks['NOCHE'][] = $time;
            } else {
                $blocks['MADRUGADA'][] = $time;
            }
        }

        $blocks = array_filter($blocks, fn($times) => count($times) > 0);

        $programsGrid = [];
        foreach ($masterPrograms as $program) {
            $time = $program->hora_transmision;
            $day = $program->dia_transmision;
            if ($time && $day) {
                $programsGrid[$time][$day][] = $program;
            }
        }

        return view('admin.master-programs.grid', [
            'dayTabs' => $dayTabs,
            'blocks' => $blocks,
            'programsGrid' => $programsGrid,
        ]);
    }

    public function create(): View
    {
        $masterProgram = new MasterProgram([
                'timezone' => 'America/Caracas',
                'duracion_minutos' => 120,
                'activo' => true,
                'vistas_archive' => 0,
                'escuchas_locales' => 0,
                'vistas_totales' => 0,
            ]);

        return view('admin.master-programs.create', [
            'masterProgram'      => $masterProgram,
            'defaultNewsIdsText' => '',
            'liveNewsIdsText'    => '',
            'previewNewsIdsText' => '',
            'generateCodeAction' => null,
            'emisiones'          => collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $masterProgram = MasterProgram::query()->create($this->validated($request));
        $this->saveEmisiones($masterProgram, $request);
        $masterProgram->syncEmisionNormal();

        return redirect()
            ->route('admin.master-programs.edit', $masterProgram)
            ->with('status', 'Programa maestro creado.');
    }

    public function edit(MasterProgram $masterProgram): View
    {
        return view('admin.master-programs.edit', [
            'masterProgram'     => $masterProgram,
            'defaultNewsIdsText' => $this->idListToText($masterProgram->default_news_ids),
            'liveNewsIdsText'    => $this->idListToText($masterProgram->live_news_ids),
            'previewNewsIdsText' => $this->idListToText($masterProgram->preview_news_ids),
            'generateCodeAction' => route('admin.programs.generate-code', $masterProgram),
            'emisiones'          => $masterProgram->emisiones()->get(),
        ]);
    }

    public function update(Request $request, MasterProgram $masterProgram): RedirectResponse
    {
        $masterProgram->update($this->validated($request, $masterProgram->id));
        $this->saveEmisiones($masterProgram, $request);
        $masterProgram->syncEmisionNormal();

        return redirect()
            ->route('admin.master-programs.edit', $masterProgram)
            ->with('status', 'Programa maestro actualizado.');
    }

    public function destroy(MasterProgram $masterProgram): RedirectResponse
    {
        $masterProgram->delete();

        return redirect()
            ->route('admin.master-programs.index')
            ->with('status', 'Programa maestro eliminado.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'conductor' => ['required', 'string', 'max:255'],
            'dia_transmision' => ['required', Rule::in(['LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO', 'DOMINGO'])],
            'hora_transmision' => ['nullable', 'string', 'max:8'],
            'timezone' => ['required', 'string', 'max:255'],
            'duracion_minutos' => ['required', 'integer', 'min:1', 'max:1440'],
            'genero' => ['required', 'string', 'max:255'],
            'program_code' => ['nullable', 'string', 'max:12', 'unique:master_programs,program_code' . ($ignoreId ? ',' . $ignoreId : '')],
            'code_prefix' => ['nullable', 'string', 'max:12'],
            'caratula_url' => ['nullable', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'live_title' => ['nullable', 'string', 'max:255'],
            'live_description' => ['nullable', 'string'],
            'live_image_url' => ['nullable', 'string', 'max:255'],
            'live_starts_at' => ['nullable', 'date'],
            'live_ends_at' => ['nullable', 'date'],
            'default_news_ids_text' => ['nullable', 'string'],
            'live_news_ids_text' => ['nullable', 'string'],
            'preview_news_ids_text' => ['nullable', 'string'],
            'comentario_predeterminado' => ['nullable', 'string', 'max:255'],
            'red_social1_url' => ['nullable', 'string', 'max:255'],
            'red_social2_url' => ['nullable', 'string', 'max:255'],
            'activo' => ['nullable', 'boolean'],
            'archive_identifier' => ['nullable', 'string', 'max:255'],
            'vistas_archive' => ['nullable', 'integer', 'min:0'],
            'escuchas_locales' => ['nullable', 'integer', 'min:0'],
            'vistas_totales' => ['nullable', 'integer', 'min:0'],
            'stats_updated_at' => ['nullable', 'date'],
            'ruta_ftp' => ['nullable', 'string', 'max:255'],
            'email_notificacion' => ['nullable', 'email', 'max:255'],
            'email_copia_notificacion' => ['nullable', 'email', 'max:255'],
        ]);

        $validated['hora_transmision'] = $this->normalizeTime((string) ($validated['hora_transmision'] ?? ''));
        $validated['program_code'] = $this->normalizeProgramCode((string) ($validated['program_code'] ?? '')) ?: null;
        $validated['code_prefix'] = $this->normalizeProgramCode((string) ($validated['code_prefix'] ?? '')) ?: null;
        $validated['live_starts_at'] = $this->normalizeDateTime((string) ($validated['live_starts_at'] ?? ''));
        $validated['live_ends_at'] = $this->normalizeDateTime((string) ($validated['live_ends_at'] ?? ''));
        $validated['stats_updated_at'] = $this->normalizeDateTime((string) ($validated['stats_updated_at'] ?? ''));
        $validated['default_news_ids'] = $this->parseIdList((string) ($validated['default_news_ids_text'] ?? ''));
        $validated['live_news_ids'] = $this->parseIdList((string) ($validated['live_news_ids_text'] ?? ''));
        $validated['preview_news_ids'] = $this->parseIdList((string) ($validated['preview_news_ids_text'] ?? ''));
        $validated['activo'] = $request->boolean('activo');
        $validated['caratula_url'] = trim((string) ($validated['caratula_url'] ?? '')) ?: null;
        $validated['descripcion'] = trim((string) ($validated['descripcion'] ?? '')) ?: null;
        $validated['live_title'] = trim((string) ($validated['live_title'] ?? '')) ?: null;
        $validated['live_description'] = trim((string) ($validated['live_description'] ?? '')) ?: null;
        $validated['live_image_url'] = trim((string) ($validated['live_image_url'] ?? '')) ?: null;
        $validated['comentario_predeterminado'] = trim((string) ($validated['comentario_predeterminado'] ?? '')) ?: null;
        $validated['red_social1_url'] = trim((string) ($validated['red_social1_url'] ?? '')) ?: null;
        $validated['red_social2_url'] = trim((string) ($validated['red_social2_url'] ?? '')) ?: null;
        $validated['archive_identifier'] = trim((string) ($validated['archive_identifier'] ?? '')) ?: null;
        $validated['ruta_ftp'] = trim((string) ($validated['ruta_ftp'] ?? '')) ?: null;
        $validated['email_notificacion'] = trim((string) ($validated['email_notificacion'] ?? '')) ?: null;
        $validated['email_copia_notificacion'] = trim((string) ($validated['email_copia_notificacion'] ?? '')) ?: null;

        unset($validated['default_news_ids_text'], $validated['live_news_ids_text'], $validated['preview_news_ids_text']);

        return $validated;
    }

    /**
     * Guarda (crea / actualiza / elimina) las emisiones enviadas desde el formulario.
     * Cada fila del repetidor viene en emisiones[N][campo].
     * Las emisiones que tengan id se actualizan; las sin id se crean; las que
     * no aparezcan en el POST se eliminan.
     */
    private function saveEmisiones(MasterProgram $masterProgram, Request $request): void
    {
        $tiposValidos = array_keys(\App\Models\MasterProgramEmision::TIPOS);
        $diasValidos  = array_keys(\App\Models\MasterProgramEmision::DIAS);
        $rows         = $request->input('emisiones', []);

        if (! is_array($rows)) {
            return;
        }

        $request->validate([
            'emisiones.*.tipo'              => ['nullable', 'string', 'in:' . implode(',', $tiposValidos)],
            'emisiones.*.dia_semana'        => ['nullable', 'string', 'in:' . implode(',', $diasValidos)],
            'emisiones.*.hora_inicio'       => ['nullable', 'string', 'max:8'],
            'emisiones.*.duracion_minutos'  => ['nullable', 'integer', 'min:1', 'max:600'],
            'emisiones.*.duracion_segundos' => ['nullable', 'integer', 'min:0'],
            'emisiones.*.duracion_real_min' => ['nullable', 'integer', 'min:0', 'max:600'],
            'emisiones.*.duracion_real_sec' => ['nullable', 'integer', 'min:0', 'max:59'],
            'emisiones.*.enlace'            => ['nullable', 'url', 'max:500'],
            'emisiones.*.url_podcast'       => ['nullable', 'url', 'max:500'],
        ]);

        $seenIds = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $tipo   = trim((string) ($row['tipo']       ?? 'normal'));
            $dia    = strtoupper(trim((string) ($row['dia_semana']  ?? '')));
            $hora   = trim((string) ($row['hora_inicio'] ?? ''));

            if (! in_array($tipo, $tiposValidos, true) || ! in_array($dia, $diasValidos, true) || $hora === '' || ! preg_match('/^\d{2}:\d{2}/', $hora)) {
                continue;
            }

            $enlace = trim((string) ($row['enlace']      ?? ''));
            // en_vivo requiere enlace — si no lo tiene, lo ignoramos
            if ($tipo === 'en_vivo' && $enlace === '') {
                continue;
            }

            $duracionSegundos = null;
            if (isset($row['duracion_segundos']) && $row['duracion_segundos'] !== '' && is_numeric($row['duracion_segundos'])) {
                $duracionSegundos = max(0, (int) $row['duracion_segundos']);
            } elseif (
                (isset($row['duracion_real_min']) && $row['duracion_real_min'] !== '') ||
                (isset($row['duracion_real_sec']) && $row['duracion_real_sec'] !== '')
            ) {
                $min = (int) ($row['duracion_real_min'] ?? 0);
                $sec = (int) ($row['duracion_real_sec'] ?? 0);
                $totalSec = ($min * 60) + $sec;
                $duracionSegundos = $totalSec > 0 ? $totalSec : null;
            }

            $data = [
                'tipo'              => $tipo,
                'etiqueta'          => trim((string) ($row['etiqueta']         ?? '')) ?: null,
                'dia_semana'        => $dia,
                'hora_inicio'       => $this->normalizeTime($hora) ?? $hora,
                'duracion_minutos'  => max(1, (int) ($row['duracion_minutos'] ?? 120)),
                'duracion_segundos' => $duracionSegundos,
                'enlace'            => $enlace ?: null,
                'url_podcast'       => trim((string) ($row['url_podcast']      ?? '')) ?: null,
                'notas'             => trim((string) ($row['notas']            ?? '')) ?: null,
                'activo'            => isset($row['activo']) ? (bool) $row['activo'] : true,
            ];

            $rowId = (int) ($row['id'] ?? 0);

            if ($rowId > 0) {
                /** @var \App\Models\MasterProgramEmision|null $emision */
                $emision = $masterProgram->emisiones()->find($rowId);
                if ($emision) {
                    $emision->update($data);
                    $seenIds[] = $rowId;
                }
            } else {
                $emision = $masterProgram->emisiones()->create($data);
                $seenIds[] = $emision->id;
            }
        }

        // Eliminar las emisiones que ya no están en el formulario
        $masterProgram->emisiones()
            ->when($seenIds !== [], fn ($q) => $q->whereNotIn('id', $seenIds))
            ->when($seenIds === [], fn ($q) => $q)
            ->delete();

        // Eliminar duplicados (mismo tipo + dia_semana + hora_inicio), conservando la de menor id
        $allEmisiones = $masterProgram->emisiones()->orderBy('id')->get();
        $grouped = $allEmisiones->groupBy(function ($e) {
            $h = substr((string) $e->hora_inicio, 0, 5);
            return sprintf('%s_%s_%s', $e->tipo, strtoupper((string) $e->dia_semana), $h);
        });

        $deleteIds = [];
        foreach ($grouped as $group) {
            if ($group->count() > 1) {
                foreach ($group->slice(1) as $dup) {
                    $deleteIds[] = $dup->id;
                }
            }
        }

        if ($deleteIds !== []) {
            $masterProgram->emisiones()->whereIn('id', $deleteIds)->delete();
        }
    }

    private function normalizeTime(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $candidate = substr($value, 0, 5);

        try {
            return Carbon::createFromFormat('H:i', $candidate)->format('H:i:s');
        } catch (\Throwable) {
            return $candidate . ':00';
        }
    }

    private function normalizeDateTime(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        return Carbon::parse($value)->toDateTimeString();
    }

    private function normalizeProgramCode(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $value = \Illuminate\Support\Str::of($value)->ascii()->upper()->replaceMatches('/[^A-Z0-9]+/', '')->toString();

        return substr($value, 0, 12);
    }

    private function currentDayKey(): string
    {
        return match (Carbon::now(config('app.timezone'))->dayOfWeekIso) {
            1 => 'LUNES',
            2 => 'MARTES',
            3 => 'MIERCOLES',
            4 => 'JUEVES',
            5 => 'VIERNES',
            6 => 'SABADO',
            7 => 'DOMINGO',
            default => 'LUNES',
        };
    }

    /**
     * @return array<int, int>|null
     */
    private function parseIdList(string $value): ?array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($value)) ?: [];
        $ids = [];

        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '' || ! is_numeric($line)) {
                continue;
            }

            $ids[] = (int) $line;
        }

        $ids = array_values(array_unique($ids));

        return $ids !== [] ? $ids : null;
    }

    /**
     * @param array<int, int>|null $values
     */
    private function idListToText(?array $values): string
    {
        if ($values === null || $values === []) {
            return '';
        }

        return implode("\n", array_map(static fn ($value): string => (string) $value, $values));
    }
}
