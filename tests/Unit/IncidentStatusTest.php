<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\IncidentStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IncidentStatusTest extends TestCase
{
    /** TC-UT-12: Closed is terminal; no backward moves. */
    #[Test]
    public function the_status_workflow_only_moves_forward(): void
    {
        $this->assertTrue(IncidentStatus::Reported->canMoveTo(IncidentStatus::UnderInvestigation));
        $this->assertTrue(IncidentStatus::Reported->canMoveTo(IncidentStatus::Closed));
        $this->assertTrue(IncidentStatus::UnderInvestigation->canMoveTo(IncidentStatus::Closed));
        $this->assertFalse(IncidentStatus::UnderInvestigation->canMoveTo(IncidentStatus::Reported));
        $this->assertSame([], IncidentStatus::Closed->next());
    }
}
