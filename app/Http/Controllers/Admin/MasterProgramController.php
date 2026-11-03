<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MasterProgram;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class MasterProgramController extends Controller
{
    public function index(): View
    {
        $masterPrograms = MasterProgram::adminListing();
        $masterPrograms->loadMissing('emisiones');

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
                $dayEntries = collect();

                foreach ($masterPrograms as $program) {
                    $emisiones = $program->emisiones;

                    if ($emisiones->isNotEmpty()) {
                        // Si tiene emisiones configuradas, tomamos ÚNICAMENTE las activas del día correspondiente.
                        // Si todas las emisiones están inactivas, NO se recurre al horario base (emisión desactivada explícitamente).
                        $activePassesForDay = $emisiones->where('activo', true)
                            ->filter(fn ($em) => strtoupper(trim((string) $em->dia_semana)) === $day)
                            ->sortBy(fn ($em) => (string) ($em->hora_inicio ?? '99:99:99'))
                            ->values();

                        if ($activePassesForDay->isNotEmpty()) {
                            $earliestPass = $activePassesForDay->first();
                            $earliestTime = (string) ($earliestPass->hora_inicio ?? '99:99:99');

                            $dayEntries->push([
                                'program' => $program,
                                'sort_time' => $earliestTime,
                                'day_passes' => $activePassesForDay,
                            ]);
                        }
                    } else {
                        // Retrocompatibilidad: programas históricos sin ninguna emisión configurada en master_program_emisiones
                        $baseDay = strtoupper(trim((string) $program->dia_transmision));
                        if ($baseDay === $day) {
                            $baseTime = (string) ($program->hora_transmision ?? '99:99:99');
                            $dayEntries->push([
                                'program' => $program,
                                'sort_time' => $baseTime,
                                'day_passes' => collect(),
                            ]);
                        }
                    }
                }

                // Ordenar por hora de pase del día (ascendente) y luego por nombre
                $sorted = $dayEntries->sortBy([
                    fn ($a, $b) => strcmp((string) $a['sort_time'], (string) $b['sort_time']),
                    fn ($a, $b) => strnatcasecmp((string) $a['program']->name, (string) $b['program']->name),
                ])->pluck('program')->values();

                return [
                    $day => $sorted,
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
                'editorial_mode' => 'mandatory_review',
            ]);

        return view('admin.master-programs.create', [
            'masterProgram'      => $masterProgram,
            'defaultNewsIdsText' => '',
            'liveNewsIdsText'    => '',
            'previewNewsIdsText' => '',
            'generateCodeAction' => null,
            'emisiones'          => collect(),
            'availableConductors' => $this->loadAvailableConductors(),
            'authorizedConductorIds' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $masterProgram = MasterProgram::query()->create($this->validated($request));
        $this->saveEmisiones($masterProgram, $request);
        $this->syncConductors($masterProgram, $request);
        $masterProgram->syncEmisionNormal();

        return redirect()
            ->route('admin.master-programs.edit', $masterProgram)
            ->with('status', 'Programa maestro creado.');
    }

    public function edit(MasterProgram $masterProgram): View
    {
        return view('admin.master-programs.edit', [
            'masterProgram'      => $masterProgram,
            'defaultNewsIdsText' => $this->idListToText($masterProgram->default_news_ids),
            'liveNewsIdsText'    => $this->idListToText($masterProgram->live_news_ids),
            'previewNewsIdsText' => $this->idListToText($masterProgram->preview_news_ids),
            'generateCodeAction' => route('admin.programs.generate-code', $masterProgram),
            'emisiones'          => $masterProgram->emisiones()->get(),
            'availableConductors' => $this->loadAvailableConductors(),
            'authorizedConductorIds' => Schema::hasTable('master_program_conductor')
                ? $masterProgram->authorizedConductors()->pluck('users.id')->map(fn ($id) => (int) $id)->all()
                : [],
        ]);
    }

    public function update(Request $request, MasterProgram $masterProgram): RedirectResponse
    {
        $masterProgram->update($this->validated($request, $masterProgram->id, $masterProgram));
        $this->saveEmisiones($masterProgram, $request);
        $this->syncConductors($masterProgram, $request);
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
    private function validated(Request $request, ?int $ignoreId = null, ?MasterProgram $existing = null): array
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'conductor' => ['required', 'string', 'max:255'],
            'pais' => ['nullable', 'string', 'max:100'],
            'dia_transmision' => ['required', Rule::in(['LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO', 'DOMINGO'])],
            'hora_transmision' => ['nullable', 'string', 'max:8'],
            'timezone' => ['required', 'string', 'max:255'],
            'duracion_minutos' => ['required', 'integer', 'min:1', 'max:1440'],
            'submission_cutoff_minutes' => ['nullable', 'integer', 'min:15', 'max:10080'],
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
            'editorial_mode' => ['nullable', Rule::in(['mandatory_review', 'autonomous'])],
            'confirm_autonomous_mode' => ['nullable', 'boolean'],
            'editorial_guide' => ['nullable', 'array'],
            'editorial_guide.identity_and_audience' => ['nullable', 'string', 'max:4000'],
            'editorial_guide.tone_and_style' => ['nullable', 'string', 'max:4000'],
            'editorial_guide.structure_rules' => ['nullable', 'string', 'max:4000'],
            'editorial_guide.cover_requirements' => ['nullable', 'string', 'max:4000'],
            'editorial_guide.particular_rules' => ['nullable', 'string', 'max:4000'],
            'editorial_guide.optional_examples' => ['nullable', 'string', 'max:4000'],
        ]);

        $validated['hora_transmision'] = $this->normalizeTime((string) ($validated['hora_transmision'] ?? ''), $ignoreId);
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

        if (Schema::hasColumn('master_programs', 'editorial_mode')) {
            $requestedMode = (string) ($validated['editorial_mode'] ?? ($existing?->editorial_mode ?? 'mandatory_review'));

            if ($requestedMode === 'autonomous') {
                $alreadyAuthorized = $existing !== null && $existing->isAutonomousDeliveryAuthorized();
                $confirmAutonomous = $request->boolean('confirm_autonomous_mode');

                if (! $alreadyAuthorized && ! $confirmAutonomous) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'editorial_mode' => 'La entrega autónoma requiere autorización expresa del administrador (marque la casilla de autorización).',
                    ]);
                }

                $validated['editorial_mode'] = 'autonomous';
                $validated['autonomous_authorized_by'] = $existing?->autonomous_authorized_by ?? $request->user()?->id;
                $validated['autonomous_authorized_at'] = $existing?->autonomous_authorized_at ?? now();
            } else {
                $validated['editorial_mode'] = 'mandatory_review';
                $validated['autonomous_authorized_by'] = null;
                $validated['autonomous_authorized_at'] = null;
            }
        } else {
            unset($validated['editorial_mode']);
        }

        if (Schema::hasColumn('master_programs', 'editorial_guide') && is_array($request->input('editorial_guide'))) {
            $guide = [];
            foreach ([
                'identity_and_audience',
                'tone_and_style',
                'structure_rules',
                'cover_requirements',
                'particular_rules',
                'optional_examples',
            ] as $key) {
                $val = trim((string) ($validated['editorial_guide'][$key] ?? ''));
                if ($val !== '') {
                    $guide[$key] = $val;
                }
            }
            $validated['editorial_guide'] = $guide !== [] ? $guide : null;
        } elseif (! $request->has('editorial_guide')) {
            unset($validated['editorial_guide']);
        }

        unset(
            $validated['default_news_ids_text'],
            $validated['live_news_ids_text'],
            $validated['preview_news_ids_text'],
            $validated['confirm_autonomous_mode']
        );

        return $validated;
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function loadAvailableConductors(): \Illuminate\Support\Collection
    {
        if (! Schema::hasTable('users')) {
            return collect();
        }

        $query = User::query();

        $hasRoleColumn = Schema::hasColumn('users', 'role');
        $hasIsAdminColumn = Schema::hasColumn('users', 'is_admin');

        if ($hasRoleColumn && $hasIsAdminColumn) {
            $query->where(function ($q): void {
                $q->where(function ($inner): void {
                    $inner->where('role', '!=', 'admin')->orWhereNull('role');
                })->where(function ($inner): void {
                    $inner->where('is_admin', false)->orWhereNull('is_admin');
                });
            });
        } elseif ($hasRoleColumn) {
            $query->where(function ($q): void {
                $q->where('role', '!=', 'admin')->orWhereNull('role');
            });
        } elseif ($hasIsAdminColumn) {
            $query->where(function ($q): void {
                $q->where('is_admin', false)->orWhereNull('is_admin');
            });
        }

        return $query->orderBy('name')->get();
    }

    private function syncConductors(MasterProgram $masterProgram, Request $request): void
    {
        if (! Schema::hasTable('master_program_conductor')) {
            return;
        }

        $request->validate([
            'conductor_user_ids' => ['nullable', 'array'],
            'conductor_user_ids.*' => ['integer', 'exists:users,id'],
            'new_conductor_name' => ['nullable', 'string', 'max:255'],
            'new_conductor_email' => ['nullable', 'email', 'max:255'],
            'new_conductor_password' => ['nullable', 'string', 'min:8', 'max:255'],
        ], [
            'new_conductor_email.email' => 'El correo del nuevo conductor debe ser una dirección válida.',
            'new_conductor_password.min' => 'La contraseña inicial del nuevo conductor debe tener al menos 8 caracteres.',
        ]);

        $selectedIds = collect((array) $request->input('conductor_user_ids', []))
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        $newEmail = trim((string) $request->input('new_conductor_email', ''));
        $newName = trim((string) $request->input('new_conductor_name', ''));
        $newPass = (string) $request->input('new_conductor_password', '');

        if ($newEmail !== '') {
            $conductorUser = User::query()->where('email', $newEmail)->first();
            if ($conductorUser && $conductorUser->hasAdminAccess()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'new_conductor_email' => 'La dirección ingresada pertenece a una cuenta de administrador. No se puede convertir en cuenta de conductor.',
                ]);
            }

            if (! $conductorUser) {
                $userPayload = [
                    'name' => $newName !== '' ? $newName : $masterProgram->conductor,
                    'email' => $newEmail,
                    'password' => Hash::make($newPass !== '' ? $newPass : \Illuminate\Support\Str::random(16)),
                    'is_conductor' => true,
                ];
                if (Schema::hasColumn('users', 'is_admin')) {
                    $userPayload['is_admin'] = false;
                }
                if (Schema::hasColumn('users', 'role')) {
                    $userPayload['role'] = 'conductor';
                }
                $conductorUser = User::query()->create($userPayload);
            } else {
                $conductorUser->forceFill(['is_conductor' => true])->save();
            }

            $selectedIds[] = (int) $conductorUser->id;
            $selectedIds = array_values(array_unique($selectedIds));
        }

        if ($request->has('conductor_submissions_section_present') || $selectedIds !== [] || $newEmail !== '') {
            $syncPayload = [];
            foreach ($selectedIds as $userId) {
                $user = User::query()->find($userId);
                if (! $user || $user->hasAdminAccess()) {
                    continue;
                }
                if (! $user->is_conductor) {
                    $user->forceFill(['is_conductor' => true])->save();
                }
                $syncPayload[$userId] = [
                    'is_active' => true,
                    'authorized_by' => $request->user()?->id,
                    'authorized_at' => now(),
                ];
            }

            $masterProgram->conductors()->sync($syncPayload);
        }
    }

    /**
     * Guarda (crea / actualiza / elimina) las emisiones enviadas desde el formulario.
     * Cada fila del repetidor viene en emisiones[N][campo].
     * Las emisiones que tengan id se actualizan; las sin id se crean; las que
     * no aparezcan en el POST se eliminan.
     *
     * FIX: si la fila no trae duracion_segundos y los campos «duración real»
     * vienen vacíos, se conserva el valor que ya tiene la emisión en vez de
     * sobrescribirlo con null.
     */
    private function saveEmisiones(MasterProgram $masterProgram, Request $request): void
    {
        $tiposValidos = array_keys(\App\Models\MasterProgramEmision::TIPOS);
        $diasValidos  = array_keys(\App\Models\MasterProgramEmision::DIAS);
        $rows         = $request->input('emisiones', []);

        if (! is_array($rows)) {
            return;
        }

        // Normalizar strings de dígitos con posibles ceros a la izquierda (ej. "00", "05") antes de validar integer
        $cleanedRows = [];
        foreach ($rows as $k => $row) {
            if (is_array($row)) {
                if (isset($row['duracion_real_sec']) && is_string($row['duracion_real_sec'])) {
                    $secTrim = trim($row['duracion_real_sec']);
                    if (preg_match('/^\d+$/', $secTrim)) {
                        $row['duracion_real_sec'] = (int) $secTrim;
                    }
                }
                if (isset($row['duracion_real_min']) && is_string($row['duracion_real_min'])) {
                    $minTrim = trim($row['duracion_real_min']);
                    if (preg_match('/^\d+$/', $minTrim)) {
                        $row['duracion_real_min'] = (int) $minTrim;
                    }
                }
            }
            $cleanedRows[$k] = $row;
        }
        $request->merge(['emisiones' => $cleanedRows]);
        $rows = $cleanedRows;

        $request->validate([
            'emisiones.*.tipo'              => ['nullable', 'string', 'in:' . implode(',', $tiposValidos)],
            'emisiones.*.dia_semana'        => ['nullable', 'string', 'in:' . implode(',', $diasValidos)],
            'emisiones.*.hora_inicio'       => ['nullable', 'string', 'max:8'],
            'emisiones.*.duracion_minutos'  => ['nullable', 'integer', 'min:1', 'max:600'],
            'emisiones.*.duracion_segundos' => ['nullable', 'integer', 'min:0'],
            'emisiones.*.duracion_real_min' => ['nullable', 'integer', 'min:0', 'max:600'],
            'emisiones.*.duracion_real_sec' => ['nullable', 'integer', 'min:0', 'max:59'],
            'emisiones.*.borrar_duracion_real' => ['nullable'],
            'emisiones.*.enlace'            => ['nullable', 'url', 'max:500'],
            'emisiones.*.url_podcast'       => ['nullable', 'url', 'max:500'],
        ], [
            'emisiones.*.hora_inicio.max' => 'La hora de inicio no debe superar los 8 caracteres.',
            'emisiones.*.duracion_minutos.min' => 'La duración en minutos debe ser de al menos 1.',
            'emisiones.*.duracion_minutos.max' => 'La duración en minutos no debe superar los 600.',
            'emisiones.*.duracion_real_min.integer' => 'Los minutos de duración real deben ser un número entero.',
            'emisiones.*.duracion_real_min.min' => 'Los minutos de duración real no pueden ser negativos.',
            'emisiones.*.duracion_real_min.max' => 'Los minutos de duración real no deben superar los 600.',
            'emisiones.*.duracion_real_sec.integer' => 'Los segundos de duración real deben ser un número entero entre 0 y 59.',
            'emisiones.*.duracion_real_sec.min' => 'Los segundos de duración real no pueden ser negativos.',
            'emisiones.*.duracion_real_sec.max' => 'Los segundos de duración real deben estar entre 0 y 59.',
            'emisiones.*.enlace.url' => 'El enlace en vivo de la emisión debe ser una URL válida (con http:// o https://).',
            'emisiones.*.url_podcast.url' => 'La URL del podcast de la emisión debe ser una URL válida (con http:// o https://).',
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

            // ── Resolver duracion_segundos sin pisar el valor existente ──
            $rowId = (int) ($row['id'] ?? 0);
            /** @var \App\Models\MasterProgramEmision|null $emision */
            $emision = $rowId > 0 ? $masterProgram->emisiones()->find($rowId) : null;

            $borrarDuracion = ! empty($row['borrar_duracion_real']);

            if ($borrarDuracion) {
                $duracionSegundos = null;
            } else {
                $duracionSegundos = null;
                $hasDuracionExplicit = false;

                if (
                    (isset($row['duracion_real_min']) && $row['duracion_real_min'] !== '') ||
                    (isset($row['duracion_real_sec']) && $row['duracion_real_sec'] !== '')
                ) {
                    $min = (int) ($row['duracion_real_min'] ?? 0);
                    $sec = (int) ($row['duracion_real_sec'] ?? 0);
                    $totalSec = ($min * 60) + $sec;
                    $duracionSegundos = $totalSec > 0 ? $totalSec : null;
                    $hasDuracionExplicit = true;
                } elseif (isset($row['duracion_segundos']) && $row['duracion_segundos'] !== '' && is_numeric($row['duracion_segundos'])) {
                    $duracionSegundos = max(0, (int) $row['duracion_segundos']);
                    $hasDuracionExplicit = true;
                }

                // Si no se envió nada explícito y los campos vienen vacíos, conservar el valor que ya tenía la emisión
                if (! $hasDuracionExplicit && $emision) {
                    $duracionSegundos = $emision->duracion_segundos;
                }
            }

            $data = [
                'tipo'              => $tipo,
                'etiqueta'          => trim((string) ($row['etiqueta']         ?? '')) ?: null,
                'dia_semana'        => $dia,
                'hora_inicio'       => $this->normalizeTime($hora, $masterProgram->id) ?? $hora,
                'duracion_minutos'  => max(1, (int) ($row['duracion_minutos'] ?? 120)),
                'duracion_segundos' => $duracionSegundos,
                'enlace'            => $enlace ?: null,
                'url_podcast'       => trim((string) ($row['url_podcast']      ?? '')) ?: null,
                'notas'             => trim((string) ($row['notas']            ?? '')) ?: null,
                'activo'            => isset($row['activo']) ? (bool) $row['activo'] : true,
            ];

            if ($emision) {
                $emision->update($data);
                $seenIds[] = $rowId;
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

    private function normalizeTime(string $value, ?int $programId = null): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $candidate = substr($value, 0, 5);

        try {
            return Carbon::createFromFormat('H:i', $candidate)->format('H:i:s');
        } catch (\Throwable) {
            Log::warning('[MasterProgram] normalizeTime: valor no válido como H:i', [
                'value'      => $value,
                'program_id' => $programId,
            ]);

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
