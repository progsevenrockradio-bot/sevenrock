<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\RadioArtist;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class BandProfileMatcher
{
    /**
     * @return Collection<int, RadioArtist>
     */
    public function search(string $term, int $limit = 10): Collection
    {
        $term = trim($term);
        if ($term === '' || ! $this->hasTable()) {
            return collect();
        }

        $normalizedTerm = $this->normalizeKey($term);
        $like = '%' . mb_strtolower($term) . '%';

        $candidates = RadioArtist::query()
            ->where(function ($query) use ($like): void {
                $query
                    ->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw("LOWER(COALESCE(editorial_summary, biography, '')) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(COALESCE(related_artists, '')) LIKE ?", [$like]);
            })
            ->orderBy('name')
            ->limit(150)
            ->get();

        if ($candidates->isEmpty()) {
            $candidates = RadioArtist::query()
                ->orderBy('name')
                ->limit(150)
                ->get();
        }

        return $this->rankCandidates($candidates, $term, $normalizedTerm)
            ->take(max(1, $limit))
            ->values();
    }

    public function exactMatch(string $term): ?RadioArtist
    {
        return $this->bestMatch($term, false);
    }

    public function fuzzyMatch(string $term): ?RadioArtist
    {
        return $this->bestMatch($term, true);
    }

    private function bestMatch(string $term, bool $allowFuzzy): ?RadioArtist
    {
        $term = trim($term);
        if ($term === '' || ! $this->hasTable()) {
            return null;
        }

        $normalizedTerm = $this->normalizeKey($term);
        if ($normalizedTerm === '') {
            return null;
        }

        $candidate = $this->rankCandidates(
            RadioArtist::query()->get(),
            $term,
            $normalizedTerm
        )->first();

        if (! $candidate instanceof RadioArtist) {
            return null;
        }

        $score = $this->scoreCandidate($candidate, $term, $normalizedTerm);
        if (! $allowFuzzy) {
            return $score >= 1000 ? $candidate : null;
        }

        // Fuzzy matching is intentionally conservative.
        // The previous threshold was permissive enough to cross-match similar band names
        // (for example, Matchbook Romance -> My Chemical Romance).
        return $score >= 650 ? $candidate : null;
    }

    /**
     * @param Collection<int, RadioArtist> $candidates
     * @return Collection<int, RadioArtist>
     */
    private function rankCandidates(Collection $candidates, string $term, string $normalizedTerm): Collection
    {
        return $candidates
            ->sortByDesc(fn (RadioArtist $candidate): int => $this->scoreCandidate($candidate, $term, $normalizedTerm))
            ->values();
    }

    private function scoreCandidate(RadioArtist $candidate, string $term, string $normalizedTerm): int
    {
        $normalizedName = $this->normalizeKey((string) $candidate->name);
        if ($normalizedName === $normalizedTerm) {
            return 1000;
        }

        foreach ((array) $candidate->related_artists as $relatedArtist) {
            if (! is_string($relatedArtist)) {
                continue;
            }

            $normalizedRelated = $this->normalizeKey($relatedArtist);
            if ($normalizedRelated === $normalizedTerm) {
                return 950;
            }
        }

        if ($normalizedName !== '' && str_starts_with($normalizedName, $normalizedTerm)) {
            return 850;
        }

        if ($normalizedName !== '' && str_starts_with($normalizedTerm, $normalizedName)) {
            return 800;
        }

        if ($normalizedName !== '' && str_contains($normalizedName, $normalizedTerm)) {
            return 700;
        }

        foreach ((array) $candidate->related_artists as $relatedArtist) {
            if (! is_string($relatedArtist)) {
                continue;
            }

            $normalizedRelated = $this->normalizeKey($relatedArtist);
            if ($normalizedRelated !== '' && str_contains($normalizedRelated, $normalizedTerm)) {
                return 650;
            }
        }

        $best = 0.0;
        if ($normalizedName !== '') {
            similar_text($normalizedName, $normalizedTerm, $percent);
            $best = max($best, (float) $percent);
        }

        foreach ((array) $candidate->related_artists as $relatedArtist) {
            if (! is_string($relatedArtist)) {
                continue;
            }

            $normalizedRelated = $this->normalizeKey($relatedArtist);
            if ($normalizedRelated === '') {
                continue;
            }

            similar_text($normalizedRelated, $normalizedTerm, $percent);
            $best = max($best, (float) $percent);
        }

        if ($best <= 0) {
            return 0;
        }

        return 500 + (int) round($best);
    }

    private function hasTable(): bool
    {
        try {
            return Schema::hasTable('radio_artists');
        } catch (\Throwable) {
            return false;
        }
    }

    private function normalizeKey(string $value): string
    {
        return preg_replace('/[^a-z0-9]+/i', '', mb_strtolower(trim(Str::ascii($value)))) ?: '';
    }
}
