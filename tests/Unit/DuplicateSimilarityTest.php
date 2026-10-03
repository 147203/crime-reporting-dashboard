<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\DuplicateDetector;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DuplicateSimilarityTest extends TestCase
{
    /** TC-UT-13: normalisation and normalised Levenshtein (V8). */
    #[Test]
    public function similarity_is_normalised_levenshtein_on_normalised_text(): void
    {
        $this->assertSame('near the market gate 2', DuplicateDetector::normalise('  Near the MARKET-gate, 2! '));
        $this->assertSame(1.0, DuplicateDetector::similarity('near market', 'near market'));
        $this->assertEqualsWithDelta(1 - 1 / 11, DuplicateDetector::similarity('near market', 'near marker'), 1e-9);
        $this->assertLessThan(0.8, DuplicateDetector::similarity('near market', 'opposite the hospital'));
        $this->assertSame(1.0, DuplicateDetector::similarity('', ''));
    }

    /** TC-UT-18: D-48, the numbers in two locations must match for a duplicate. */
    #[Test]
    public function numbers_in_a_location_are_compared_exactly(): void
    {
        $this->assertSame(['12', '3'], DuplicateDetector::numbers('plot 12 near stage 3'));
        $this->assertSame(['5'], DuplicateDetector::numbers('near the market 05'));
        $this->assertNotSame(DuplicateDetector::numbers('near the market 5'), DuplicateDetector::numbers('near the market 400'));
        $this->assertSame([], DuplicateDetector::numbers('near the market gate'));
    }
}
