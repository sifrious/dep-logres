<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PackageBoundaryTest extends TestCase
{
    #[Test]
    public function runtime_dependencies_are_limited_to_php_and_portable_contracts(): void
    {
        $manifest = json_decode(
            file_get_contents(dirname(__DIR__).'/composer.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        self::assertSame([
            'php' => '^8.3',
            'sifrious/authorization-contract' => 'dev-main',
            'sifrious/reference-contract' => '^1.0',
            'sifrious/stacks-contract' => '^1.0',
            'sifrious/elwin' => 'dev-cursor/resumable-handoffs-b1d5#9667abdef4ea97e532ea557b36fa944b2572732b',
            'sifrious/elwin-clarification-contract' => 'dev-pr-9',
        ], $manifest['require']);
    }

    #[Test]
    public function every_source_type_is_listed_in_the_public_api(): void
    {
        $api = file_get_contents(dirname(__DIR__).'/PUBLIC-API.md');
        $sourceFiles = glob(dirname(__DIR__).'/src/*.php');

        self::assertIsArray($sourceFiles);

        foreach ($sourceFiles as $sourceFile) {
            $name = pathinfo($sourceFile, PATHINFO_FILENAME);
            self::assertStringContainsString("`{$name}`", $api, "{$name} is missing from PUBLIC-API.md.");
        }
    }
}
