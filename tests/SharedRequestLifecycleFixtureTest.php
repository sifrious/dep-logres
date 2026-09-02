<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sifrious\HarnessContractFixtures\Fixture;

final class SharedRequestLifecycleFixtureTest extends TestCase
{
    #[Test]
    public function logres_boundary_uses_the_shared_request_lifecycle_fixture(): void
    {
        $fixture = Fixture::load('request-lifecycle-v1');

        self::assertSame($fixture['execution_request']['id'], $fixture['preflight']['request_id']);
        self::assertSame('passed', $fixture['run_result']['required_verification']);
        self::assertSame('succeeded', $fixture['run_result']['canonical_status']);
        self::assertSame('failed', $fixture['failed_verification_result']['required_verification']);
        self::assertSame('failed', $fixture['failed_verification_result']['canonical_status']);
    }
}
