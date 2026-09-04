<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sifrious\Logres\HumanGate;
use Sifrious\Logres\NeedsInput;

final class HumanGateTest extends TestCase
{
    #[Test]
    public function it_throws_a_structured_needs_input_payload(): void
    {
        try {
            HumanGate::pause('Approve transport?', ['stdin', 'argument'], 'resume-01');
            self::fail('The human gate did not pause execution.');
        } catch (NeedsInput $gate) {
            self::assertSame([
                'status' => 'needs_input',
                'question_id' => 'resume-01',
                'prompt' => 'Approve transport?',
                'allowed_responses' => ['stdin', 'argument'],
                'response_shape' => [
                    'type' => 'string',
                    'enum' => ['stdin', 'argument'],
                ],
                'resume_token' => 'resume-01',
            ], $gate->payload());
        }
    }
}
