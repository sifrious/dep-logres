<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PackageBoundaryTest extends TestCase
{
    #[Test]
    public function runtime_dependencies_are_limited_to_php(): void
    {
        $manifest = json_decode(
            file_get_contents(dirname(__DIR__).'/composer.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        self::assertSame(['php' => '^8.3', 'sifrious/wardrobe' => 'dev-main'], $manifest['require']);
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

    #[Test]
    public function runner_orchestration_has_no_framework_ui_or_provider_sdk_dependency(): void
    {
        $runnerSources = implode("\n", array_map(
            static fn (string $path): string => file_get_contents($path),
            glob(dirname(__DIR__).'/src/*Runner*.php') ?: [],
        ));

        foreach (['Illuminate\\', 'Livewire\\', 'Anthropic\\', 'OpenAI\\', 'Laravel\\'] as $forbiddenNamespace) {
            self::assertStringNotContainsString($forbiddenNamespace, $runnerSources);
        }

        self::assertStringNotContainsString('Http\\Controllers', $runnerSources);
        self::assertStringNotContainsString('shell_exec(', $runnerSources);
        self::assertStringNotContainsString('proc_open(', $runnerSources);
    }
}
