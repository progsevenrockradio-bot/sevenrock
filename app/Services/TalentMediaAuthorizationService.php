<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Talent;
use App\Models\TalentMedia;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TalentMediaAuthorizationService
{
    /**
     * Determina si el usuario/talento actual tiene permiso para acceder (ver, reproducir o descargar) al archivo.
     */
    public function canAccess(TalentMedia $media, ?Authenticatable $user = null, ?Talent $talent = null): bool
    {
        $isAdmin = ($user instanceof User) && $user->isAdmin();
        if ($isAdmin) {
            return true;
        }

        $isOwner = $talent && ((int) $talent->id === (int) $media->talent_id);
        if ($isOwner) {
            return true;
        }

        // Para cualquier visitante o usuario ajeno al propietario:
        // 1. El archivo DEBE estar aprobado por moderación
        if ($media->status !== 'approved') {
            return false;
        }

        // 2. La banda propietaria DEBE ser públicamente visible (activa y no oculta)
        $band = $media->relationLoaded('talent') ? $media->talent : $media->talent()->first();
        if (! $band || ! $band->isPubliclyVisible()) {
            return false;
        }

        // 3. Privacidad documental estricta: los documentos son exclusivos del propietario y administración
        // Ningún usuario web ajeno (aunque esté autenticado o el documento esté marcado con is_exclusive) puede acceder
        if ($media->type === 'document') {
            return false;
        }

        // 4. Si el contenido multimedia es exclusivo para afiliados/usuarios registrados
        if ($media->is_exclusive) {
            return $user !== null;
        }

        // 5. Archivo público, aprobado y de banda visible
        return true;
    }

    /**
     * Valida la autorización de acceso y aborta con 403 si no se cumplen las condiciones.
     *
     * @throws HttpException
     */
    public function authorize(TalentMedia $media, ?Authenticatable $user = null, ?Talent $talent = null): void
    {
        $isAdmin = ($user instanceof User) && $user->isAdmin();
        if ($isAdmin) {
            return;
        }

        $isOwner = $talent && ((int) $talent->id === (int) $media->talent_id);
        if ($isOwner) {
            return;
        }

        // 1. Archivo en moderación o rechazado
        if ($media->status !== 'approved') {
            abort(403, 'Este archivo no está disponible o se encuentra pendiente de revisión.');
        }

        // 2. Banda no visible públicamente
        $band = $media->relationLoaded('talent') ? $media->talent : $media->talent()->first();
        if (! $band || ! $band->isPubliclyVisible()) {
            abort(403, 'El material de este artista no está disponible públicamente.');
        }

        // 3. Documento privado (privacidad documental sobre is_exclusive)
        if ($media->type === 'document') {
            abort(403, 'Los documentos son estrictamente privados y solo accesibles por el artista propietario y la administración.');
        }

        // 4. Exclusivo para usuarios autenticados
        if ($media->is_exclusive && ! $user) {
            abort(403, 'Contenido exclusivo para usuarios registrados o afiliados.');
        }
    }
}

