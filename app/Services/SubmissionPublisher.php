<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\ExtractReleaseCoverJob;
use App\Mail\SubmissionPublishedToHub;
use App\Models\EmailLog;
use App\Models\NewRelease;
use App\Models\TrackSubmission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Circuito único de publicación de maquetas aprobadas y de su aviso por correo.
 *
 * - La publicación (lanzamiento + vínculo + fecha) se resuelve dentro de una transacción
 *   con la maqueta bloqueada y revalidada.
 * - El correo se envía siempre DESPUÉS de confirmar la transacción.
 * - El estado del aviso queda en la propia maqueta; solo un fallo de envío confirmado
 *   ('failed') puede reintentarse, y el reintento nunca republica.
 *
 * Limitación: no se puede demostrar entrega única absoluta; si el envío pudo haberse
 * entregado pero no se pudo registrar, el estado pasa a 'unknown' y no se reenvía solo.
 */
final class SubmissionPublisher
{
    public const PUBLISHED = 'published';
    public const ALREADY_PUBLISHED = 'already_published';
    public const NOT_APPROVED = 'not_approved';
    public const NEEDS_REVIEW = 'needs_review';
    public const ERROR = 'error';

    /**
     * @return array{result: string, release: ?NewRelease, notice: ?string, message: string}
     */
    public function publish(TrackSubmission $submission, bool $showInFeed): array
    {
        $created = false;
        $claimedInTx = false;

        try {
            $outcome = DB::transaction(function () use ($submission, $showInFeed, &$created, &$claimedInTx): array {
                /** @var TrackSubmission|null $locked */
                $locked = TrackSubmission::query()->lockForUpdate()->find($submission->id);

                if (! $locked) {
                    return ['result' => self::ERROR, 'release' => null, 'message' => 'La maqueta ya no existe.'];
                }

                // Recuperar el lanzamiento ya vinculado: nunca se crea otro.
                if ($locked->new_release_id && ($linked = NewRelease::query()->find($locked->new_release_id))) {
                    $claimedInTx = $this->markPublishedIfAccessible($locked, $linked);

                    return ['result' => self::ALREADY_PUBLISHED, 'release' => $linked, 'message' => 'Esta maqueta ya estaba publicada en el Hub.'];
                }

                if ($locked->status !== 'approved') {
                    return ['result' => self::NOT_APPROVED, 'release' => null, 'message' => 'Solo las maquetas aprobadas pueden publicarse en el Hub.'];
                }

                // Registros históricos sin vínculo: no se asocian por coincidencia, quedan para revisión.
                if ($locked->published_to_hub) {
                    return ['result' => self::NEEDS_REVIEW, 'release' => null, 'message' => 'Maqueta marcada como publicada antes de existir el vínculo; requiere revisión manual (no se vincula por título/artista).'];
                }

                $ambiguous = NewRelease::query()
                    ->where('title', $locked->song_title)
                    ->where('artist_name', $locked->band_name)
                    ->exists();

                if ($ambiguous) {
                    return ['result' => self::NEEDS_REVIEW, 'release' => null, 'message' => 'Ya existe un lanzamiento con el mismo título y artista; requiere revisión manual (no se vincula automáticamente).'];
                }

                $release = NewRelease::query()->create($this->releaseAttributes($locked, $showInFeed));
                $created = true;

                $locked->forceFill(['new_release_id' => $release->id])->save();
                $claimedInTx = $this->markPublishedIfAccessible($locked, $release);

                return ['result' => self::PUBLISHED, 'release' => $release, 'message' => 'Maqueta publicada en el Catálogo Musical (Hub).'];
            });
        } catch (Throwable $e) {
            Log::error('Error al publicar maqueta en el Hub', ['submission_id' => $submission->id, 'error' => $e->getMessage()]);

            return ['result' => self::ERROR, 'release' => null, 'notice' => null, 'message' => 'Ocurrió un error al publicar la maqueta.'];
        }

        $release = $outcome['release'];
        $notice = null;

        if ($created && $release) {
            try {
                ExtractReleaseCoverJob::dispatch($release, (string) $submission->file_path);
            } catch (Throwable $e) {
                Log::error('No se pudo encolar la extracción de portada', ['release_id' => $release->id, 'error' => $e->getMessage()]);
            }
        }

        if ($release && in_array($outcome['result'], [self::PUBLISHED, self::ALREADY_PUBLISHED], true)) {
            $fresh = TrackSubmission::query()->find($submission->id);

            // Solo el proceso que reservó el aviso dentro del bloqueo transaccional (o cuando nunca hubo intento) lo envía tras el commit.
            if ($fresh && ($claimedInTx || $fresh->publication_notice_status === null)) {
                $notice = $this->sendNotice($fresh, $claimedInTx);
            } else {
                $notice = $fresh?->publication_notice_status;
            }

            if ($fresh && $fresh->published_at !== null) {
                app(SubmissionFollowupService::class)->syncAfterPublication($fresh);
            }
        }

        return $outcome + ['notice' => $notice];
    }

