<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MasterProgram;
use App\Models\MasterProgramEmision;
use App\Models\RadioProgram;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

final class PodcastDeliveryReportService
{
    public const UNREGISTERED_USER_FILTER = 'unregistered';

    /** @var array<int, string> */
    private const ISO_DAY_KEYS = [
        1 => 'LUNES',
        2 => 'MARTES',
        3 => 'MIERCOLES',
        4 => 'JUEVES',
        5 => 'VIERNES',
        6 => 'SABADO',
        7 => 'DOMINGO',
    ];

    /** @var array<string, string> */
    private const DAY_LABELS = [
        'LUNES' => 'Lunes',
        'MARTES' => 'Martes',
        'MIERCOLES' => 'Miércoles',
        'JUEVES' => 'Jueves',
        'VIERNES' => 'Viernes',
        'SABADO' => 'Sábado',
        'DOMINGO' => 'Domingo',
    ];

    /** @var array<string, string> */
    public const DISTRIBUTION_STATUS_OPTIONS = [
        'ready' => 'Listo en emisión',
        'in_progress' => 'En proceso',
        'needs_attention' => 'Requiere atención',
        'pending_editorial' => 'Pendiente editorial',
        'r2_only' => 'Audio en R2',
        'unprocessed' => 'Sin procesar (borrador)',
    ];

    /** @var array<string, string> */
    public const SORT_OPTIONS = [
        'uploaded_at' => 'Fecha de subida',
        'emission_date' => 'Fecha de emisión',
    ];

    /**
     * Extrae y normaliza los filtros desde la petición respetando permisos del usuario.
     *
     * @return array<string, mixed>
     */
    public function extractFilters(Request $request, User $viewer): array
    {
        $isAdmin = $viewer->hasAdminAccess();

        $programId = $request->filled('master_program_id')
            ? max(0, (int) $request->query('master_program_id'))
            : null;
        if ($programId === 0) {
            $programId = null;
        }

        $rawSubmittedBy = trim((string) $request->query('submitted_by_user_id', ''));
        $submittedByFilter = null;
        if ($isAdmin && $rawSubmittedBy !== '') {
            if ($rawSubmittedBy === self::UNREGISTERED_USER_FILTER) {
                $submittedByFilter = self::UNREGISTERED_USER_FILTER;
            } elseif (ctype_digit($rawSubmittedBy) && (int) $rawSubmittedBy > 0) {
                $submittedByFilter = (int) $rawSubmittedBy;
            }
        }

        $uploadedFrom = $this->parseDateFilter($request->query('uploaded_from'));
        $uploadedTo = $this->parseDateFilter($request->query('uploaded_to'));
        $emissionFrom = $this->parseDateFilter($request->query('emission_from'));
        $emissionTo = $this->parseDateFilter($request->query('emission_to'));

        $distributionStatus = trim((string) $request->query('distribution_status', ''));
        if (! array_key_exists($distributionStatus, self::DISTRIBUTION_STATUS_OPTIONS)) {
            $distributionStatus = '';
        }

        $sortBy = trim((string) $request->query('sort_by', 'uploaded_at'));
        if (! array_key_exists($sortBy, self::SORT_OPTIONS)) {
            $sortBy = 'uploaded_at';
        }

        $sortDir = strtolower(trim((string) $request->query('sort_dir', 'desc')));
        if (! in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = 'desc';
        }

        return [
            'master_program_id' => $programId,
            'submitted_by_user_id' => $submittedByFilter,
            'uploaded_from' => $uploadedFrom,
            'uploaded_to' => $uploadedTo,
            'emission_from' => $emissionFrom,
            'emission_to' => $emissionTo,
            'distribution_status' => $distributionStatus,
            'sort_by' => $sortBy,
            'sort_dir' => $sortDir,
        ];
    }

