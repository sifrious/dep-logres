<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use DateTimeImmutable;

final readonly class RunnerLocalRecord
{
    public function __construct(
        public string $executionKey,
        public string $idempotencyIdentity,
        public string $envelopeFingerprint,
        public RunnerLocalStage $stage,
        public DateTimeImmutable $updatedAt,
        public ?RunnerTerminalResult $terminalResult = null,
    ) {}

    public static function key(ExecutionEnvelope $envelope): string
    {
        return implode('|', [$envelope->runId->value, $envelope->attemptId->value, $envelope->leaseId->value]);
    }

    public static function fingerprint(ExecutionEnvelope $envelope): string
    {
        $value = [
            'run_id' => $envelope->runId->value,
            'attempt_id' => $envelope->attemptId->value,
            'target_runner_id' => $envelope->targetRunnerId->value,
            'workspace_identity' => $envelope->workspaceIdentity->value,
            'workspace_path' => $envelope->workspacePath->value,
            'repository_identity' => $envelope->repositoryIdentity->value,
            'runtime' => $envelope->runtime,
            'runtime_adapter' => $envelope->runtimeAdapter,
            'authorization_grant_reference' => $envelope->authorizationGrantReference,
            'protocol_version' => $envelope->protocolVersion,
            'required_capabilities' => $envelope->requiredCapabilities,
            'request_payload' => $envelope->requestPayload,
            'stacks_context' => $envelope->stacksContext === null ? null : serialize($envelope->stacksContext),
        ];
        self::sortRecursively($value);

        return hash('sha256', serialize($value));
    }

    private static function sortRecursively(array &$value): void
    {
        foreach ($value as &$item) {
            if (is_array($item)) {
                self::sortRecursively($item);
            }
        }
        unset($item);
        if (! array_is_list($value)) {
            ksort($value);
        }
    }
}
