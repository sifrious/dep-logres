<?php

declare(strict_types=1);

namespace Sifrious\Logres;

final readonly class VerificationOutcomeAggregator
{
    public function aggregate(VerificationPlan $plan, ProviderExecutionEventLog $eventLog): VerifiedOutcome
    {
        $checks = [];
        $evidence = [];

        foreach ($plan->checks as $definition) {
            if (! $definition->enabled) {
                $checks[] = new CheckResult(
                    checkId: $definition->id,
                    checkName: $definition->name,
                    required: $definition->required,
                    disposition: CheckDisposition::SkippedByPolicy,
                );
                continue;
            }

            $event = $this->latestEventForCheck($eventLog, $definition);
            if ($event === null) {
                $checks[] = new CheckResult(
                    checkId: $definition->id,
                    checkName: $definition->name,
                    required: $definition->required,
                    disposition: CheckDisposition::Incomplete,
                );
                continue;
            }

            $reference = $this->referenceFromEvent($event);
            $result = new CheckResult(
                checkId: $definition->id,
                checkName: $definition->name,
                required: $definition->required,
                disposition: $this->dispositionFromEvent($event),
                evidence: [$reference],
                exitStatus: $this->intFromPayload($event->payload, ['exit_status', 'exit_code', 'status_code']),
                boundedOutput: $this->boundedOutput($event->payload),
                durationMs: $this->intFromPayload($event->payload, ['duration_ms']),
                toolVersion: $this->stringFromPayload($event->payload, ['tool_version', 'version']),
            );
            $checks[] = $result;
            $evidence[] = $reference;
        }

        [$requiredOutcome, $verificationStatus] = $this->decisionTable($checks);
        $summary = $this->summary($checks, $requiredOutcome);

        return new VerifiedOutcome(
            requiredVerification: $requiredOutcome,
            verificationStatus: $verificationStatus,
            observedOutcome: $summary,
            checks: $checks,
            evidence: $evidence,
        );
    }

    private function latestEventForCheck(ProviderExecutionEventLog $eventLog, CheckDefinition $definition): ?ExecutionEvent
    {
        $matched = array_values(array_filter(
            $eventLog->events,
            static fn (ExecutionEvent $event): bool => $definition->matches($event),
        ));
        if ($matched === []) {
            return null;
        }

        usort($matched, static fn (ExecutionEvent $left, ExecutionEvent $right): int => $left->sequence <=> $right->sequence);

        return $matched[array_key_last($matched)];
    }

    private function dispositionFromEvent(ExecutionEvent $event): CheckDisposition
    {
        $raw = strtolower(trim((string) ($event->payload['verification_status'] ?? $event->payload['status'] ?? $event->payload['result'] ?? '')));
        $raw = str_replace(['-', ' '], '_', $raw);

        if ($raw !== '') {
            return match ($raw) {
                'pass', 'passed', 'success', 'succeeded' => CheckDisposition::Passed,
                'fail', 'failed', 'failure' => CheckDisposition::Failed,
                'incomplete', 'partial', 'pending' => CheckDisposition::Incomplete,
                'skipped', 'skipped_by_policy' => CheckDisposition::SkippedByPolicy,
                'unavailable' => CheckDisposition::Unavailable,
                default => CheckDisposition::Incomplete,
            };
        }

        $exit = $this->intFromPayload($event->payload, ['exit_status', 'exit_code', 'status_code']);
        if ($exit === null) {
            return CheckDisposition::Incomplete;
        }

        return $exit === 0 ? CheckDisposition::Passed : CheckDisposition::Failed;
    }

    private function referenceFromEvent(ExecutionEvent $event): EvidenceReference
    {
        $payloadReference = $event->payload['reference'] ?? [];
        $kind = is_array($payloadReference) && is_string($payloadReference['kind'] ?? null)
            ? (string) $payloadReference['kind']
            : $event->type;

        return new EvidenceReference(
            kind: $kind,
            locator: $this->locatorFromEvent($event),
            observedAt: $event->occurredAt->format(DATE_ATOM),
            sequence: $event->sequence,
            metadata: [
                'event_type' => $event->type,
                'reference' => $payloadReference,
            ],
        );
    }

    private function locatorFromEvent(ExecutionEvent $event): string
    {
        $reference = $event->payload['reference'] ?? [];
        if (! is_array($reference)) {
            return "sequence:{$event->sequence}";
        }

        if (($reference['kind'] ?? null) === 'test_execution') {
            return sprintf('suite:%s#%s', (string) ($reference['suite'] ?? 'unknown'), (string) ($reference['name'] ?? 'unknown'));
        }

        if (($reference['kind'] ?? null) === 'tool_invocation') {
            return sprintf('tool:%s#%s', (string) ($reference['tool_name'] ?? 'unknown'), (string) ($reference['invocation_id'] ?? 'unknown'));
        }

        if (($reference['kind'] ?? null) === 'command_execution') {
            return sprintf('command:%s', (string) ($reference['command_id'] ?? 'unknown'));
        }

        return "sequence:{$event->sequence}";
    }

    /** @param list<CheckResult> $checks @return array{RequiredVerificationOutcome, VerificationStatus} */
    private function decisionTable(array $checks): array
    {
        $required = array_values(array_filter($checks, static fn (CheckResult $check): bool => $check->required));
        $scan = $required === [] ? $checks : $required;
        $hasPassed = false;

        foreach ($scan as $check) {
            if ($check->disposition === CheckDisposition::Failed) {
                return [RequiredVerificationOutcome::Failed, VerificationStatus::Failed];
            }
            if ($check->disposition === CheckDisposition::Unavailable) {
                return [RequiredVerificationOutcome::Unavailable, VerificationStatus::Unavailable];
            }
            if ($check->disposition === CheckDisposition::Incomplete) {
                return [RequiredVerificationOutcome::Incomplete, VerificationStatus::Incomplete];
            }
            if ($check->disposition === CheckDisposition::Passed) {
                $hasPassed = true;
            }
        }

        foreach ($scan as $check) {
            if ($check->disposition === CheckDisposition::SkippedByPolicy) {
                return [RequiredVerificationOutcome::SkippedByPolicy, VerificationStatus::SkippedByPolicy];
            }
        }

        if ($hasPassed || $scan === []) {
            return [RequiredVerificationOutcome::Passed, VerificationStatus::Succeeded];
        }

        return [RequiredVerificationOutcome::Incomplete, VerificationStatus::Incomplete];
    }

    /** @param list<CheckResult> $checks */
    private function summary(array $checks, RequiredVerificationOutcome $outcome): string
    {
        $counts = [];
        foreach ($checks as $check) {
            $counts[$check->disposition->value] = ($counts[$check->disposition->value] ?? 0) + 1;
        }
        ksort($counts);

        $parts = [];
        foreach ($counts as $disposition => $count) {
            $parts[] = "{$disposition}:{$count}";
        }

        return 'verification='.$outcome->value.' ['.implode(', ', $parts).']';
    }

    /** @param array<string, mixed> $payload @param list<string> $keys */
    private function intFromPayload(array $payload, array $keys): ?int
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $payload)) {
                continue;
            }
            $value = $payload[$key];
            if (is_int($value)) {
                return $value;
            }
            if (is_numeric($value)) {
                return (int) $value;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $payload @param list<string> $keys */
    private function stringFromPayload(array $payload, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (is_string($payload[$key] ?? null)) {
                return (string) $payload[$key];
            }
        }

        return null;
    }

    /** @param array<string, mixed> $payload */
    private function boundedOutput(array $payload): string
    {
        $output = '';
        if (is_string($payload['output'] ?? null)) {
            $output = $payload['output'];
        } elseif (is_string($payload['stdout'] ?? null) || is_string($payload['stderr'] ?? null)) {
            $output = (string) ($payload['stdout'] ?? '');
            $stderr = (string) ($payload['stderr'] ?? '');
            if ($stderr !== '') {
                $output = trim($output."\n".$stderr);
            }
        }

        return strlen($output) > 2048 ? substr($output, 0, 2048) : $output;
    }
}
