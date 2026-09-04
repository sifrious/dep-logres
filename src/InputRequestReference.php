<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class InputRequestReference implements ExecutionEventReference
{
    public function __construct(
        public string $requestId,
        public string $prompt,
    ) {
        if (trim($this->requestId) === '' || trim($this->prompt) === '') {
            throw new InvalidArgumentException('Input-request references require identity and prompt.');
        }
    }

    public function toArray(): array
    {
        return [
            'kind' => 'input_request',
            'request_id' => $this->requestId,
            'prompt' => $this->prompt,
        ];
    }
}
