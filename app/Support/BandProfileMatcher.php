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

        $bestCandidate = $this->rankCandidates(
            RadioArtist::query()->get(),
            $term,
            $normalizedTerm
        )->first();

        if ($bestCandidate instanceof RadioArtist) {
            $score = $this->scoreCandidate($bestCandidate, $term, $normalizedTerm);
            if (! $allowFuzzy) {
                return $score >= 1000 ? $bestCandidate : null;
            }

            // Fuzzy match threshold:
            // High confidence required (>= 750) to prevent cross-matching different bands.
            // Ambiguous matches return null to ensure neutral player metadata.
            return $score >= 750 ? $bestCandidate : null;
        }

        return null;
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

        $termLen = mb_strlen($normalizedTerm);
        $nameLen = mb_strlen($normalizedName);

        // Prefix matching: only if term is substantial (>= 4 chars) and covers significant part of the name
        if ($normalizedName !== '' && $termLen >= 4 && str_starts_with($normalizedName, $normalizedTerm)) {
            $ratio = $termLen / max(1, $nameLen);
            if ($ratio >= 0.70) {
                return 850;
            }
        }

        if ($normalizedName !== '' && $nameLen >= 4 && str_starts_with($normalizedTerm, $normalizedName)) {
            $ratio = $nameLen / max(1, $termLen);
            if ($ratio >= 0.70) {
                return 800;
            }
        }

        // Substring matching: only if term is substantial and high ratio
        if ($normalizedName !== '' && $termLen >= 5 && str_contains($normalizedName, $normalizedTerm)) {
            $ratio = $termLen / max(1, $nameLen);
            if ($ratio >= 0.75) {
                return 700;
            }
        }

        // Related artists: NEVER override primary artist matching.
        // Cap related artist matches at 550 so they never exceed the fuzzy threshold (750)
        // and cannot masquerade as the searched band.
        foreach ((array) $candidate->related_artists as $relatedArtist) {
            if (! is_string($relatedArtist)) {
                continue;
            }

            $normalizedRelated = $this->normalizeKey($relatedArtist);
            if ($normalizedRelated === $normalizedTerm) {
                return 550;
            }
        }

        $best = 0.0;
        if ($normalizedName !== '') {
            similar_text($normalizedName, $normalizedTerm, $percent);
            $best = max($best, (float) $percent);
        }

        if ($best <= 0) {
            return 0;
        }

        // similar_text gives 0-100.
        // 500 + 100 = max 600.
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