    /**
     * Construye la consulta filtrada, ordenada de forma estable y acotada a permisos.
     * Puramente de lectura: no dispara trabajos, correos ni modificaciones.
     *
     * @param  array<string, mixed>  $filters
     */
    public function buildQuery(User $viewer, array $filters): Builder
    {
        $query = RadioProgram::query()->with([
            'masterProgram.emisiones',
            'submittedBy:id,name,email',
            'lateSubmissionAuthorizer:id,name',
            'events' => static function ($q): void {
                $q->orderBy('created_at')->orderBy('id');
            },
        ]);

        $this->applyViewerScope($query, $viewer);

        if (! empty($filters['master_program_id'])) {
            $query->where('master_program_id', (int) $filters['master_program_id']);
        }

        if ($viewer->hasAdminAccess() && $filters['submitted_by_user_id'] !== null) {
            if ($filters['submitted_by_user_id'] === self::UNREGISTERED_USER_FILTER) {
                $query->whereNull('submitted_by_user_id');
            } else {
                $query->where('submitted_by_user_id', (int) $filters['submitted_by_user_id']);
            }
        }

        $appTimezone = (string) config('app.timezone', 'Europe/Madrid');

        // Filtro independiente de subida (created_at)
        if (! empty($filters['uploaded_from'])) {
            $start = Carbon::createFromFormat('Y-m-d', (string) $filters['uploaded_from'], $appTimezone)->startOfDay();
            $query->where('created_at', '>=', $start);
        }

        if (! empty($filters['uploaded_to'])) {
            $end = Carbon::createFromFormat('Y-m-d', (string) $filters['uploaded_to'], $appTimezone)->endOfDay();
            $query->where('created_at', '<=', $end);
        }

        // Filtro independiente de emisión prevista (fecha_emision) — nunca usa created_at
        if (! empty($filters['emission_from'])) {
            $query->whereDate('fecha_emision', '>=', (string) $filters['emission_from']);
        }

        if (! empty($filters['emission_to'])) {
            $query->whereDate('fecha_emision', '<=', (string) $filters['emission_to']);
        }

        if (! empty($filters['distribution_status'])) {
            $this->applyDistributionStatusFilter($query, (string) $filters['distribution_status']);
        }

        $sortBy = (string) ($filters['sort_by'] ?? 'uploaded_at');
        $sortDir = (string) ($filters['sort_dir'] ?? 'desc');

        if ($sortBy === 'emission_date') {
            $query
                ->orderByRaw('CASE WHEN fecha_emision IS NULL THEN 1 ELSE 0 END ASC')
                ->orderBy('fecha_emision', $sortDir)
                ->orderBy('hora_inicio', $sortDir)
                ->orderBy('id', $sortDir);
        } else {
            $query
                ->orderBy('created_at', $sortDir)
                ->orderBy('id', $sortDir);
        }

        return $query;
    }

    /**
     * Devuelve el listado paginado de episodios con filas presentadas.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getPaginatedReport(Request $request, User $viewer, array $filters, int $perPage = 25): array
    {
        $appTimezone = (string) config('app.timezone', 'Europe/Madrid');
        $returnToUrl = $request->fullUrl();
        $isAdmin = $viewer->hasAdminAccess();

        /** @var LengthAwarePaginator $paginator */
        $paginator = $this->buildQuery($viewer, $filters)
            ->paginate($perPage)
            ->withQueryString();

        $rows = $paginator->getCollection()
            ->map(fn (RadioProgram $episode): array => $this->presentEpisode($episode, $appTimezone, $returnToUrl, $isAdmin))
            ->values();

