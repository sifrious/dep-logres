<?php

declare(strict_types=1);

namespace Sifrious\Logres;

final class HumanGate
{
    public static function pause(string $prompt, array $allowedResponses, string $resumeToken): never
    {
        throw new NeedsInput($prompt, $allowedResponses, $resumeToken);
    }
}
