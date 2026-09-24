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
}
