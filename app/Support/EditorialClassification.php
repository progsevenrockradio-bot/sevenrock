<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Post;
use Illuminate\Database\Eloquent\Builder;

class EditorialClassification
{
    public const TYPE_NOTICIA = 'noticia';
    public const TYPE_EFEMERIDE = 'efemeride';
    public const TYPE_UNCLASSIFIED = 'unclassified';

    public const CATEGORY_NOTICIA = 'Noticias Rock';
    public const CATEGORY_EFEMERIDE = 'Hoy en el Rock';

    public const SLUG_NOTICIA = 'noticias-rock';
    public const SLUG_EFEMERIDE = 'hoy-en-el-rock';

    /**
     * Resuelve el tipo editorial exclusivamente a partir de las categorías y taxonomías
     * almacenadas del artículo. Nunca infiere ni adivina por palabras del título.
     *
     * @return array{
     *   type: string,
     *   label: string,
     *   destination: string,
     *   has_conflict: bool,
     *   conflict_reason: ?string,
     *   historical_year: ?int,
     *   badge_classes: string,
     *   icon: string
     * }
     */
    public static function resolve(Post $post): array
    {
        $names = self::extractStoredCategoryNames($post);

        $hasNoticia = false;
        $hasEfemeride = false;

        foreach ($names as $name) {
            $norm = self::normalize($name);
            if ($norm === 'noticias rock' || $norm === self::SLUG_NOTICIA) {
                $hasNoticia = true;
            }
            if ($norm === 'hoy en el rock' || $norm === self::SLUG_EFEMERIDE) {
                $hasEfemeride = true;
            }
        }

        if ($hasNoticia && $hasEfemeride) {
            return [
                'type'            => self::TYPE_UNCLASSIFIED,
                'label'           => 'Sin clasificar',
                'destination'     => 'Conflicto: Noticias Rock / Hoy en el Rock',
                'has_conflict'    => true,
                'conflict_reason' => 'Conflicto de clasificación: el registro contiene simultáneamente «Noticias Rock» y «Hoy en el Rock». Requiere revisión manual.',
                'historical_year' => null,
                'badge_classes'   => 'border-rose-500/40 bg-rose-500/15 text-rose-200',
                'icon'            => '⚠️',
            ];
        }

        if ($hasEfemeride) {
            return [
                'type'            => self::TYPE_EFEMERIDE,
                'label'           => 'Efeméride',
                'destination'     => self::CATEGORY_EFEMERIDE,
                'has_conflict'    => false,
                'conflict_reason' => null,
                'historical_year' => self::extractVerifiedHistoricalYear($post),
                'badge_classes'   => 'border-amber-500/40 bg-amber-500/15 text-amber-200',
                'icon'            => '📅',
            ];
        }

        if ($hasNoticia) {
            return [
                'type'            => self::TYPE_NOTICIA,
                'label'           => 'Noticia',
                'destination'     => self::CATEGORY_NOTICIA,
                'has_conflict'    => false,
                'conflict_reason' => null,
                'historical_year' => null,
                'badge_classes'   => 'border-sky-500/40 bg-sky-500/15 text-sky-200',
                'icon'            => '📰',
            ];
        }

        return [
            'type'            => self::TYPE_UNCLASSIFIED,
            'label'           => 'Sin clasificar',
            'destination'     => 'Sin sección asignada',
            'has_conflict'    => false,
            'conflict_reason' => null,
            'historical_year' => null,
            'badge_classes'   => 'border-zinc-500/40 bg-zinc-500/15 text-zinc-300',
            'icon'            => '❓',
        ];
    }

    /**
     * Aplica el filtro de tipo editorial (noticia / efemeride / unclassified) a una consulta Eloquent
     * respetando exactamente la misma semántica que resolve():
     * - noticia: tiene Noticias Rock y NO tiene Hoy en el Rock
     * - efemeride: tiene Hoy en el Rock y NO tiene Noticias Rock
     * - unclassified: no tiene ninguna de las dos, o tiene ambas (conflicto)
     */
    public static function applyTypeFilter(Builder $query, string $typeFilter): Builder
    {
        return match ($typeFilter) {
            self::TYPE_NOTICIA => $query
                ->where(fn (Builder $q) => self::whereHasSection($q, self::CATEGORY_NOTICIA, self::SLUG_NOTICIA))
                ->whereNot(fn (Builder $q) => self::whereHasSection($q, self::CATEGORY_EFEMERIDE, self::SLUG_EFEMERIDE)),

            self::TYPE_EFEMERIDE => $query
                ->where(fn (Builder $q) => self::whereHasSection($q, self::CATEGORY_EFEMERIDE, self::SLUG_EFEMERIDE))
                ->whereNot(fn (Builder $q) => self::whereHasSection($q, self::CATEGORY_NOTICIA, self::SLUG_NOTICIA)),

            self::TYPE_UNCLASSIFIED => $query->where(function (Builder $q) {
                $q->where(function (Builder $neither) {
                    $neither->whereNot(fn (Builder $s) => self::whereHasSection($s, self::CATEGORY_NOTICIA, self::SLUG_NOTICIA))
                            ->whereNot(fn (Builder $s) => self::whereHasSection($s, self::CATEGORY_EFEMERIDE, self::SLUG_EFEMERIDE));
                })->orWhere(function (Builder $both) {
                    $both->where(fn (Builder $s) => self::whereHasSection($s, self::CATEGORY_NOTICIA, self::SLUG_NOTICIA))
                         ->where(fn (Builder $s) => self::whereHasSection($s, self::CATEGORY_EFEMERIDE, self::SLUG_EFEMERIDE));
                });
            }),

            default => $query,
        };
    }

