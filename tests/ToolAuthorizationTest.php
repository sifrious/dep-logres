<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sifrious\Logres\ToolAuthorizationContext;
use Sifrious\Logres\ToolAuthorizer;
use Sifrious\Logres\ToolManifest;

final class ToolAuthorizationTest extends TestCase
{
    #[Test]
    public function a_tool_manifest_requires_authorization(): void
    {
        $manifest = self::manifest();
        unset($manifest['authorization']);

        $this->expectException(InvalidArgumentException::class);

        ToolManifest::fromArray($manifest);
    }

    #[Test]
    public function operator_workspace_and_capability_jointly_filter_tools(): void
    {
        $allowed = ToolManifest::fromArray(self::manifest('files.read', 'filesystem.read'));
        $denied = ToolManifest::fromArray(self::manifest('process.start', 'process.start'));
        $context = new ToolAuthorizationContext('local', 'workspace-one', ['filesystem.read']);

        $selected = ToolAuthorizer::select([$denied, $allowed], $context);

        self::assertSame(['files.read'], array_map(static fn (ToolManifest $tool): string => $tool->id, $selected));
    }

    #[Test]
    public function direct_invocation_is_reauthorized(): void
    {
        $tool = ToolManifest::fromArray(self::manifest());

        $this->expectException(RuntimeException::class);

        ToolAuthorizer::assertInvocationAllowed(
            $tool,
            new ToolAuthorizationContext('local', 'workspace-two', ['filesystem.read']),
        );
    }

    private static function manifest(string $id = 'files.read', string $capability = 'filesystem.read'): array
    {
        return [
            'id' => $id,
            'capability' => $capability,
            'input_schema' => ['type' => 'object'],
            'authorization' => [
                'operators' => ['local'],
                'workspaces' => ['workspace-one'],
                'capabilities' => [$capability],
            ],
            'binding' => 'fixture',
        ];
    }
}