        return [
            'paginator' => $paginator,
            'rows' => $rows,
            'filters' => $filters,
            'filterOptions' => $this->getFilterOptions($viewer),
            'activeFilterLabels' => $this->describeActiveFilters($filters, $viewer),
            'appTimezone' => $appTimezone,
            'programTimezones' => $this->collectTimezones($rows),
            'printQuery' => $this->buildPrintQuery($filters),
        ];
    }

    /**
     * Devuelve TODOS los resultados filtrados (sin paginación) para la vista de impresión/PDF.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getPrintReport(Request $request, User $viewer, array $filters): array
    {
        $appTimezone = (string) config('app.timezone', 'Europe/Madrid');
        $isAdmin = $viewer->hasAdminAccess();

        $episodes = $this->buildQuery($viewer, $filters)->get();
        $backRoute = $isAdmin
            ? route('admin.podcast-uploads.deliveries.index', $this->buildPrintQuery($filters))
            : route('conductor.episodes.deliveries.index', $this->buildPrintQuery($filters));

        $rows = $episodes
            ->map(fn (RadioProgram $episode): array => $this->presentEpisode($episode, $appTimezone, $backRoute, $isAdmin))
            ->values();

        return [
            'rows' => $rows,
            'totalCount' => $rows->count(),
            'filters' => $filters,
            'activeFilterLabels' => $this->describeActiveFilters($filters, $viewer),
            'generatedAt' => Carbon::now($appTimezone),
            'appTimezone' => $appTimezone,
            'programTimezones' => $this->collectTimezones($rows),
            'backUrl' => $backRoute,
            'isAdmin' => $isAdmin,
        ];
    }

    /**
     * Presenta un episodio con todos los datos reales, sin inventar históricos ni modificar registros.
     *
     * @return array<string, mixed>
     */
    public function presentEpisode(RadioProgram $episode, string $appTimezone, string $returnToUrl, bool $isAdmin): array
    {
        $master = $episode->masterProgram;
        $programName = trim((string) ($master?->nombre ?: $episode->titulo_programa ?: 'Sin programa maestro'));
        $episodeTitle = trim((string) ($episode->live_title ?: $episode->titulo_programa ?: 'Sin título'));

        // Responsable original de la subida (nunca se atribuye al admin actual ni al conductor por suposición)
        $hasRegisteredSubmitter = $episode->submitted_by_user_id !== null && $episode->submittedBy !== null;
        $submittedByName = $hasRegisteredSubmitter
            ? (string) $episode->submittedBy->name
            : 'Usuario no registrado';
        $submittedByEmail = $hasRegisteredSubmitter ? (string) ($episode->submittedBy->email ?? '') : null;

        // Fecha y hora reales de subida
        $uploadedAtCarbon = $episode->created_at?->copy()->setTimezone($appTimezone);
        $uploadedDateFormatted = $uploadedAtCarbon ? $uploadedAtCarbon->format('d/m/Y') : 'Sin fecha';
        $uploadedTimeFormatted = $uploadedAtCarbon ? $uploadedAtCarbon->format('H:i') : null;

        // Zona horaria del programa maestro (sin inventar)
        $rawMasterTz = trim((string) ($master?->timezone ?? ''));
        $validMasterTz = ($rawMasterTz !== '' && in_array($rawMasterTz, timezone_identifiers_list(), true))
            ? $rawMasterTz
            : null;

        // Fecha y hora previstas de emisión (NUNCA usa created_at)
        $emission = $this->resolveEmissionSchedule($episode, $master, $validMasterTz);

        // Revisiones y sustituciones de audio disponibles
        $revisions = $this->extractRevisions($episode, $appTimezone);

        // Estado de distribución consolidado
        $distribution = $this->resolveDistributionStatus($episode);

        // Enlace al detalle existente preservando los filtros en return_to
        $detailUrl = $isAdmin
            ? route('admin.podcast-uploads.editorial.show', [
                'radioProgram' => $episode->id,
                'return_to' => $returnToUrl,
            ])
            : route('conductor.episodes.show', [
                'radioProgram' => $episode->id,
                'return_to' => $returnToUrl,
            ]);

        return [
            'id' => (int) $episode->id,
            'episode' => $episode,
            'program_name' => $programName,
            'episode_number' => $episode->numero_episodio,
            'episode_title' => $episodeTitle,
            'submitted_by' => [
                'registered' => $hasRegisteredSubmitter,
                'name' => $submittedByName,
                'email' => $submittedByEmail,
            ],
            'uploaded' => [
                'has_value' => $uploadedAtCarbon !== null,
                'date' => $uploadedDateFormatted,
                'time' => $uploadedTimeFormatted,
                'full' => $uploadedAtCarbon ? $uploadedAtCarbon->format('d/m/Y H:i') : 'Sin fecha de subida',
                'timezone' => $appTimezone,
            ],
            'emission' => $emission,
            'timezone' => [
                'program' => $validMasterTz,
                'program_label' => $validMasterTz ?? 'Sin zona horaria registrada',
                'upload' => $appTimezone,
            ],
            'distribution' => $distribution,
            'revisions' => $revisions,
            'detail_url' => $detailUrl,
        ];
    }

    /**
     * Valida que una URL de retorno sea interna y segura hacia el listado de entregas.
     */
    public function sanitizeReturnToUrl(?string $rawUrl, string $expectedPathPrefix): ?string
    {
        $raw = trim((string) $rawUrl);
        if ($raw === '') {
            return null;
        }

        if (str_starts_with($raw, $expectedPathPrefix)) {
            return $raw;
        }

        $parsed = parse_url($raw);
        if (! is_array($parsed)) {
            return null;
        }

        $path = (string) ($parsed['path'] ?? '');
        if (! str_starts_with($path, $expectedPathPrefix)) {
            return null;
        }

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $urlHost = $parsed['host'] ?? null;
        if ($urlHost !== null && $appHost !== null && strcasecmp((string) $urlHost, (string) $appHost) !== 0) {
            return null;
        }

        $query = isset($parsed['query']) && $parsed['query'] !== '' ? '?' . $parsed['query'] : '';

        return $path . $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveEmissionSchedule(RadioProgram $episode, ?MasterProgram $master, ?string $validMasterTz): array
    {
        $emissionDate = $episode->fecha_emision;

        $isManualAuthorized = $episode->hasActiveLateSubmissionException()
            || (bool) data_get($episode->delivery_metadata, 'fecha_emision_manual', false)
            || (bool) data_get($episode->delivery_metadata, 'manual_emission_date', false)
            || (bool) data_get($episode->delivery_metadata, 'manual_date_authorized', false);

        if ($emissionDate === null) {
            return [
                'date_missing' => true,
                'date' => null,
                'weekday_key' => null,
                'weekday_label' => null,
                'time_missing' => true,
                'time' => null,
                'time_source' => 'missing',
                'time_source_label' => null,
                'slots_count' => 0,
                'calendar_notice' => [
                    'type' => 'missing',
                    'label' => 'Fecha de emisión no registrada',
                    'detail' => 'Este episodio no tiene fecha de emisión guardada.',
                ],
            ];
        }

        $weekdayKey = self::ISO_DAY_KEYS[(int) $emissionDate->dayOfWeekIso] ?? null;
        $weekdayLabel = $weekdayKey !== null ? (self::DAY_LABELS[$weekdayKey] ?? $weekdayKey) : null;
        $dateFormatted = $emissionDate->format('d/m/Y');

        // 1. Hora guardada directamente en el episodio (si existe)
        $storedHoraInicio = trim((string) ($episode->hora_inicio ?? ''));
        $storedHoraFin = trim((string) ($episode->hora_fin ?? ''));

        $timeDisplay = null;
        $timeSource = 'missing';
        $timeSourceLabel = null;
        $slotsCount = 0;

        if ($storedHoraInicio !== '') {
            $startShort = substr($storedHoraInicio, 0, 5);
            $timeDisplay = $storedHoraFin !== '' ? ($startShort . '–' . substr($storedHoraFin, 0, 5)) : $startShort;
            $timeSource = 'episode_stored';
            $timeSourceLabel = 'Hora registrada en episodio';
            $slotsCount = 1;
        }

        // 2. Programación actual del programa maestro (identificada siempre como referencia actual)
        $masterActiveSlotsForDay = collect();
        $masterAllActiveSlots = collect();
        $usesEmisionesTable = false;

        if ($master instanceof MasterProgram) {
            $emisiones = $master->relationLoaded('emisiones') ? $master->emisiones : collect();
            if ($emisiones->isNotEmpty()) {
                $usesEmisionesTable = true;
                $masterAllActiveSlots = $emisiones->where('activo', true)->values();
                if ($weekdayKey !== null) {
                    $masterActiveSlotsForDay = $masterAllActiveSlots
                        ->filter(fn (MasterProgramEmision $em): bool => strtoupper(trim((string) $em->dia_semana)) === $weekdayKey)
                        ->sortBy(fn (MasterProgramEmision $em): string => (string) ($em->hora_inicio ?? '99:99:99'))
                        ->values();
                }
            }
        }

        if ($timeDisplay === null && $master instanceof MasterProgram) {
            if ($usesEmisionesTable && $masterActiveSlotsForDay->isNotEmpty()) {
                $formattedSlots = $masterActiveSlotsForDay
                    ->map(static function (MasterProgramEmision $em): string {
                        $hour = $em->horaFormateada();
                        $tipo = $em->tipo === 'retransmision' ? ' (Retrans.)' : ($em->tipo === 'en_vivo' ? ' (En vivo)' : '');

                        return $hour . $tipo;
                    })
                    ->filter(static fn (string $s): bool => trim($s) !== '')
                    ->values();

                if ($formattedSlots->isNotEmpty()) {
                    $timeDisplay = $formattedSlots->implode(' · ');
                    $timeSource = 'master_current_reference';
                    $timeSourceLabel = 'Referencia actual del programa';
                    $slotsCount = $formattedSlots->count();
                }
            } elseif (! $usesEmisionesTable) {
                $baseDay = strtoupper(trim((string) ($master->dia_transmision ?? '')));
                $baseHour = trim((string) ($master->hora_transmision ?? ''));
                if ($baseHour !== '' && ($baseDay === '' || $baseDay === $weekdayKey)) {
                    $timeDisplay = substr($baseHour, 0, 5);
                    $timeSource = 'master_current_reference';
                    $timeSourceLabel = 'Referencia actual del programa';
                    $slotsCount = 1;
                }
            }
        }

        // 3. Aviso de discrepancia con el calendario aplicable (sin corregir automáticamente)
        $calendarNotice = null;
        if ($master instanceof MasterProgram && $weekdayKey !== null) {
            $scheduleMatches = true;
            $expectedSummary = '';

            if ($usesEmisionesTable) {
                if ($masterAllActiveSlots->isNotEmpty() && $masterActiveSlotsForDay->isEmpty()) {
                    $scheduleMatches = false;
                    $expectedSummary = $masterAllActiveSlots
                        ->map(fn (MasterProgramEmision $em): string => (self::DAY_LABELS[strtoupper(trim((string) $em->dia_semana))] ?? (string) $em->dia_semana) . ' ' . $em->horaFormateada())
                        ->unique()
                        ->implode(', ');
                }
            } else {
                $baseDay = strtoupper(trim((string) ($master->dia_transmision ?? '')));
                if ($baseDay !== '' && isset(self::DAY_LABELS[$baseDay]) && $baseDay !== $weekdayKey) {
                    $scheduleMatches = false;
                    $baseHour = trim((string) ($master->hora_transmision ?? ''));
                    $expectedSummary = self::DAY_LABELS[$baseDay] . ($baseHour !== '' ? ' ' . substr($baseHour, 0, 5) : '');
                }
            }

            if (! $scheduleMatches) {
                if ($isManualAuthorized) {
                    $calendarNotice = [
                        'type' => 'authorized_manual',
                        'label' => 'Fecha manual autorizada',
                        'detail' => "Emisión en {$weekdayLabel} ({$dateFormatted}) autorizada fuera del calendario actual ({$expectedSummary}).",
                    ];
                } else {
                    $calendarNotice = [
                        'type' => 'mismatch',
                        'label' => 'No coincide con calendario actual',
                        'detail' => "Fecha guardada: {$weekdayLabel} ({$dateFormatted}). Calendario actual del programa: {$expectedSummary}.",
                    ];
                }
            }
        }

        // Comprobar también si el campo dia_transmision guardado en el propio episodio discrepa de la fecha
        if ($calendarNotice === null && $weekdayKey !== null) {
            $storedDay = strtoupper(trim((string) ($episode->dia_transmision ?? '')));
            if ($storedDay !== '' && isset(self::DAY_LABELS[$storedDay]) && $storedDay !== $weekdayKey) {
                if ($isManualAuthorized) {
                    $calendarNotice = [
                        'type' => 'authorized_manual',
                        'label' => 'Fecha manual autorizada',
                        'detail' => "Emisión en {$weekdayLabel} ({$dateFormatted}) autorizada manualmente.",
                    ];
                } else {
                    $calendarNotice = [
                        'type' => 'mismatch',
                        'label' => 'Día guardado distinto a la fecha',
                        'detail' => "El episodio tiene día «" . self::DAY_LABELS[$storedDay] . "» pero su fecha de emisión cae en {$weekdayLabel} ({$dateFormatted}).",
                    ];
                }
            }
        }

        return [
            'date_missing' => false,
            'date' => $dateFormatted,
            'date_iso' => $emissionDate->toDateString(),
            'weekday_key' => $weekdayKey,
            'weekday_label' => $weekdayLabel,
            'time_missing' => $timeDisplay === null,
            'time' => $timeDisplay,
            'time_source' => $timeSource,
            'time_source_label' => $timeSourceLabel,
            'slots_count' => $slotsCount,
            'timezone' => $validMasterTz,
            'calendar_notice' => $calendarNotice,
        ];
    }

    /**
     * Extrae historial de revisiones y sustituciones de audio sin sobrescribir la subida original.
     *
     * @return array{count: int, has_revisions: bool, has_audio_substitution: bool, items: array<int, array<string, mixed>>}
     */
    private function extractRevisions(RadioProgram $episode, string $appTimezone): array
    {
        $items = [];
        $audioPaths = [];

        $payload = is_array($episode->editorial_original_payload) ? $episode->editorial_original_payload : [];
        $history = is_array($payload['history'] ?? null) ? $payload['history'] : [];

        foreach ($history as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $audio = trim((string) ($entry['archivo_mp3'] ?? ''));
            if ($audio !== '') {
                $audioPaths[] = $audio;
            }
            $recordedAt = ! empty($entry['recorded_at'])
                ? $this->formatIsoTimestamp((string) $entry['recorded_at'], $appTimezone)
                : null;

            $items[] = [
                'source' => 'snapshot',
                'version' => isset($entry['version']) ? (int) $entry['version'] : null,
                'timestamp' => $recordedAt,
                'label' => $this->translateRevisionReason((string) ($entry['reason'] ?? 'revision')),
                'detail' => $audio !== '' ? basename($audio) : null,
            ];
        }

        if (isset($payload['current']) && is_array($payload['current'])) {
            $currentAudio = trim((string) ($payload['current']['archivo_mp3'] ?? ''));
            if ($currentAudio !== '') {
                $audioPaths[] = $currentAudio;
            }
        }

        $stagedPath = trim((string) data_get($episode->delivery_metadata, 'staged_replacement_mp3_path', ''));
        if ($stagedPath !== '') {
            $audioPaths[] = $stagedPath;
            $stagedAt = data_get($episode->delivery_metadata, 'staged_replacement_at');
            $items[] = [
                'source' => 'staged_audio',
                'version' => (int) ($episode->editorial_version ?: 1),
                'timestamp' => is_string($stagedAt) ? $this->formatIsoTimestamp($stagedAt, $appTimezone) : null,
                'label' => 'Sustitución de audio en espera de aprobación',
                'detail' => basename($stagedPath),
            ];
        }

        $relevantEventTypes = [
            'CONDUCTOR_SUBMISSION_UPDATED',
            'CONDUCTOR_SUBMISSION_UPDATED_AFTER_APPROVAL',
            'ADMIN_EDITORIAL_UPDATED',
            'ADMIN_EDITORIAL_POST_DELIVERY_UPDATED',
            'AUDIO_REPLACED',
        ];

        $events = $episode->relationLoaded('events') ? $episode->events : collect();
        $hasEventAudioReprocess = false;

        foreach ($events as $event) {
            $type = (string) ($event->event_type ?? '');
            if (! in_array($type, $relevantEventTypes, true)) {
                continue;
            }

            $meta = is_array($event->metadata) ? $event->metadata : [];
            if (! empty($meta['audio_reprocessed']) || ! empty($meta['had_prepared_audio']) || $type === 'AUDIO_REPLACED') {
                $hasEventAudioReprocess = true;
            }

            $items[] = [
                'source' => 'event',
                'version' => isset($meta['new_version']) ? (int) $meta['new_version'] : null,
                'timestamp' => $event->created_at?->copy()->setTimezone($appTimezone)->format('d/m/Y H:i'),
                'label' => (string) ($event->event_message ?: $type),
                'detail' => ! empty($meta['audio_reprocessed']) ? 'Audio reprocesado' : null,
            ];
        }

        $uniqueAudioPaths = array_values(array_unique(array_filter($audioPaths)));
        $hasAudioSubstitution = count($uniqueAudioPaths) > 1 || $stagedPath !== '' || $hasEventAudioReprocess;
        $editorialVersion = max(1, (int) ($episode->editorial_version ?: 1));
        $hasRevisions = $editorialVersion > 1 || count($items) > 0 || $hasAudioSubstitution;

        return [
            'version' => $editorialVersion,
            'count' => count($items),
            'has_revisions' => $hasRevisions,
            'has_audio_substitution' => $hasAudioSubstitution,
            'items' => $items,
        ];
    }

    /**
     * Clasifica el estado de distribución reutilizando exactamente la misma lógica que recent-uploads.
     *
     * @return array{code: string, label: string, tone: string, detail: string}
     */
    private function resolveDistributionStatus(RadioProgram $episode): array
    {
        $editorialStatus = (string) ($episode->editorial_status ?: RadioProgram::EDITORIAL_STATUS_NOT_REQUIRED);
        $isProcessedInR2 = $episode->isProcessedInR2();

        $hasAudioFailed = ! $episode->isProcessedPath() && (
            $episode->delivery_status === 'delivery_failed'
            || filled($episode->delivery_last_error)
            || ($episode->radioboss_status === 'radioboss_error' && str_contains((string) $episode->radioboss_last_error, 'no es una versión procesada definitiva'))
        );

        $isProcessingAudio = $episode->processing_started_at !== null
            && $episode->processing_finished_at === null
            && ! $hasAudioFailed;

        $radioStatus = (string) ($episode->radioboss_status ?? ($episode->enviado_radioboss ? 'radioboss_verified' : 'radioboss_pending'));
        $archiveStatus = (string) ($episode->archive_org_status ?? 'archive_pending');
        $deliveryStatus = (string) ($episode->delivery_status ?? 'delivery_pending');

        $isAudioDistributed = $radioStatus === 'radioboss_verified'
            && in_array($archiveStatus, ['archive_verified', 'archive_skipped'], true);

        $rbNotifAccepted = $episode->radioboss_notification_sent_at !== null
            || data_get($episode->delivery_metadata, 'radioboss_notification_status') === 'accepted_smtp';
        $archNotifAccepted = $episode->archive_notification_sent_at !== null
            || data_get($episode->delivery_metadata, 'archive_notification_status') === 'accepted_smtp';

        $rbNotifError = data_get($episode->delivery_metadata, 'radioboss_notification_error');
        $archNotifError = data_get($episode->delivery_metadata, 'archive_notification_error');

        $hasNotificationError = filled($rbNotifError) || filled($archNotifError);
        $hasNotificationPending = $isAudioDistributed
            && (! $rbNotifAccepted || (($episode->masterProgram?->sync_archive_org ?? true) && ! $archNotifAccepted));

        $hasErrors = $hasAudioFailed
            || $radioStatus === 'radioboss_error'
            || $archiveStatus === 'archive_error'
            || $deliveryStatus === 'delivery_failed'
            || $editorialStatus === RadioProgram::EDITORIAL_STATUS_RETAINED;

        $hasActivePipeline = ! $isAudioDistributed && (
            in_array($radioStatus, ['radioboss_pending', 'processing', 'uploading'], true)
            || in_array($archiveStatus, ['archive_pending', 'archive_pending_indexing', 'processing', 'uploading'], true)
            || in_array($deliveryStatus, ['delivery_pending', 'processing', 'uploading'], true)
            || $isProcessingAudio
        );

        if ($hasErrors) {
            return [
                'code' => 'needs_attention',
                'label' => 'Requiere atención',
                'tone' => 'danger',
                'detail' => (string) ($episode->delivery_last_error ?: $episode->radioboss_last_error ?: $episode->archive_org_last_error ?: 'Error o retención editorial'),
            ];
        }

        if (in_array($editorialStatus, [RadioProgram::EDITORIAL_STATUS_PENDING, RadioProgram::EDITORIAL_STATUS_CHANGES_REQUESTED], true)) {
            return [
                'code' => 'pending_editorial',
                'label' => $editorialStatus === RadioProgram::EDITORIAL_STATUS_CHANGES_REQUESTED
                    ? 'Cambios editoriales solicitados'
                    : 'Pendiente revisión editorial',
                'tone' => 'warning',
                'detail' => 'En espera de validación editorial antes de distribuir.',
            ];
        }

        if ($isAudioDistributed && $hasNotificationError) {
            return [
                'code' => 'ready',
                'label' => 'Distribuido (notificación fallida)',
                'tone' => 'warning',
                'detail' => (string) ($rbNotifError ?: $archNotifError),
            ];
        }

        if ($isAudioDistributed && $hasNotificationPending) {
            return [
                'code' => 'ready',
                'label' => 'Distribuido (notif. pendiente)',
                'tone' => 'info',
                'detail' => 'RadioBOSS y Archive.org completados; notificación en cola.',
            ];
        }

        if ($isAudioDistributed) {
            return [
                'code' => 'ready',
                'label' => 'Listo en emisión',
                'tone' => 'success',
                'detail' => 'Verificado en RadioBOSS y Archive.org.',
            ];
        }

        if ($hasActivePipeline) {
            return [
                'code' => 'in_progress',
                'label' => 'En proceso',
                'tone' => 'warning',
                'detail' => (string) ($episode->status_message ?: 'Procesamiento o distribución en curso.'),
            ];
        }

        if ($isProcessedInR2) {
            return [
                'code' => 'r2_only',
                'label' => 'Audio en R2',
                'tone' => 'info',
                'detail' => 'MP3 definitivo disponible en R2, pendiente de distribución final.',
            ];
        }

        return [
            'code' => 'unprocessed',
            'label' => 'Sin procesar',
            'tone' => 'neutral',
            'detail' => (string) ($episode->status_message ?: 'Guardado sin distribución iniciada.'),
        ];
    }

    private function applyViewerScope(Builder $query, User $viewer): void
    {
        if ($viewer->hasAdminAccess()) {
            return;
        }

        if ($viewer->hasConductorAccess()) {
            $authorizedIds = $viewer->authorizedMasterPrograms()
                ->pluck('master_programs.id')
                ->map(static fn ($id): int => (int) $id)
                ->all();

            if ($authorizedIds === []) {
                $query->whereRaw('1 = 0');

                return;
            }

            $query
                ->where('submitted_by_user_id', (int) $viewer->id)
                ->whereIn('master_program_id', $authorizedIds);

            return;
        }

        $query->whereRaw('1 = 0');
    }

    private function applyDistributionStatusFilter(Builder $query, string $status): void
    {
        match ($status) {
            'ready' => $query
                ->where(function (Builder $q): void {
                    $q->where('radioboss_status', 'radioboss_verified')
                        ->orWhere('enviado_radioboss', true);
                })
                ->whereIn('archive_org_status', ['archive_verified', 'archive_skipped'])
                ->where(function (Builder $q): void {
                    $q->whereNull('delivery_status')
                        ->orWhere('delivery_status', '!=', 'delivery_failed');
                })
                ->where(function (Builder $q): void {
                    $q->whereNull('editorial_status')
                        ->orWhere('editorial_status', '!=', RadioProgram::EDITORIAL_STATUS_RETAINED);
                }),

            'needs_attention' => $query->where(function (Builder $q): void {
                $q->where('delivery_status', 'delivery_failed')
                    ->orWhere('radioboss_status', 'radioboss_error')
                    ->orWhere('archive_org_status', 'archive_error')
                    ->orWhere('editorial_status', RadioProgram::EDITORIAL_STATUS_RETAINED);
            }),

            'pending_editorial' => $query->whereIn('editorial_status', [
                RadioProgram::EDITORIAL_STATUS_PENDING,
                RadioProgram::EDITORIAL_STATUS_CHANGES_REQUESTED,
            ]),

            'unprocessed' => $query
                ->where('delivery_status', 'skipped')
                ->where('radioboss_status', 'skipped')
                ->where('archive_org_status', 'skipped'),

            'in_progress' => $query
                ->where(function (Builder $q): void {
                    $q->whereIn('radioboss_status', ['radioboss_pending', 'processing', 'uploading'])
                        ->orWhereIn('archive_org_status', ['archive_pending', 'archive_pending_indexing', 'processing', 'uploading'])
                        ->orWhereIn('delivery_status', ['delivery_pending', 'processing', 'uploading'])
                        ->orWhere(function (Builder $sub): void {
                            $sub->whereNotNull('processing_started_at')
                                ->whereNull('processing_finished_at');
                        });
                })
                ->where(function (Builder $q): void {
                    $q->whereNull('delivery_status')
                        ->orWhere('delivery_status', '!=', 'delivery_failed');
                })
                ->where(function (Builder $q): void {
                    $q->whereNull('radioboss_status')
                        ->orWhere('radioboss_status', '!=', 'radioboss_error');
                })
                ->where(function (Builder $q): void {
                    $q->whereNull('archive_org_status')
                        ->orWhere('archive_org_status', '!=', 'archive_error');
                })
                ->where(function (Builder $q): void {
                    $q->whereNull('editorial_status')
                        ->orWhereNotIn('editorial_status', [
                            RadioProgram::EDITORIAL_STATUS_RETAINED,
                            RadioProgram::EDITORIAL_STATUS_PENDING,
                            RadioProgram::EDITORIAL_STATUS_CHANGES_REQUESTED,
                        ]);
                })
                ->whereNot(function (Builder $q): void {
                    $q->where(function (Builder $rb): void {
                        $rb->where('radioboss_status', 'radioboss_verified')
                            ->orWhere('enviado_radioboss', true);
                    })->whereIn('archive_org_status', ['archive_verified', 'archive_skipped']);
                }),

            'r2_only' => $query
                ->where('archivo_mp3_disk', 'r2')
                ->where('archivo_mp3', 'like', 'programas_procesados/%')
                ->whereNot(function (Builder $q): void {
                    $q->where(function (Builder $rb): void {
                        $rb->where('radioboss_status', 'radioboss_verified')
                            ->orWhere('enviado_radioboss', true);
                    })->whereIn('archive_org_status', ['archive_verified', 'archive_skipped']);
                }),

            default => null,
        };
    }

    /**
     * @return array{programs: Collection<int, MasterProgram>, submitters: Collection<int, User>, hasUnregistered: bool}
     */
    private function getFilterOptions(User $viewer): array
    {
        if ($viewer->hasAdminAccess()) {
            $programs = Schema::hasTable('master_programs')
                ? MasterProgram::query()->orderBy('nombre')->get(['id', 'nombre', 'timezone'])
                : collect();

            $submitterIds = RadioProgram::query()
                ->whereNotNull('submitted_by_user_id')
                ->distinct()
                ->pluck('submitted_by_user_id')
                ->map(static fn ($id): int => (int) $id)
                ->all();

            $submitters = $submitterIds !== []
                ? User::query()->whereIn('id', $submitterIds)->orderBy('name')->get(['id', 'name', 'email'])
                : collect();

            $hasUnregistered = RadioProgram::query()->whereNull('submitted_by_user_id')->exists();

            return [
                'programs' => $programs,
                'submitters' => $submitters,
                'hasUnregistered' => $hasUnregistered,
            ];
        }

        $programs = $viewer->hasConductorAccess()
            ? $viewer->authorizedMasterPrograms()->orderBy('nombre')->get(['master_programs.id', 'master_programs.nombre', 'master_programs.timezone'])
            : collect();

        return [
            'programs' => $programs,
            'submitters' => collect([$viewer]),
            'hasUnregistered' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, string>
     */
    private function describeActiveFilters(array $filters, User $viewer): array
    {
        $labels = [];

        if (! empty($filters['master_program_id'])) {
            $programName = MasterProgram::query()->whereKey((int) $filters['master_program_id'])->value('nombre');
            $labels[] = 'Programa: ' . ($programName ?: ('#' . $filters['master_program_id']));
        }

        if ($viewer->hasAdminAccess() && $filters['submitted_by_user_id'] !== null) {
            if ($filters['submitted_by_user_id'] === self::UNREGISTERED_USER_FILTER) {
                $labels[] = 'Subido por: Usuario no registrado';
            } else {
                $userName = User::query()->whereKey((int) $filters['submitted_by_user_id'])->value('name');
                $labels[] = 'Subido por: ' . ($userName ?: ('Usuario #' . $filters['submitted_by_user_id']));
            }
        }

        if (! empty($filters['uploaded_from']) || ! empty($filters['uploaded_to'])) {
            $from = ! empty($filters['uploaded_from']) ? Carbon::parse((string) $filters['uploaded_from'])->format('d/m/Y') : 'inicio';
            $to = ! empty($filters['uploaded_to']) ? Carbon::parse((string) $filters['uploaded_to'])->format('d/m/Y') : 'hoy';
            $labels[] = "Subida: {$from} – {$to}";
        }

        if (! empty($filters['emission_from']) || ! empty($filters['emission_to'])) {
            $from = ! empty($filters['emission_from']) ? Carbon::parse((string) $filters['emission_from'])->format('d/m/Y') : 'inicio';
            $to = ! empty($filters['emission_to']) ? Carbon::parse((string) $filters['emission_to'])->format('d/m/Y') : 'sin límite';
            $labels[] = "Emisión: {$from} – {$to}";
        }

        if (! empty($filters['distribution_status'])) {
            $labels[] = 'Estado: ' . (self::DISTRIBUTION_STATUS_OPTIONS[$filters['distribution_status']] ?? $filters['distribution_status']);
        }

        $sortByLabel = self::SORT_OPTIONS[$filters['sort_by'] ?? 'uploaded_at'] ?? 'Fecha de subida';
        $sortDirLabel = ($filters['sort_dir'] ?? 'desc') === 'asc' ? 'ascendente' : 'descendente';
        $labels[] = "Orden: {$sortByLabel} ({$sortDirLabel})";

        return $labels;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function buildPrintQuery(array $filters): array
    {
        return array_filter([
            'master_program_id' => $filters['master_program_id'],
            'submitted_by_user_id' => $filters['submitted_by_user_id'],
            'uploaded_from' => $filters['uploaded_from'],
            'uploaded_to' => $filters['uploaded_to'],
            'emission_from' => $filters['emission_from'],
            'emission_to' => $filters['emission_to'],
            'distribution_status' => $filters['distribution_status'],
            'sort_by' => $filters['sort_by'],
            'sort_dir' => $filters['sort_dir'],
        ], static fn ($value): bool => $value !== null && $value !== '');
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<int, string>
     */
    private function collectTimezones(Collection $rows): array
    {
        return $rows
            ->pluck('timezone.program')
            ->filter(static fn ($tz): bool => is_string($tz) && trim($tz) !== '')
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function parseDateFilter(mixed $raw): ?string
    {
        $value = trim((string) ($raw ?? ''));
        if ($value === '') {
            return null;
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function formatIsoTimestamp(string $raw, string $appTimezone): ?string
    {
        try {
            return Carbon::parse($raw)->setTimezone($appTimezone)->format('d/m/Y H:i');
        } catch (\Throwable) {
            return null;
        }
    }

    private function translateRevisionReason(string $reason): string
    {
        return match ($reason) {
            'initial_submission' => 'Entrega inicial registrada',
            'conductor_revision' => 'Revisión enviada por el conductor',
            'post_approval_modification' => 'Modificación posterior a aprobación',
            'preserved_before_admin_edit' => 'Respaldo previo a edición administrativa',
            'preserved_before_ai_proposal' => 'Respaldo previo a propuesta IA',
            default => 'Revisión registrada (' . $reason . ')',
        };
    }
}

