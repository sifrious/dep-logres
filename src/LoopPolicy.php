<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class LoopPolicy
{
    public function __construct(
        public string $name,
        public string $version,
        public DateTimeImmutable $wallClockDeadline,
        public int $maximumSteps,
        public int $maximumAttempts,
        public int $maximumToolCalls,
        public int $maximumConsecutiveFailures,
        public int $maximumTokens,
        public int $maximumCostMicros,
        public int $maximumDelegationDepth,
        public int $maximumConcurrentChildren,
        public int $maximumInputWaitSeconds,
        public bool $independentVerificationRequired = true,
    ) {
        if (trim($name) === '' || trim($version) === '') {
            throw new InvalidArgumentException('A loop policy requires a stable name and version.');
        }
        if ($wallClockDeadline->getOffset() !== 0) {
            throw new InvalidArgumentException('A loop policy deadline must be expressed in UTC.');
        }
        foreach ([
            $maximumSteps,
            $maximumAttempts,
            $maximumToolCalls,
            $maximumConsecutiveFailures,
            $maximumTokens,
            $maximumCostMicros,
        ] as $positiveLimit) {
            if ($positiveLimit < 1) {
                throw new InvalidArgumentException('Loop execution budgets must be positive.');
            }
        }
        if ($maximumDelegationDepth < 0 || $maximumConcurrentChildren < 0 || $maximumInputWaitSeconds < 0) {
            throw new InvalidArgumentException('Loop delegation, child, and input-wait budgets cannot be negative.');
        }
    }

    /** @return array<string, int|string|bool> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'version' => $this->version,
            'wall_clock_deadline' => $this->wallClockDeadline->format(DATE_ATOM),
            'maximum_steps' => $this->maximumSteps,
            'maximum_attempts' => $this->maximumAttempts,
            'maximum_tool_calls' => $this->maximumToolCalls,
            'maximum_consecutive_failures' => $this->maximumConsecutiveFailures,
            'maximum_tokens' => $this->maximumTokens,
            'maximum_cost_micros' => $this->maximumCostMicros,
            'maximum_delegation_depth' => $this->maximumDelegationDepth,
            'maximum_concurrent_children' => $this->maximumConcurrentChildren,
            'maximum_input_wait_seconds' => $this->maximumInputWaitSeconds,
            'independent_verification_required' => $this->independentVerificationRequired,
        ];
    }

    /** @param array<string, mixed> $input */
    public static function fromArray(array $input): self
    {
        $required = [
            'name', 'version', 'wall_clock_deadline', 'maximum_steps', 'maximum_attempts',
            'maximum_tool_calls', 'maximum_consecutive_failures', 'maximum_tokens',
            'maximum_cost_micros', 'maximum_delegation_depth', 'maximum_concurrent_children',
            'maximum_input_wait_seconds', 'independent_verification_required',
        ];
        if (array_diff($required, array_keys($input)) !== []) {
            throw new InvalidArgumentException('A persisted loop policy must contain every policy field.');
        }

        try {
            return new self(
                name: self::string($input['name']),
                version: self::string($input['version']),
                wallClockDeadline: new DateTimeImmutable(self::string($input['wall_clock_deadline'])),
                maximumSteps: self::integer($input['maximum_steps']),
                maximumAttempts: self::integer($input['maximum_attempts']),
                maximumToolCalls: self::integer($input['maximum_tool_calls']),
                maximumConsecutiveFailures: self::integer($input['maximum_consecutive_failures']),
                maximumTokens: self::integer($input['maximum_tokens']),
                maximumCostMicros: self::integer($input['maximum_cost_micros']),
                maximumDelegationDepth: self::integer($input['maximum_delegation_depth']),
                maximumConcurrentChildren: self::integer($input['maximum_concurrent_children']),
                maximumInputWaitSeconds: self::integer($input['maximum_input_wait_seconds']),
                independentVerificationRequired: self::boolean($input['independent_verification_required']),
            );
        } catch (\Throwable $error) {
            throw new InvalidArgumentException('The persisted loop policy is invalid.', previous: $error);
        }
    }

    private static function string(mixed $value): string
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException('Expected a string policy value.');
        }

        return $value;
    }

    private static function integer(mixed $value): int
    {
        if (! is_int($value)) {
            throw new InvalidArgumentException('Expected an integer policy value.');
        }

        return $value;
    }

    private static function boolean(mixed $value): bool
    {
        if (! is_bool($value)) {
            throw new InvalidArgumentException('Expected a boolean policy value.');
        }

        return $value;
    }
}
