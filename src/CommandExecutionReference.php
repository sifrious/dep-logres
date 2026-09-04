<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class CommandExecutionReference implements ExecutionEventReference
{
    public function __construct(
        public string $commandId,
        public string $command,
    ) {
        if (trim($this->commandId) === '' || trim($this->command) === '') {
            throw new InvalidArgumentException('Command execution references require identity and command text.');
        }
    }

    public function toArray(): array
    {
        return [
            'kind' => 'command_execution',
            'command_id' => $this->commandId,
            'command' => $this->command,
        ];
    }
}
