<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\PublicMediaUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class MasterProgram extends Model
{
    use HasFactory;
    use Auditable;

    public const EDITORIAL_MODE_MANDATORY = 'mandatory_review';
    public const EDITORIAL_MODE_AUTONOMOUS = 'autonomous';

    protected $table = 'master_programs';

    protected $fillable = [
        'nombre',
        'program_code',
        'storage_folder',
        'code_prefix',
        'conductor',
        'pais',
        'dia_transmision',
        'hora_transmision',
        'timezone',
        'duracion_minutos',
        'submission_cutoff_minutes',
        'genero',
        'caratula_url',
        'descripcion',
        'live_title',
        'live_description',
        'live_image_url',
        'live_starts_at',
        'live_ends_at',
        'default_news_ids',
        'live_news_ids',
        'preview_news_ids',
        'comentario_predeterminado',
        'red_social1_url',
        'red_social2_url',
        'activo',
        'editorial_mode',
        'editorial_guide',
        'autonomous_authorized_by',
        'autonomous_authorized_at',
        'sync_archive_org',
        'archive_identifier',
        'vistas_archive',
        'escuchas_locales',
        'vistas_totales',
        'stats_updated_at',
        'ruta_ftp',
        'email_notificacion',
        'email_copia_notificacion',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'sync_archive_org' => 'boolean',
        'duracion_minutos' => 'integer',
        'submission_cutoff_minutes' => 'integer',
        'default_news_ids' => 'array',
        'live_news_ids' => 'array',
        'preview_news_ids' => 'array',
        'editorial_guide' => 'array',
        'autonomous_authorized_by' => 'integer',
        'autonomous_authorized_at' => 'datetime',
        'live_starts_at' => 'datetime',
        'live_ends_at' => 'datetime',
        'stats_updated_at' => 'datetime',
        'vistas_archive' => 'integer',
        'escuchas_locales' => 'integer',
        'vistas_totales' => 'integer',
    ];

    protected static function booted(): void
    {
        $bumpScheduleVersion = static function (): void {
            \Illuminate\Support\Facades\Cache::forever('cache.version.program_schedule', now()->timestamp);
            \Illuminate\Support\Facades\Cache::forget('site.programs.catalog');
        };

        static::saved($bumpScheduleVersion);
        static::deleted($bumpScheduleVersion);

        static::saving(function (self $program): void {
            if (! filled($program->program_code)) {
                $program->program_code = static::generateUniqueProgramCode(
                    (string) ($program->code_prefix ?: $program->name ?: $program->nombre ?: 'PROGRAMA'),
                    $program->getKey()
                );
            } else {
                $program->program_code = static::normalizeProgramCode((string) $program->program_code);
            }

            if ($program->program_code === '') {
                $program->program_code = static::generateUniqueProgramCode((string) ($program->name ?: 'PROGRAMA'), $program->getKey());
            }

            if (! filled($program->code_prefix)) {
                $program->code_prefix = static::normalizeProgramCode((string) ($program->name ?: $program->nombre ?: 'PROGRAMA'));
            } else {
                $program->code_prefix = static::normalizeProgramCode((string) $program->code_prefix);
            }

            if (Schema::hasColumn($program->getTable(), 'storage_folder')) {
                $originalFolder = trim((string) ($program->getOriginal('storage_folder') ?? ''));
                if ($program->exists && $originalFolder !== '' && ! $program->isDirty('storage_folder')) {
                    $program->storage_folder = $originalFolder;
                } elseif (! filled($program->storage_folder)) {
                    $program->storage_folder = static::generateUniqueStorageFolder(
                        (string) ($program->nombre ?: $program->name ?: $program->program_code ?: 'programa'),
                        $program->getKey()
                    );
                } else {
                    $program->storage_folder = static::generateUniqueStorageFolder(
                        (string) $program->storage_folder,
                        $program->getKey()
                    );
                }
            }

            if (Schema::hasColumn($program->getTable(), 'editorial_mode')) {
                $mode = trim((string) ($program->editorial_mode ?? ''));
                if (! in_array($mode, [self::EDITORIAL_MODE_MANDATORY, self::EDITORIAL_MODE_AUTONOMOUS], true)) {
                    $program->editorial_mode = self::EDITORIAL_MODE_MANDATORY;
                }
            }
        });
    }

    public function radioPrograms(): HasMany
    {
        return $this->hasMany(RadioProgram::class, 'master_program_id');
    }

    /**
     * Emisiones semanales del programa, ordenadas por día y hora.
     * La tabla `master_program_emisiones` permite varios slots por programa
     * (normal, retransmisión/podcast, en vivo).
     */
    public function emisiones(): HasMany
    {
        return $this->hasMany(MasterProgramEmision::class, 'master_program_id')
            ->orderByRaw(
                "CASE UPPER(dia_semana)
                    WHEN 'LUNES'     THEN 1
                    WHEN 'MARTES'    THEN 2
                    WHEN 'MIERCOLES' THEN 3
                    WHEN 'JUEVES'    THEN 4
                    WHEN 'VIERNES'   THEN 5
                    WHEN 'SABADO'    THEN 6
                    WHEN 'DOMINGO'   THEN 7
                    ELSE 99
                END"
            )
            ->orderBy('hora_inicio');
    }

    /**
     * Garantiza compatibilidad retroactiva: si el programa tiene
     * `dia_transmision` y `hora_transmision` pero NO tiene ninguna emisión
     * de tipo 'normal', la crea automáticamente.
     * Se debe llamar tras cada guardado del programa desde el panel.
     */
    public function syncEmisionNormal(): void
    {
        $dia  = strtoupper(trim((string) $this->dia_transmision));
        $hora = trim((string) $this->hora_transmision);

        if ($dia === '' || $hora === '') {
            return;
        }

        $existing = $this->emisiones()->where('tipo', 'normal')->first();

        if ($existing) {
            // Si ya existe una emisión normal, solo actualizamos los datos de horario base si están desfasados
            $existing->update([
                'dia_semana'       => $dia,
                'hora_inicio'      => $hora,
                'duracion_minutos' => $this->duracion_minutos ?? 120,
            ]);
        } else {
            $this->emisiones()->create([
                'tipo'             => 'normal',
                'dia_semana'       => $dia,
                'hora_inicio'      => $hora,
                'duracion_minutos' => $this->duracion_minutos ?? 120,
                'activo'           => true,
            ]);
        }
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(ProgramInvitation::class, 'master_program_id');
    }

    public function outreachContacts(): HasMany
    {
        return $this->hasMany(BandContact::class, 'program_code', 'program_code');
    }

    public function outreachCampaigns(): HasMany
    {
        return $this->hasMany(OutreachCampaign::class, 'program_code', 'program_code');
    }

    public function conductors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'master_program_conductor', 'master_program_id', 'user_id')
            ->withPivot(['is_active', 'authorized_by', 'authorized_at'])
            ->withTimestamps();
    }

    public function authorizedConductors(): BelongsToMany
    {
        return $this->conductors()->wherePivot('is_active', true);
    }

    public function authorizeConductor(User|int $user, ?int $authorizedBy = null): void
    {
        $userId = $user instanceof User ? (int) $user->id : (int) $user;
        if ($userId <= 0 || ! Schema::hasTable('master_program_conductor')) {
            return;
        }

        $this->conductors()->syncWithoutDetaching([
            $userId => [
                'is_active' => true,
                'authorized_by' => $authorizedBy,
                'authorized_at' => now(),
            ],
        ]);

        if (Schema::hasColumn('users', 'is_conductor')) {
            User::query()->whereKey($userId)->update(['is_conductor' => true]);
        }
    }

    public function revokeConductor(User|int $user): void
    {
        $userId = $user instanceof User ? (int) $user->id : (int) $user;
        if ($userId <= 0 || ! Schema::hasTable('master_program_conductor')) {
            return;
        }

        $this->conductors()->updateExistingPivot($userId, [
            'is_active' => false,
        ]);
    }

    public function resolveStorageFolder(): string
    {
        $hasColumn = Schema::hasColumn($this->getTable(), 'storage_folder');
        $existing = trim((string) ($this->getAttribute('storage_folder') ?? ''));

        if ($existing !== '') {
            return static::normalizeStorageFolder($existing);
        }

        $generated = static::generateUniqueStorageFolder(
            (string) ($this->nombre ?: $this->name ?: $this->program_code ?: 'programa-' . ($this->id ?: 'general')),
            $this->exists ? (int) $this->getKey() : null
        );

        $this->setAttribute('storage_folder', $generated);

        if ($this->exists && $hasColumn) {
            static::withoutEvents(function () use ($generated): void {
                static::query()->whereKey($this->getKey())->update(['storage_folder' => $generated]);
            });
        }

        return $generated;
    }

    public static function normalizeStorageFolder(string $value): string
    {
        $slug = Str::slug($value, '-');
        $slug = preg_replace('/[^a-z0-9\-]+/', '-', strtolower($slug)) ?: '';
        $slug = trim($slug, '-');

        return $slug !== '' ? substr($slug, 0, 100) : 'programa';
    }

    public static function generateUniqueStorageFolder(string $source, ?int $ignoreId = null): string
    {
        $base = static::normalizeStorageFolder($source);
        if ($base === '') {
            $base = 'programa';
        }

        $table = (new static())->getTable();
        if (! Schema::hasTable($table)) {
            return $base;
        }

        $query = static::query();
        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        $columns = ['id', 'nombre', 'program_code'];
        if (Schema::hasColumn($table, 'storage_folder')) {
            $columns[] = 'storage_folder';
        }

        $otherRows = $query->orderBy('id')->get($columns);
        $taken = [];

        foreach ($otherRows as $row) {
            $rowFolder = trim((string) ($row->storage_folder ?? ''));
            if ($rowFolder !== '') {
                $taken[] = static::normalizeStorageFolder($rowFolder);
            } elseif ($ignoreId === null || (int) $row->id < $ignoreId) {
                $fallback = static::normalizeStorageFolder((string) ($row->nombre ?: $row->program_code ?: 'programa-' . $row->id));
                $taken[] = $fallback;
            }
        }

        $taken = array_values(array_unique(array_filter($taken)));

        if (! in_array($base, $taken, true)) {
            return $base;
        }

        for ($i = 2; $i <= 999; $i++) {
            $candidate = substr($base, 0, 95) . '-' . $i;
            if (! in_array($candidate, $taken, true)) {
                return $candidate;
            }
        }

        return substr($base, 0, 88) . '-' . ($ignoreId ?: now()->format('His'));
    }

    public function isAutonomousDeliveryAuthorized(): bool
    {
        return trim((string) ($this->editorial_mode ?? self::EDITORIAL_MODE_MANDATORY)) === self::EDITORIAL_MODE_AUTONOMOUS;
    }

    public function getSubmissionCutoffMinutesAttribute(?int $value): int
    {
        return $value !== null && $value > 0 ? (int) $value : 90;
    }

    /**
     * Devuelve una representación legible del margen de cierre en horas o minutos
     * (ej. «24 horas», «48 horas», «90 minutos»).
     */
    public function formatCutoffHuman(?int $minutes = null): string
    {
        $m = $minutes !== null ? $minutes : $this->submission_cutoff_minutes;
        if ($m <= 0) {
            return 'Sin margen';
        }
        if ($m % 1440 === 0) {
            $days = intdiv($m, 1440);
            return $days === 1 ? '24 horas' : ($days * 24) . ' horas';
        }
        if ($m % 60 === 0) {
            $hours = intdiv($m, 60);
            return $hours === 1 ? '1 hora' : "{$hours} horas";
        }

        return "{$m} minutos";
    }

    /**
     * Calcula el plazo límite de entrega para una emisión específica.
     *
     * @param mixed $emissionDate Fecha de emisión (Y-m-d o objeto fecha)
     * @return array<string, mixed>
     */
    public function calculateEmissionDeadline(mixed $emissionDate): array
    {
        $cutoffMinutes = $this->submission_cutoff_minutes;
        $cutoffHuman = $this->formatCutoffHuman($cutoffMinutes);
        $tz = trim((string) ($this->timezone ?: config('app.timezone', 'Europe/Madrid')));
        if ($tz === '' || ! in_array($tz, timezone_identifiers_list(), true)) {
            $tz = config('app.timezone', 'Europe/Madrid');
        }

        $dateStr = null;
        if ($emissionDate instanceof Carbon || $emissionDate instanceof \DateTimeInterface) {
            $dateStr = $emissionDate->format('Y-m-d');
        } elseif (is_string($emissionDate) && trim($emissionDate) !== '') {
            try {
                $dateStr = Carbon::parse(trim($emissionDate))->format('Y-m-d');
            } catch (\Throwable) {
                $dateStr = null;
            }
        }

        if ($dateStr === null) {
            return [
                'status' => 'undetermined',
                'reason' => 'Fecha de emisión no especificada o inválida.',
                'cutoff_minutes' => $cutoffMinutes,
                'cutoff_human' => $cutoffHuman,
                'timezone' => $tz,
                'is_closed' => false,
                'emission_datetime' => null,
                'emission_formatted' => null,
                'deadline_datetime' => null,
                'deadline_formatted' => null,
                'deadline_human' => 'Fecha no determinada',
                'crosses_midnight' => false,
                'remaining_seconds' => 0,
                'remaining_human' => 'Fecha no determinada',
            ];
        }

        $isoDays = [
            1 => 'LUNES',
            2 => 'MARTES',
            3 => 'MIERCOLES',
            4 => 'JUEVES',
            5 => 'VIERNES',
            6 => 'SABADO',
            7 => 'DOMINGO',
        ];

        try {
            $dayIso = (int) Carbon::parse($dateStr, $tz)->dayOfWeekIso; // 1 = Lunes, ..., 7 = Domingo
            $dayName = $isoDays[$dayIso] ?? null;
        } catch (\Throwable) {
            $dayName = null;
        }

        $emisiones = $this->relationLoaded('emisiones') ? $this->emisiones : $this->emisiones()->get();

        $rawTime = '';
        if ($emisiones->isNotEmpty()) {
            // Regla de paridad con el selector administrativo:
            // Si existen emisiones configuradas en master_program_emisiones, tomamos ÚNICAMENTE las activas del día.
            // Si no hay ninguna emisión activa para esa fecha/día, NO se autoriza la entrega ni se recurre al horario base.
            $activePasses = $emisiones->where('activo', true)
                ->filter(fn ($em) => strtoupper(trim((string) $em->dia_semana)) === $dayName)
                ->sortBy(fn ($em) => (string) ($em->hora_inicio ?? '99:99:99'))
                ->values();

            if ($activePasses->isEmpty()) {
                return [
                    'status' => 'no_active_emission',
                    'reason' => "El programa no tiene emisiones activas programadas para el día {$dayName} ({$dateStr}).",
                    'cutoff_minutes' => $cutoffMinutes,
                    'cutoff_human' => $cutoffHuman,
                    'timezone' => $tz,
                    'is_closed' => true, // Bloquea la entrega explícitamente
                    'emission_datetime' => null,
                    'emission_formatted' => null,
                    'deadline_datetime' => null,
                    'deadline_formatted' => null,
                    'deadline_human' => "Sin emisión activa para {$dayName}",
                    'crosses_midnight' => false,
                    'remaining_seconds' => 0,
                    'remaining_human' => 'No programado',
                ];
            }

            // Si hay múltiples pases activos el mismo día, el pase objetivo se define por el primer pase cronológico del día,
            // garantizando que el plazo de entrega proteja la primera salida al aire de la jornada.
            $targetPass = $activePasses->first();
            $rawTime = trim((string) ($targetPass->hora_inicio ?? ''));
        } else {
            // Retrocompatibilidad: programas históricos sin ninguna emisión configurada en master_program_emisiones
            $baseDay = strtoupper(trim((string) $this->dia_transmision));
            if ($dayName !== null && $baseDay !== '' && $baseDay !== $dayName) {
                return [
                    'status' => 'no_active_emission',
                    'reason' => "El programa histórico está configurado para {$baseDay}, no para {$dayName} ({$dateStr}).",
                    'cutoff_minutes' => $cutoffMinutes,
                    'cutoff_human' => $cutoffHuman,
                    'timezone' => $tz,
                    'is_closed' => true,
                    'emission_datetime' => null,
                    'emission_formatted' => null,
                    'deadline_datetime' => null,
                    'deadline_formatted' => null,
                    'deadline_human' => "Sin emisión activa para {$dayName}",
                    'crosses_midnight' => false,
                    'remaining_seconds' => 0,
                    'remaining_human' => 'No programado',
                ];
            }

            $rawTime = trim((string) ($this->hora_transmision ?? ''));
        }

        if ($rawTime === '') {
            return [
                'status' => 'undetermined',
                'reason' => 'El Programa Maestro no tiene configurada una hora de transmisión fija.',
                'cutoff_minutes' => $cutoffMinutes,
                'cutoff_human' => $cutoffHuman,
                'timezone' => $tz,
                'is_closed' => false,
                'emission_datetime' => null,
                'emission_formatted' => null,
                'deadline_datetime' => null,
                'deadline_formatted' => null,
                'deadline_human' => 'Horario no determinado',
                'crosses_midnight' => false,
                'remaining_seconds' => 0,
                'remaining_human' => 'Horario no determinado',
            ];
        }

        $timeParts = explode(':', $rawTime);
        $hour = str_pad(trim($timeParts[0] ?? '0'), 2, '0', STR_PAD_LEFT);
        $minute = str_pad(trim($timeParts[1] ?? '0'), 2, '0', STR_PAD_LEFT);
        $second = str_pad(trim($timeParts[2] ?? '0'), 2, '0', STR_PAD_LEFT);
        $normalizedTime = "{$hour}:{$minute}:{$second}";

        try {
            $emissionDateTime = Carbon::createFromFormat('Y-m-d H:i:s', "{$dateStr} {$normalizedTime}", $tz);
        } catch (\Throwable $e) {
            return [
                'status' => 'undetermined',
                'reason' => 'Formato de hora de emisión inválido: ' . $e->getMessage(),
                'cutoff_minutes' => $cutoffMinutes,
                'cutoff_human' => $cutoffHuman,
                'timezone' => $tz,
                'is_closed' => false,
                'emission_datetime' => null,
                'emission_formatted' => null,
                'deadline_datetime' => null,
                'deadline_formatted' => null,
                'deadline_human' => 'Hora inválida',
                'crosses_midnight' => false,
                'remaining_seconds' => 0,
                'remaining_human' => 'Hora inválida',
            ];
        }

        $deadlineDateTime = $emissionDateTime->copy()->subMinutes($cutoffMinutes);
        $crossesMidnight = $deadlineDateTime->format('Y-m-d') !== $emissionDateTime->format('Y-m-d');

        $now = Carbon::now($tz);
        $isClosed = $now->greaterThanOrEqualTo($deadlineDateTime);
        $diffSeconds = (int) $now->diffInSeconds($deadlineDateTime, false);

        if ($isClosed) {
            $remainingHuman = 'Plazo cerrado';
        } else {
            $h = intdiv($diffSeconds, 3600);
            $m = intdiv($diffSeconds % 3600, 60);
            if ($h > 0) {
                $remainingHuman = "Quedan {$h}h {$m}m";
            } elseif ($m > 0) {
                $remainingHuman = "Quedan {$m}m";
            } else {
                $remainingHuman = "Menos de 1 minuto restante";
            }
        }

        return [
            'status' => 'ok',
            'reason' => null,
            'cutoff_minutes' => $cutoffMinutes,
            'cutoff_human' => $cutoffHuman,
            'timezone' => $tz,
            'is_closed' => $isClosed,
            'emission_datetime' => $emissionDateTime,
            'emission_formatted' => $emissionDateTime->translatedFormat('d/m/Y H:i') . ' (' . $tz . ')',
            'deadline_datetime' => $deadlineDateTime,
            'deadline_formatted' => $deadlineDateTime->translatedFormat('d/m/Y H:i') . ' (' . $tz . ')',
            'deadline_human' => 'Cierre ' . $cutoffHuman . ' antes de la emisión (' . $deadlineDateTime->translatedFormat('d/m/Y H:i') . ' ' . $tz . ')',
            'crosses_midnight' => $crossesMidnight,
            'remaining_seconds' => $diffSeconds,
            'remaining_human' => $remainingHuman,
        ];
    }

    /**
     * Combina la guía general de la emisora con los ajustes específicos del programa maestro.
     *
     * @return array<string, mixed>
     */
    public function effectiveEditorialGuide(): array
    {
        $general = ThemeSetting::currentPodcastEditorialGuide();
        $programSpecific = is_array($this->editorial_guide) ? $this->editorial_guide : [];

        return [
            'station_guide' => $general,
            'identity_and_audience' => trim((string) ($programSpecific['identity_and_audience'] ?? $this->descripcion ?? $general['identity_and_audience'] ?? '')),
            'tone_and_style' => trim((string) ($programSpecific['tone_and_style'] ?? $general['tone_and_style'] ?? '')),
            'structure_rules' => array_merge(
                is_array($general['structure_rules'] ?? null) ? $general['structure_rules'] : [],
                is_array($programSpecific['structure_rules'] ?? null) ? array_filter($programSpecific['structure_rules'], static fn ($v) => $v !== null && $v !== '') : []
            ),
            'cover_requirements' => array_merge(
                is_array($general['cover_requirements'] ?? null) ? $general['cover_requirements'] : [],
                is_array($programSpecific['cover_requirements'] ?? null) ? array_filter($programSpecific['cover_requirements'], static fn ($v) => $v !== null && $v !== '') : []
            ),
            'particular_rules' => trim((string) ($programSpecific['particular_rules'] ?? '')),
            'optional_examples' => trim((string) ($programSpecific['optional_examples'] ?? '')),
        ];
    }

    /**
     * @return Collection<int, static>
     */
    public static function adminListing(): Collection
    {
        $query = static::query();
        $table = (new static())->getTable();

        if (! Schema::hasTable($table)) {
            return collect();
        }

        $dayColumn = static::firstExistingColumn($table, ['dia_transmision', 'day', 'schedule_day']);
        $timeColumn = static::firstExistingColumn($table, ['hora_transmision', 'time', 'schedule_time']);
        $nameColumn = static::firstExistingColumn($table, ['nombre', 'name', 'title']);

        if ($dayColumn !== null) {
            $query->orderByRaw(sprintf(
                "CASE UPPER(%s)
                    WHEN 'LUNES' THEN 1
                    WHEN 'MARTES' THEN 2
                    WHEN 'MIERCOLES' THEN 3
                    WHEN 'JUEVES' THEN 4
                    WHEN 'VIERNES' THEN 5
                    WHEN 'SABADO' THEN 6
                    WHEN 'DOMINGO' THEN 7
                    ELSE 99
                END",
                self::wrapColumn($dayColumn)
            ));
        }

        if ($timeColumn !== null) {
            $query->orderByRaw(sprintf("COALESCE(%s, '99:99:99')", self::wrapColumn($timeColumn)));
        }

        if ($nameColumn !== null) {
            $query->orderBy($nameColumn);
        } else {
            $query->orderBy($table . '.id');
        }

        return $query->get();
    }

    public function getNameAttribute(): string
    {
        return trim((string) ($this->nombre ?: ''));
    }

    public function getProgramNameAttribute(): string
    {
        return $this->name;
    }

    public function getProgramCodeAttribute(?string $value): string
    {
        return trim((string) $value);
    }

    public function getHostAttribute(): string
    {
        $host = trim((string) $this->conductor);
        if ($host === '') {
            return '';
        }

        $host = preg_replace('/^\s*(conducido\s+por\s*:?\s*|conduce\s*:?\s*|host\s*:?\s*)/iu', '', $host) ?: $host;
        $host = preg_replace('/\s+/', ' ', $host) ?: $host;

        return trim($host);
    }

    public function getDescriptionAttribute(): string
    {
        return trim((string) (
            $this->live_description
            ?: $this->descripcion
            ?: $this->comentario_predeterminado
            ?: ''
        ));
    }

    public function getCoverUrlAttribute(): string
    {
        if ($resolved = PublicMediaUrl::normalizePublicUrl($this->live_image_url ?: $this->caratula_url)) {
            return $resolved;
        }

        $cover = trim((string) ($this->live_image_url ?: $this->caratula_url));

        return $cover !== '' ? asset($cover) : '';
    }

    public function getScheduleAttribute(): string
    {
        $hora = trim((string) $this->hora_transmision);
        $hora = preg_replace('/\b(\d{1,2}:\d{2}):\d{2}\b/', '$1', $hora);

        $parts = array_filter([
            trim((string) $this->dia_transmision),
            $hora,
        ]);

        return $parts !== [] ? implode(' · ', $parts) : 'Programación continua';
    }

    public function publicSlug(): string
    {
        return Str::slug($this->name ?: 'programa');
    }

    public function programCodeLabel(): string
    {
        return $this->program_code !== '' ? $this->program_code : 'SIN CODIGO';
    }

    public static function normalizeProgramCode(string $value): string
    {
        $value = Str::of($value)
            ->ascii()
            ->upper()
            ->replaceMatches('/[^A-Z0-9]+/', '')
            ->trim()
            ->toString();

        return substr($value, 0, 12);
    }

    public static function generateUniqueProgramCode(string $source, ?int $ignoreId = null): string
    {
        $base = static::normalizeProgramCode($source);
        if ($base === '') {
            $base = 'PROGRAMA';
        }

        $base = substr($base, 0, 12);
        $query = static::query();
        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        $existing = $query->pluck('program_code')->filter()->map(static fn ($code) => strtoupper(trim((string) $code)))->all();
        if (! in_array($base, $existing, true)) {
            return $base;
        }

        for ($i = 1; $i <= 99; $i++) {
            $suffix = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $prefixLength = max(0, 12 - strlen($suffix));
            $candidate = substr($base, 0, $prefixLength) . $suffix;

            if (! in_array($candidate, $existing, true)) {
                return $candidate;
            }
        }

        return substr($base, 0, 10) . now()->format('s');
    }

    /**
     * @param  array<int, string>  $columns
     */
    private static function firstExistingColumn(string $table, array $columns): ?string
    {
        foreach ($columns as $column) {
            if (Schema::hasColumn($table, $column)) {
                return $column;
            }
        }

        return null;
    }

    private static function wrapColumn(string $column): string
    {
        return '`' . str_replace('`', '``', $column) . '`';
    }
}
