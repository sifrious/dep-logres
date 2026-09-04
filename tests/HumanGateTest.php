<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sifrious\Elwin\Handoff\HandoffPayload;
use Sifrious\Elwin\Handoff\ResumableHandoff;
use Sifrious\Elwin\Handoff\ResumeContext;
use Sifrious\Logres\HumanGate;
use Sifrious\Logres\NeedsInput;
use Sifrious\ReferenceContract\CrossPackageReference;

final class HumanGateTest extends TestCase
{
    #[Test]
    public function it_throws_a_structured_needs_input_payload(): void
    {
        try {
            HumanGate::pause($this->handoff());
            self::fail('The human gate did not pause execution.');
        } catch (NeedsInput $gate) {
            self::assertSame([
                'status' => 'needs_input',
                'handoff' => $gate->handoff->reference()->toArray(),
                'resume_context' => $gate->handoff->resumeContext->jsonSerialize(),
            ], $gate->payload());
            self::assertArrayNotHasKey('prompt', $gate->payload());
            self::assertArrayNotHasKey('allowed_responses', $gate->payload());
        }
    }

    private function handoff(): ResumableHandoff
    {
        $at = new DateTimeImmutable('2026-09-04T12:00:00Z');

        return new ResumableHandoff(
            'handoff:1',
            new CrossPackageReference('sifrious/elwin', 'conversation', 'conversation:1'),
            new CrossPackageReference('sifrious/logres', 'run', 'run:1'),
            new CrossPackageReference('sifrious/elwin', 'question', 'question:1'),
            new ResumeContext(
                'resume-secret',
                new CrossPackageReference('sifrious/logres', 'turn-checkpoint', 'checkpoint:1'),
            ),
            new HandoffPayload('sifrious.elwin.intervention-context/v1', ['summary' => 'Approve transport?']),
            $at,
            $at->modify('+1 hour'),
        );
    }
}
