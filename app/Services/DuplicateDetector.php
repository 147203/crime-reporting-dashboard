<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DuplicateStatus;
use App\Models\Incident;
use Illuminate\Support\Str;

/**
 * V8 duplicate candidates (docs/02 section 5): same category and ward, occurred_at within the window, and normalised
 * location similarity at least the threshold (normalised Levenshtein). Records without a location description are not
 * compared (D-38), and the numbers in the two locations must be the same (D-48). A match is only flagged for review;
 * nothing is merged or deleted automatically.
 */
final class DuplicateDetector
{
    public function findMatch(Incident $incident): ?Incident
    {
        $location = self::normalise($incident->location_description);

        if ($location === '') {
            return null;
        }

        $window = (int) config('analytics.duplicates.window_minutes');
        $threshold = (float) config('analytics.duplicates.similarity');

        $candidates = Incident::query()
            ->where('category_id', $incident->category_id)
            ->where('ward_id', $incident->ward_id)
            ->whereBetween('occurred_at', [$incident->occurred_at->copy()->subMinutes($window), $incident->occurred_at->copy()->addMinutes($window)])
            ->where('duplicate_status', '!=', DuplicateStatus::Confirmed->value)
            ->whereNotNull('location_description')
            // Never link a record to itself or back to one of its own duplicates (that would form a loop).
            ->when($incident->exists, fn ($q) => $q->whereKeyNot($incident->getKey())->where(fn ($inner) => $inner->whereNull('duplicate_of_id')->orWhere('duplicate_of_id', '!=', $incident->getKey())))
            ->orderByRaw('ABS(TIMESTAMPDIFF(MINUTE, occurred_at, ?))', [$incident->occurred_at->format('Y-m-d H:i:s')])
            ->orderBy('id')
            ->limit(50)
            ->get(['id', 'location_description', 'occurred_at']);

        $best = null;
        $bestScore = -1.0;
        $numbers = self::numbers($location);

        foreach ($candidates as $candidate) {
            $other = self::normalise($candidate->location_description);
            // D-48: two places that differ in a house, plot or stage number are different places, however similar.
            if (self::numbers($other) !== $numbers) {
                continue;
            }

            $score = self::similarity($location, $other);

            if ($score >= $threshold && $score > $bestScore) {
                $best = $candidate;
                $bestScore = $score;
            }
        }

        return $best;
    }

    public static function normalise(?string $text): string
    {
        // Transliterated to ASCII so the byte-based Levenshtein distance counts characters, not bytes.
        $text = mb_strtolower(Str::ascii((string) $text));
        $text = (string) preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * The numbers in a normalised location, in order (for example "plot 12 near stage 3" gives ["12", "3"]).
     *
     * @return list<string>
     */
    public static function numbers(string $normalised): array
    {
        preg_match_all('/\d+/', $normalised, $matches);

        return array_map(fn (string $n): string => ltrim($n, '0') === '' ? '0' : ltrim($n, '0'), $matches[0]);
    }

    /**
     * 1 minus the Levenshtein distance divided by the longer length; 1.0 means identical.
     */
    public static function similarity(string $a, string $b): float
    {
        $longest = max(strlen($a), strlen($b));

        if ($longest === 0) {
            return 1.0;
        }

        return 1 - levenshtein($a, $b) / $longest;
    }
}
