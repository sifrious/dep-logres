<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use RuntimeException;

final class ToolAuthorizer
{
    public static function select(iterable $tools, ToolAuthorizationContext $context): array
    {
        $allowed = [];

        foreach ($tools as $tool) {
            if ($tool instanceof ToolManifest && self::allows($tool, $context)) {
                $allowed[] = $tool;
            }
        }

        usort($allowed, static fn (ToolManifest $left, ToolManifest $right): int => $left->id <=> $right->id);

        return $allowed;
    }

    public static function assertInvocationAllowed(ToolManifest $tool, ToolAuthorizationContext $context): void
    {
        if (! self::allows($tool, $context)) {
            throw new RuntimeException("Tool {$tool->id} is not authorized for this invocation.");
        }
    }

    private static function allows(ToolManifest $tool, ToolAuthorizationContext $context): bool
    {
        return in_array($context->operator, $tool->authorization['operators'], true)
            && in_array($context->workspace, $tool->authorization['workspaces'], true)
            && in_array($tool->capability, $tool->authorization['capabilities'], true)
            && in_array($tool->capability, $context->capabilities, true);
    }
}
