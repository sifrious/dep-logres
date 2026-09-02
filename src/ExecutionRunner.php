<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use DateTimeImmutable;
use Throwable;

/**
 * Authoritative provider-neutral machine-side execution boundary.
 *
 * Logres lifecycle authority is supplied by RunnerLifecycle. Runtime execution is
 * supplied by a host adapter to Wardrobe. This class contains no transport,
 * provider, framework, UI, or inbound-listener behavior.
 */
final readonly class ExecutionRunner
{
    public function __construct(
        private RunnerDescriptor $runner,
        private EnvelopeAuthenticator $authenticator,
        private RunnerAuthorization $authorization,
        private RunnerWorkspace $workspace,
        private RunnerLifecycle $lifecycle,
        private RunnerRuntime $runtime,
        private RunnerLocalStateStore $localState,
        private RunnerEventSink $events,
    ) {}

    /** @param array<string, mixed> $input */
    public function execute(array $input, DateTimeImmutable $now): RunnerExecutionOutcome
    {
        try {
            $envelope = ExecutionEnvelope::parse($input);
        } catch (Throwable $error) {
            return RunnerExecutionOutcome::rejected(RunnerRejectionReason::Malformed, $error->getMessage());
        }

        $key = RunnerLocalRecord::key($envelope);
        $existing = $this->localState->find($key);
        if ($existing?->terminalResult !== null) {
            return RunnerExecutionOutcome::completed($existing->terminalResult);
        }
        if ($existing !== null && in_array($existing->stage, [RunnerLocalStage::Accepted, RunnerLocalStage::Invoking, RunnerLocalStage::Reporting], true)) {
            return $this->reject($envelope, RunnerRejectionReason::DuplicateOrAlreadyProcessed, 'This immutable execution was already accepted locally.', $now);
        }

        $this->localState->save(new RunnerLocalRecord($key, $envelope->idempotencyIdentity, RunnerLocalStage::Received, $now));

        if (! in_array($envelope->protocolVersion, $this->runner->capabilities->protocolVersions, true)) {
            return $this->reject($envelope, RunnerRejectionReason::UnsupportedProtocolVersion, 'The envelope protocol version is not supported.', $now);
        }
        if (! $this->authenticator->authenticates($envelope)) {
            return $this->reject($envelope, RunnerRejectionReason::Unauthenticated, 'The envelope signature or authentication material is invalid.', $now);
        }
        if ($now >= $envelope->expiresAt) {
            return $this->reject($envelope, RunnerRejectionReason::Expired, 'The execution envelope has expired.', $now);
        }
        if ($envelope->targetRunnerId->value !== $this->runner->identity->value) {
            return $this->reject($envelope, RunnerRejectionReason::WrongRunner, 'The work is addressed to a different runner.', $now);
        }
        if (! $this->authorization->authorizes($envelope)) {
            return $this->reject($envelope, RunnerRejectionReason::Unauthorized, 'The authorization grant does not authorize this execution.', $now);
        }
        if (! $this->workspace->isAvailable($envelope->workspaceIdentity)) {
            return $this->reject($envelope, RunnerRejectionReason::WorkspaceUnavailable, 'The addressed workspace is unavailable on this runner.', $now);
        }
        if (! $this->workspace->matches($envelope->workspaceIdentity, $envelope->workspacePath, $envelope->repositoryIdentity)) {
            return $this->reject($envelope, RunnerRejectionReason::WorkspaceMismatch, 'Workspace path or repository identity does not match the local observation.', $now);
        }
        if (! in_array($envelope->runtimeAdapter, $this->runner->capabilities->runtimeAdapters, true)
            || ! in_array($envelope->runtimeAdapter, $this->runtime->availableAdapters(), true)
            || ! $this->runtime->canInvoke($envelope->runtimeAdapter, $envelope->runtime)) {
            return $this->reject($envelope, RunnerRejectionReason::RuntimeUnavailable, 'The selected Wardrobe runtime adapter is unavailable.', $now);
        }
        if (array_diff($envelope->requiredCapabilities, $this->runner->capabilities->capabilities) !== []) {
            return $this->reject($envelope, RunnerRejectionReason::CapabilityMismatch, 'The runner does not satisfy every required capability.', $now);
        }
        if (! $this->lifecycle->permits($envelope, $this->runner->identity, $now)) {
            return $this->reject($envelope, RunnerRejectionReason::InvalidLifecycleState, 'Canonical Logres lifecycle state does not permit invocation.', $now);
        }

        $this->localState->save(new RunnerLocalRecord($key, $envelope->idempotencyIdentity, RunnerLocalStage::Accepted, $now));
        $observer = new SequencedRunnerObserver($envelope, $this->runner->identity, $this->events, $this->lifecycle, $now);
        $observer->event(RunnerEventType::Accepted);
        $observer->event(RunnerEventType::Starting);
        $this->localState->save(new RunnerLocalRecord($key, $envelope->idempotencyIdentity, RunnerLocalStage::Invoking, $now));
        $observer->event(RunnerEventType::Running);

        try {
            $runtimeResult = $this->runtime->invoke(RuntimeRequest::fromEnvelope($envelope), $observer);
        } catch (Throwable $error) {
            $runtimeResult = new RuntimeResult(RunnerTerminalStatus::Failure, failureCategory: 'runtime_invocation', failureDetail: $error->getMessage());
            $observer->event(RunnerEventType::Failure, ['category' => 'runtime_invocation', 'detail' => $error->getMessage()]);
        }

        $finishedAt = $now;
        $terminal = new RunnerTerminalResult(
            $envelope->runId, $envelope->attemptId, $envelope->leaseId, $this->runner->identity,
            $runtimeResult->status, $envelope->runtime, $envelope->runtimeAdapter, $envelope->workspaceIdentity,
            $now, $finishedAt, $runtimeResult->exitCode, failureCategory: $runtimeResult->failureCategory, failureDetail: $runtimeResult->failureDetail,
        );
        $this->localState->save(new RunnerLocalRecord($key, $envelope->idempotencyIdentity, RunnerLocalStage::Reporting, $finishedAt, $terminal));
        $observer->event(RunnerEventType::TerminalResult, ['status' => $terminal->status->value, 'exit_code' => $terminal->exitCode]);
        $this->localState->save(new RunnerLocalRecord($key, $envelope->idempotencyIdentity, RunnerLocalStage::Terminal, $finishedAt, $terminal));

        return RunnerExecutionOutcome::completed($terminal);
    }

    private function reject(ExecutionEnvelope $envelope, RunnerRejectionReason $reason, string $detail, DateTimeImmutable $now): RunnerExecutionOutcome
    {
        $terminal = new RunnerTerminalResult(
            $envelope->runId, $envelope->attemptId, $envelope->leaseId, $this->runner->identity,
            RunnerTerminalStatus::Rejected, $envelope->runtime, $envelope->runtimeAdapter, $envelope->workspaceIdentity,
            $now, $now, failureCategory: $reason->value, failureDetail: $detail,
        );
        return RunnerExecutionOutcome::rejected($reason, $detail, $terminal);
    }
}
