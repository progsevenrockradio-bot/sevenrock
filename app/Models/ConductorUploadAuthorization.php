<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ConductorUploadAuthorization extends Model
{
    public const STATUS_ISSUED = 'issued';
    public const STATUS_CONSUMED = 'consumed';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_REVOKED = 'revoked';

    protected $table = 'conductor_upload_authorizations';

    protected $fillable = [
        'user_id',
        'master_program_id',
        'radio_program_id',
        'upload_token',
        'r2_key',
        'expected_mime',
        'max_size_bytes',
        'status',
        'expires_at',
        'consumed_at',
        'consumed_by_radio_program_id',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'master_program_id' => 'integer',
            'radio_program_id' => 'integer',
            'max_size_bytes' => 'integer',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'consumed_by_radio_program_id' => 'integer',
        ];
    }

    public static function issueForConductor(
        User $conductor,
        MasterProgram $masterProgram,
        string $originalFilename = 'episode.mp3',
        string $expectedMime = 'audio/mpeg',
        ?int $radioProgramId = null,
        ?CarbonInterface $expiresAt = null,
        int $maxSizeBytes = 524288000,
    ): self {
        if (! $conductor->isAuthorizedForMasterProgram($masterProgram)) {
            throw new \RuntimeException('El conductor no está autorizado para este programa maestro.');
        }

        $folder = $masterProgram->resolveStorageFolder();
        $baseName = Str::slug(pathinfo($originalFilename, PATHINFO_FILENAME), '-') ?: 'episode';
        $uuid = Str::lower(Str::uuid()->toString());
        $r2Key = sprintf('podcast-inbox/%s/%s-%s.mp3', $folder, $uuid, substr($baseName, 0, 80));
        $token = hash_hmac(
            'sha256',
            sprintf('upload-auth:%d:%d:%s:%s', (int) $conductor->id, (int) $masterProgram->id, $r2Key, Str::uuid()->toString()),
            (string) (config('app.key') ?: 'sevenrockradio-upload-secret')
        );

        return self::query()->create([
            'user_id' => (int) $conductor->id,
            'master_program_id' => (int) $masterProgram->id,
            'radio_program_id' => $radioProgramId,
            'upload_token' => $token,
            'r2_key' => $r2Key,
            'expected_mime' => $expectedMime,
            'max_size_bytes' => $maxSizeBytes,
            'status' => self::STATUS_ISSUED,
            'expires_at' => $expiresAt ?? now()->addMinutes(60),
        ]);
    }

    /**
     * Comprobación preliminar (sin consumir) antes de inspeccionar el objeto en R2.
     */
    public static function validatePreClaim(
        string $r2Key,
        User $conductor,
        MasterProgram $masterProgram,
        ?RadioProgram $existingEpisode = null,
        string $uploadToken = '',
    ): self {
        $r2Key = ltrim(str_replace('\\', '/', trim($r2Key)), '/');
        $uploadToken = trim($uploadToken);

        /** @var self|null $authRecord */
        $authRecord = self::query()->where('r2_key', $r2Key)->first();

        self::assertAuthorizationEligible($authRecord, $conductor, $masterProgram, $existingEpisode, $uploadToken, true);

        return $authRecord;
    }

    /**
     * Reclama atómicamente una autorización de subida dentro de la transacción de base de datos.
     * Comprueba pertenencia (conductor, programa, episodio), token, vigencia y estado mediante
     * actualización condicional atómica (status = issued AND consumed_at IS NULL) que garantiza
     * que dos peticiones competidoras nunca puedan consumir la misma autorización dos veces.
     */
    public static function claimAtomically(
        string $r2Key,
        User $conductor,
        MasterProgram $masterProgram,
        ?RadioProgram $existingEpisode = null,
        string $uploadToken = '',
    ): self {
        $r2Key = ltrim(str_replace('\\', '/', trim($r2Key)), '/');
        $uploadToken = trim($uploadToken);
        $now = now();

        $query = self::query()
            ->where('r2_key', $r2Key)
            ->where('user_id', (int) $conductor->id)
            ->where('master_program_id', (int) $masterProgram->id)
            ->where('status', self::STATUS_ISSUED)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', $now);

        if ($uploadToken !== '') {
            $query->where('upload_token', $uploadToken);
        }

        if ($existingEpisode !== null) {
            $query->where(function ($q) use ($existingEpisode): void {
                $q->whereNull('radio_program_id')
                    ->orWhere('radio_program_id', (int) $existingEpisode->id);
            });
        } else {
            $query->whereNull('radio_program_id');
        }

        $affected = $query->update([
            'status' => self::STATUS_CONSUMED,
            'consumed_at' => $now,
            'updated_at' => $now,
        ]);

        if ($affected !== 1) {
            /** @var self|null $currentRecord */
            $currentRecord = self::query()->where('r2_key', $r2Key)->lockForUpdate()->first();
            self::assertAuthorizationEligible($currentRecord, $conductor, $masterProgram, $existingEpisode, $uploadToken, false);

            throw ValidationException::withMessages([
                'r2_key' => 'Esta subida directa ya fue utilizada en otra entrega y no puede reutilizarse.',
            ]);
        }

        /** @var self $claimed */
        $claimed = self::query()->where('r2_key', $r2Key)->lockForUpdate()->firstOrFail();

        return $claimed;
    }

    /**
     * @phpstan-assert self $authRecord
     */
    private static function assertAuthorizationEligible(
        ?self $authRecord,
        User $conductor,
        MasterProgram $masterProgram,
        ?RadioProgram $existingEpisode,
        string $uploadToken,
        bool $persistExpiredState,
    ): void {
        if (! $authRecord instanceof self) {
            throw ValidationException::withMessages([
                'r2_key' => 'La clave de audio indicada no corresponde a ninguna autorización de subida emitida por el servidor.',
            ]);
        }

        if ($uploadToken !== '' && ! hash_equals((string) $authRecord->upload_token, $uploadToken)) {
            throw ValidationException::withMessages([
                'r2_key' => 'El token de autorización de subida no coincide con la clave emitida.',
            ]);
        }

        if ((int) $authRecord->user_id !== (int) $conductor->id) {
            throw ValidationException::withMessages([
                'r2_key' => 'No tienes permiso para utilizar un objeto de audio subido por otro conductor.',
            ]);
        }

        if ((int) $authRecord->master_program_id !== (int) $masterProgram->id) {
            throw ValidationException::withMessages([
                'r2_key' => 'El objeto de audio fue autorizado para otro programa maestro y no puede asociarse a este.',
            ]);
        }

        if ($authRecord->radio_program_id !== null && ($existingEpisode === null || (int) $authRecord->radio_program_id !== (int) $existingEpisode->id)) {
            throw ValidationException::withMessages([
                'r2_key' => 'La autorización de subida está vinculada a otro episodio distinto.',
            ]);
        }

        if ($authRecord->isConsumed()) {
            throw ValidationException::withMessages([
                'r2_key' => 'Esta subida directa ya fue utilizada en otra entrega y no puede reutilizarse.',
            ]);
        }

        if ($authRecord->isExpired()) {
            if ($persistExpiredState) {
                $authRecord->forceFill(['status' => self::STATUS_EXPIRED])->save();
            }

            throw ValidationException::withMessages([
                'r2_key' => 'La autorización de subida directa ha caducado. Vuelve a subir el archivo MP3.',
            ]);
        }
    }

    public function isExpired(): bool
    {
        return $this->expires_at === null || $this->expires_at->isPast();
    }

    public function isConsumed(): bool
    {
        return $this->status !== self::STATUS_ISSUED || $this->consumed_at !== null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function masterProgram(): BelongsTo
    {
        return $this->belongsTo(MasterProgram::class);
    }
}