    /**
     * Aplica un cambio explícito de tipo editorial sobre una lista de categorías,
     * sustituyendo únicamente la categoría de sección principal y conservando
     * todas las demás categorías compatibles.
     *
     * @param  array<int, string>  $categories
     * @return array<int, string>
     */
    public static function applyExplicitTypeToCategories(array $categories, string $targetType): array
    {
        $filtered = [];
        foreach ($categories as $cat) {
            $clean = trim((string) $cat);
            if ($clean === '') {
                continue;
            }
            $norm = self::normalize($clean);
            if (in_array($norm, ['noticias rock', self::SLUG_NOTICIA, 'hoy en el rock', self::SLUG_EFEMERIDE], true)) {
                continue;
            }
            $filtered[] = $clean;
        }

        if ($targetType === self::TYPE_NOTICIA) {
            array_unshift($filtered, self::CATEGORY_NOTICIA);
        } elseif ($targetType === self::TYPE_EFEMERIDE) {
            array_unshift($filtered, self::CATEGORY_EFEMERIDE);
        }

        return array_values(array_unique($filtered));
    }

    /**
     * Extrae el año histórico verificado de una efeméride desde sanitizedMeta.event_date.
     * Nunca usa published_at ni adivina por el titular.
     */
    public static function extractVerifiedHistoricalYear(Post $post): ?int
    {
        $meta = $post->relationLoaded('sanitizedMeta')
            ? $post->sanitizedMeta
            : $post->sanitizedMeta()->first();

        $rawDate = trim((string) ($meta?->event_date ?? ''));
        if ($rawDate === '') {
            return null;
        }

        if (preg_match('/^(\d{4})(?:-\d{2}-\d{2})?$/', $rawDate, $matches)) {
            $year = (int) $matches[1];
            if ($year >= 1900 && $year <= (int) date('Y')) {
                return $year;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    public static function extractStoredCategoryNames(Post $post): array
    {
        $names = [];

        if ($post->relationLoaded('taxonomies')) {
            foreach ($post->taxonomies as $taxonomy) {
                if (($taxonomy->type ?? null) === 'category') {
                    if (! empty($taxonomy->name)) {
                        $names[] = (string) $taxonomy->name;
                    }
                    if (! empty($taxonomy->slug)) {
                        $names[] = (string) $taxonomy->slug;
                    }
                }
            }
        } elseif ($post->exists) {
            try {
                foreach ($post->taxonomies()->where('type', 'category')->get(['name', 'slug']) as $taxonomy) {
                    if (! empty($taxonomy->name)) {
                        $names[] = (string) $taxonomy->name;
                    }
                    if (! empty($taxonomy->slug)) {
                        $names[] = (string) $taxonomy->slug;
                    }
                }
            } catch (\Throwable) {
            }
        }

        $rawCategories = $post->categories;
        if (is_array($rawCategories)) {
            foreach ($rawCategories as $cat) {
                $catStr = trim((string) $cat);
                if ($catStr !== '') {
                    $names[] = $catStr;
                }
            }
        }

        return array_values(array_unique($names));
    }

    protected static function whereHasSection(Builder $query, string $categoryName, string $slug): void
    {
        $query->where(function (Builder $sub) use ($categoryName, $slug) {
            $sub->whereHas('taxonomies', function (Builder $t) use ($categoryName, $slug) {
                $t->where('type', 'category')
                  ->where(function (Builder $tn) use ($categoryName, $slug) {
                      $tn->where('name', $categoryName)
                         ->orWhere('slug', $slug);
                  });
            })->orWhereJsonContains('posts.categories', $categoryName);
        });
    }

    protected static function normalize(string $value): string
    {
        return mb_strtolower(trim($value), 'UTF-8');
    }
}

