<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class CheckResult
{
    private const OUTPUT_LIMIT = 2048;

    /** @var list<EvidenceReference> */
    public array $evidence;

    /** @param list<mixed> $evidence */
    public function __construct(
        public string $checkId,
        public string $checkName,
        public bool $required,
        public CheckDisposition $disposition,
        array $evidence = [],
        public ?int $exitStatus = null,
        public string $boundedOutput = '',
        public ?int $durationMs = null,
        public ?string $toolVersion = null,
    ) {
        if (trim($this->checkId) === '' || trim($this->checkName) === '') {
            throw new InvalidArgumentException('Check results require check identity and name.');
        }

        foreach ($evidence as $item) {
            if (! $item instanceof EvidenceReference) {
                throw new InvalidArgumentException('Check result evidence must contain EvidenceReference values.');
            }
        }
        $this->evidence = $evidence;

        if ($this->durationMs !== null && $this->durationMs < 0) {
            throw new InvalidArgumentException('Check result duration must be positive.');
        }
    }

    public function output(): string
    {
        return strlen($this->boundedOutput) > self::OUTPUT_LIMIT
            ? substr($this->boundedOutput, 0, self::OUTPUT_LIMIT)
            : $this->boundedOutput;
    }
}
