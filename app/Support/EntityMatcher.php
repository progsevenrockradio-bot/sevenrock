<?php

namespace App\Support;

class EntityMatcher
{
    /**
     * Devuelve las entidades del titular.
     *  - frases de 2+ palabras que empiezan en mayúscula
     *  - palabras sueltas en mayúscula o nombres propios
     *  - frases entre comillas
     */
    public static function entities(string $title): array
    {
        $entities = [];

        // 1. Frases entre comillas ("texto", 'texto', «texto», “texto”)
        if (preg_match_all('/["\'«“]([^"\'»”]+)["\'»”]/u', $title, $matches)) {
            foreach ($matches[1] as $match) {
                $norm = self::normalizeEntity($match);
                if (!empty($norm)) {
                    $entities[] = $norm;
                }
            }
            // Borramos para no detectarlo como mayúsculas luego
            $title = preg_replace('/["\'«“]([^"\'»”]+)["\'»”]/u', ' ', $title);
        }

        // 2. Palabras y secuencias de palabras en mayúscula
        // Primero separamos por puntuación de cláusula para no fusionar nombres
        $chunks = preg_split('/[,.;:()\[\]!?—|]/u', $title);
        
        foreach ($chunks as $chunk) {
            // Hacemos split manteniendo letras, números y caracteres que forman nombres reales (', -, &, /)
            $words = preg_split('/[^\p{L}\p{N}\'&\/-]+/u', $chunk);
            $currentEntity = [];

            foreach ($words as $word) {
                if (empty($word)) continue;
                
                // Limpiamos la palabra de posibles guiones o comillas sueltas en los extremos
                $word = trim($word, "'&-/ \t\n\r\0\x0B");
                if (empty($word)) continue;

                // Si la palabra empieza por mayúscula
                if (preg_match('/^\p{Lu}/u', $word)) {
                    $normWord = self::normalizeEntity($word);
                    // Si es una stopword (ej. Muere, El, La) no forma parte de la entidad
                    if (!self::isStopWord($normWord) && !empty($normWord)) {
                        $currentEntity[] = $normWord;
                    } else {
                        // Si era stopword, corta la secuencia actual
                        if (count($currentEntity) > 0) {
                            $entities[] = implode(' ', $currentEntity);
                            $currentEntity = [];
                        }
                    }
                } else {
                    // Palabra en minúscula o número, rompe la secuencia
                    if (count($currentEntity) > 0) {
                        $entities[] = implode(' ', $currentEntity);
                        $currentEntity = [];
                    }
                }
            }
            
            if (count($currentEntity) > 0) {
                $entities[] = implode(' ', $currentEntity);
            }
        }

        // Filtrar vacíos y duplicados
        $entities = array_filter(array_unique($entities));
        return array_values($entities);
    }

    /**
     * Devuelve la intersección de entidades compartidas
     */
    public static function sharedEntities(string $a, string $b): array
    {
        $entitiesA = self::entities($a);
        $entitiesB = self::entities($b);
        return array_values(array_intersect($entitiesA, $entitiesB));
    }

    /**
     * Normaliza a minúsculas, sin acentos ni signos.
     */
    private static function normalizeEntity(string $text): string
    {
        $text = mb_strtolower($text);
        
        $text = preg_replace('/[áàäâã]/u', 'a', $text);
        $text = preg_replace('/[éèëê]/u', 'e', $text);
        $text = preg_replace('/[íìïî]/u', 'i', $text);
        $text = preg_replace('/[óòöôõ]/u', 'o', $text);
        $text = preg_replace('/[úùüû]/u', 'u', $text);
        $text = preg_replace('/[ñ]/u', 'n', $text);
        
        $text = preg_replace('/[^a-z0-9\s]/', ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        
        return trim($text);
    }

    private static function isStopWord(string $word): bool
    {
        $stopwords = [
            'de', 'la', 'el', 'y', 'en', 'con', 'sin', 'un', 'una', 'del', 'las', 'los', 'al', 'se', 'su', 'sus',
            'hoy', 'ayer', 'muere', 'fallece', 'anuncia', 'estrena', 'lanza', 'publica', 'revela', 'nuevo', 'nueva',
            'nuevos', 'nuevas', 'video', 'gira', 'tour', 'album', 'disco', 'cancion', 'single', 'adelanto', 'primer',
            'regresa', 'vuelve', 'confirma', 'cancela', 'pospone', 'detalles', 'portada', 'tracklist', 'fecha',
            'fechas', 'concierto', 'conciertos', 'festival', 'cartel', 'edicion', 'reedicion', 'aniversario', 'anos',
            'sobre', 'para', 'por', 'como', 'entre', 'desde', 'hasta', 'durante', 'tras', 'segun', 'oficial'
        ];
        return in_array($word, $stopwords);
    }
}
