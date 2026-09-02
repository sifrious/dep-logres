<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

/** Provider-neutral request mapped by the host to Wardrobe's RuntimeInvocation. */
final readonly class RuntimeRequest
{
    public function __construct(
        public RunId $runId,
        public AttemptId $attemptId,
        public LeaseId $leaseId,
        public WorkspaceAuthority $workspaceIdentity,
        public WorkspacePath $workspacePath,
        public RepositoryIdentity $repositoryIdentity,
        public string $runtime,
        public string $adapter,
        public array $payload,
        public ?StacksExecutionContext $stacksContext = null,
    ) {
        if (trim($runtime) === '' || trim($adapter) === '') {
            throw new InvalidArgumentException('A runtime request requires a runtime and adapter.');
        }
    }

    public static function fromEnvelope(ExecutionEnvelope $envelope): self
    {
        return new self($envelope->runId, $envelope->attemptId, $envelope->leaseId, $envelope->workspaceIdentity, $envelope->workspacePath, $envelope->repositoryIdentity, $envelope->runtime, $envelope->runtimeAdapter, $envelope->requestPayload, $envelope->stacksContext);
    }
}