    /**
     * Reintenta únicamente el aviso tras un fallo de envío confirmado. No toca lanzamiento ni fecha.
     */
    public function retryNotice(TrackSubmission $submission): string
    {
        $fresh = TrackSubmission::query()->find($submission->id);

        if (! $fresh || ! $fresh->new_release_id || $fresh->publication_notice_status !== TrackSubmission::NOTICE_FAILED) {
            return 'not_retriable';
        }

        return $this->sendNotice($fresh);
    }

    /**
     * Envía el aviso (fuera de cualquier transacción). Devuelve el estado resultante.
     */
    public function sendNotice(TrackSubmission $submission, bool $alreadyClaimed = false): string
    {
        $release = $submission->new_release_id ? NewRelease::query()->find($submission->new_release_id) : null;

        // Se vuelve a comprobar la visibilidad real justo antes de notificar.
        if (! $release || ! $release->isPubliclyAccessible()) {
            if ($alreadyClaimed) {
                TrackSubmission::query()->whereKey($submission->id)
                    ->where('publication_notice_status', TrackSubmission::NOTICE_PENDING)
                    ->update([
                        'publication_notice_status' => null,
                        'publication_notice_attempts' => DB::raw('CASE WHEN publication_notice_attempts > 0 THEN publication_notice_attempts - 1 ELSE 0 END'),
                    ]);
            }

            return 'not_public';
        }

        $email = trim((string) $submission->contact_email);

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            TrackSubmission::query()->whereKey($submission->id)->update([
                'publication_notice_status' => TrackSubmission::NOTICE_SKIPPED,
                'publication_notice_error' => 'Correo de contacto no válido.',
            ]);

            return TrackSubmission::NOTICE_SKIPPED;
        }

        if (! $alreadyClaimed) {
            // Reserva atómica: solo un proceso pasa de null/failed a pending.
            $claimed = TrackSubmission::query()
                ->whereKey($submission->id)
                ->where(fn ($q) => $q->whereNull('publication_notice_status')
                    ->orWhere('publication_notice_status', TrackSubmission::NOTICE_FAILED))
                ->update([
                    'publication_notice_status' => TrackSubmission::NOTICE_PENDING,
                    'publication_notice_error' => null,
                    'publication_notice_attempts' => DB::raw('publication_notice_attempts + 1'),
                ]);

            if ($claimed === 0) {
                return (string) TrackSubmission::query()->whereKey($submission->id)->value('publication_notice_status');
            }
        }

        $mail = new SubmissionPublishedToHub($submission, $release);

        try {
            $body = $mail->render();
            Mail::to($email)->send($mail);
        } catch (Throwable $e) {
            // Fallo de envío confirmado: nada salió, se puede reintentar.
            Log::error('Fallo al enviar el aviso de publicación', ['submission_id' => $submission->id, 'error' => $e->getMessage()]);

            try {
                TrackSubmission::query()->whereKey($submission->id)->update([
                    'publication_notice_status' => TrackSubmission::NOTICE_FAILED,
                    'publication_notice_error' => Str::limit($e->getMessage(), 500),
                ]);
                EmailLog::query()->create([
                    'track_submission_id' => $submission->id,
                    'to_email' => $email,
                    'subject' => $mail->envelope()->subject,
                    'body' => 'Fallo de envío: ' . Str::limit($e->getMessage(), 500),
                    'status' => 'failed',
                ]);
            } catch (Throwable $inner) {
                Log::critical('No se pudo registrar el fallo del aviso', ['submission_id' => $submission->id, 'error' => $inner->getMessage()]);
            }

            return TrackSubmission::NOTICE_FAILED;
        }

