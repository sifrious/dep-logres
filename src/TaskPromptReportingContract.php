<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class TaskPromptReportingContract
{
    public array $eventTypes;

    public function __construct(
        array $eventTypes,
        public string $inputRequestMethod,
    ) {
        if ($eventTypes === [] || trim($inputRequestMethod) === '') {
            throw new InvalidArgumentException('A reporting contract requires event types and an input-request method.');
        }

        $this->eventTypes = array_values($eventTypes);
    }

    public function canonicalData(): array
    {
        return ['event_types' => $this->eventTypes, 'input_request_method' => $this->inputRequestMethod];
    }
}
