<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class ToolInvocationReference implements ExecutionEventReference
{
    public function __construct(
        public string $invocationId,
        public string $toolName,
    ) {
        if (trim($this->invocationId) === '' || trim($this->toolName) === '') {
            throw new InvalidArgumentException('Tool invocation references require identity and tool name.');
        }
    }

    public function toArray(): array
    {
        return [
            'kind' => 'tool_invocation',
            'invocation_id' => $this->invocationId,
            'tool_name' => $this->toolName,
        ];
    }
}