        // El correo salió: a partir de aquí un error de registro NO autoriza reenviar.
        try {
            EmailLog::query()->create([
                'track_submission_id' => $submission->id,
                'to_email' => $email,
                'subject' => $mail->envelope()->subject,
                'body' => $body,
                'status' => 'sent',
            ]);
            TrackSubmission::query()->whereKey($submission->id)->update([
                'publication_notice_status' => TrackSubmission::NOTICE_SENT,
                'publication_notice_sent_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::critical('Aviso enviado pero no se pudo registrar; no se reenviará automáticamente', ['submission_id' => $submission->id, 'error' => $e->getMessage()]);

            try {
                TrackSubmission::query()->whereKey($submission->id)->update([
                    'publication_notice_status' => TrackSubmission::NOTICE_UNKNOWN,
                    'publication_notice_error' => 'Enviado, pero falló el registro: ' . Str::limit($e->getMessage(), 400),
                ]);
            } catch (Throwable) {
                // Queda en 'pending': tampoco es reintentable.
            }

            return TrackSubmission::NOTICE_UNKNOWN;
        }

        return TrackSubmission::NOTICE_SENT;
    }

    /**
     * Registra la primera publicación efectiva (nunca se sobrescribe) solo si la ficha es pública
     * y reserva atómicamente el envío del aviso dentro del bloqueo transaccional.
     */
    private function markPublishedIfAccessible(TrackSubmission $submission, NewRelease $release): bool
    {
        if (! $release->isPubliclyAccessible()) {
            return false;
        }

        $claimed = false;
        $attributes = [];

        if ($submission->published_at === null) {
            $attributes['published_at'] = now();
            $attributes['published_to_hub'] = true;
        }

        if ($submission->publication_notice_status === null) {
            $attributes['publication_notice_status'] = TrackSubmission::NOTICE_PENDING;
            $attributes['publication_notice_error'] = null;
            $attributes['publication_notice_attempts'] = ((int) $submission->publication_notice_attempts) + 1;
            $claimed = true;
        }

        if ($attributes !== []) {
            $submission->forceFill($attributes)->save();
        }

        return $claimed;
    }

    /**
     * @return array<string, mixed>
     */
    private function releaseAttributes(TrackSubmission $submission, bool $showInFeed): array
    {
        $slugBase = Str::slug($submission->song_title . '-' . $submission->band_name);
        $slug = $slugBase;
        $count = 1;
        while (NewRelease::query()->where('slug', $slug)->exists()) {
            $slug = $slugBase . '-' . $count;
            $count++;
        }

        $link = strtolower((string) $submission->social_link);
        $youtubeUrl = null;
        $spotifyUrl = null;
        if ($submission->social_link) {
            if (str_contains($link, 'youtube.com') || str_contains($link, 'youtu.be')) {
                $youtubeUrl = $submission->social_link;
            } elseif (str_contains($link, 'spotify.com')) {
                $spotifyUrl = $submission->social_link;
            }
        }

        return [
            'title' => $submission->song_title,
            'slug' => $slug,
            'artist_name' => $submission->band_name,
            'released_at' => now(),
            'audio_path' => $submission->file_path,
            'youtube_url' => $youtubeUrl,
            'spotify_url' => $spotifyUrl,
            'description' => 'Maqueta descubierta y promocionada por el A&R de Seven Rock Radio.',
            'is_active' => true,
            'show_in_feed' => $showInFeed,
            'author_email' => $submission->contact_email,
            'origin' => 'submission',
        ];
    }
}

