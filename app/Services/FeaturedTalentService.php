<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Talent;
use Illuminate\Support\Collection;

/**
 * Selecciona los talentos que deben aparecer como "destacados" en el sitio.
 * El criterio principal es el campo `interacts` (numero de interacciones recientes),
 * filtrado a suscripciones activas. Se usa en el comando artisan `talents:refresh-featured`.
 */
final class FeaturedTalentService
{
    /**
     * Devuelve los $limit talentos que deben figurar como destacados,
     * ordenados por `interacts` descendente.
     *
     * @return Collection<int, Talent>
     */
    public function getFeatured(int $limit = 6): Collection
    {
        $limit = max(1, $limit);

        return Talent::query()
            ->where('subscription_status', 'active')
            ->orderByDesc('interacts')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }
}
