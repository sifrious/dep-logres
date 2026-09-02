<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

/** Immutable provider-neutral dispatch intent. Hosts map this value to their provider client or queue. */
final readonly class ProviderInvocationRequest
{
    /** @var list<string> */ public array $workspaceInstructions;
    /** @var array<string, mixed> */ public array $eventDelivery;
    /** @var array<string, mixed> */ public array $inputResponse;

    public function __construct(
        public string $invocationId,
        public RunId $runId,
        public ExecutionRequestId $requestId,
        public TaskId $taskId,
        public AttemptId $attemptId,
        public string $idempotencyKey,
        public ExecutionTargetId $targetId,
        public string $provider,
        public string $agentAdapter,
        public TaskPrompt $prompt,
        array $workspaceInstructions,
        array $eventDelivery,
        array $inputResponse,
        public int $timeoutSeconds,
        public string $cancellationReference,
    ) {
        if (trim($invocationId) === '' || trim($idempotencyKey) === '' || trim($provider) === '' || trim($agentAdapter) === '' || $timeoutSeconds < 1 || trim($cancellationReference) === '') {
            throw new InvalidArgumentException('A provider invocation requires stable identity, routing, adapter, timeout, and cancellation policy.');
        }
        if ($prompt->requestId->value !== $requestId->value || $prompt->taskId->value !== $taskId->value
            || $prompt->provenanceHash !== hash('sha256', $prompt->compilerVersion."\n".$prompt->inputHash."\n".$prompt->compiledPrompt)) {
            throw new InvalidArgumentException('Provider invocation request, task, and prompt identities must agree.');
        }
        if ($timeoutSeconds !== $prompt->input->request->constraints->timeoutSeconds) {
            throw new InvalidArgumentException('Provider invocation timeout must match the compiled request package.');
        }
        if ($workspaceInstructions === [] || ! self::nonemptyStrings($workspaceInstructions) || $eventDelivery === [] || $inputResponse === []) {
            throw new InvalidArgumentException('A provider invocation requires workspace, event-delivery, and input-response instructions.');
        }

        $this->workspaceInstructions = array_values($workspaceInstructions);
        $this->eventDelivery = $eventDelivery;
        $this->inputResponse = $inputResponse;
    }

    private static function nonemptyStrings(array $values): bool
    {
        foreach ($values as $value) {
            if (! is_string($value) || trim($value) === '') {
                return false;
            }
        }

        return true;
    }
}
