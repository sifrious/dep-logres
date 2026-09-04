<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class ExecutionState
{
    /** @param list<ExecutionAttempt> $attempts */
    public function __construct(
        public RunId $runId,
        public RunStatus $status,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $scheduledAt = null,
        public ?DateTimeImmutable $startedAt = null,
        public ?DateTimeImmutable $finishedAt = null,
        public ?AttemptId $activeAttemptId = null,
        public array $attempts = [],
        public ?string $failureReason = null,
        public ?string $terminalResultReference = null,
        public int $version = 0,
        public ?ExecutionStateDetails $details = null,
        public ?RecoveryRecord $recovery = null,
        public ?CancellationIntent $cancellation = null,
        /** @var list<HumanInputRecord> */
        public array $humanInputs = [],
    ) {
        if ($version < 0) {
            throw new InvalidArgumentException('Execution-state version cannot be negative.');
        }
        if ($status->isTerminal() && ($finishedAt === null || $activeAttemptId !== null)) {
            throw new InvalidArgumentException('A terminal Run requires a finish time and cannot retain an active Attempt.');
        }
        foreach ($attempts as $attempt) {
            if ($attempt->runId->value !== $runId->value) {
                throw new InvalidArgumentException('Every Attempt must belong to this Run.');
            }
        }
        $outstanding = array_filter($humanInputs, static fn (HumanInputRecord $input): bool => $input->isOutstanding());
        if (count($outstanding) > 1) {
            throw new InvalidArgumentException('A Run cannot have more than one outstanding human-input question.');
        }
        if ($outstanding !== [] && $status !== RunStatus::NeedsInput) {
            throw new InvalidArgumentException('An outstanding human-input question requires needs_input Run state.');
        }
    }

    public static function create(RunId $runId, DateTimeImmutable $createdAt, ?ExecutionStateDetails $details = null): self
    {
        return new self($runId, RunStatus::Pending, $createdAt, details: $details);
    }

    public function scheduleAttempt(AttemptId $attemptId, DateTimeImmutable $now): self
    {
        if ($this->activeAttemptId?->value === $attemptId->value) {
            return $this;
        }
        if ($this->status->isTerminal()) {
            $this->reject(ExecutionStateRejectionReason::AlreadyTerminal, 'A terminal Run cannot create another Attempt.');
        }
        if ($this->activeAttemptId !== null) {
            $this->reject(ExecutionStateRejectionReason::InvalidTransition, 'The current Attempt must finish before another Attempt is created.');
        }

        $previous = $this->attempts === [] ? null : $this->attempts[array_key_last($this->attempts)]->id;
        $attempt = new ExecutionAttempt($attemptId, $this->runId, count($this->attempts) + 1, AttemptStatus::Ready, $now, $previous);
        $status = $this->status === RunStatus::Pending || $this->status === RunStatus::NeedsInput ? RunStatus::Preparing : $this->status;
        RunTransitionPolicy::assertAllowed($this->status, $status);

        return $this->copy(status: $status, scheduledAt: $now, activeAttemptId: $attemptId, attempts: [...$this->attempts, $attempt]);
    }

    public function acquireLease(AttemptId $attemptId, LeaseId $leaseId, ExecutionNodeRef $holder, LeaseToken $token, string $acquisitionId, DateTimeImmutable $now, int $ttlSeconds): self
    {
        if ($this->cancellation !== null) {
            $this->reject(ExecutionStateRejectionReason::CancellationPending, 'Cancellation intent prevents new lease authority.');
        }
        $attempt = $this->requireCurrentAttempt($attemptId);
        if ($existing = $attempt->leaseByAcquisition($acquisitionId)) {
            if ($existing->holder == $holder && hash_equals($existing->token->value, $token->value)) {
                return $this;
            }
            $this->reject(ExecutionStateRejectionReason::ForeignLease, 'An acquisition identity cannot be reused with different authority.');
        }
        if ($this->status->isTerminal() || $attempt->status->isTerminal()) {
            $this->reject(ExecutionStateRejectionReason::NotEligibleForLease, 'Terminal Runs and Attempts cannot be leased.');
        }
        if ($attempt->status !== AttemptStatus::Ready && $attempt->status !== AttemptStatus::Expired) {
            $this->reject(ExecutionStateRejectionReason::AlreadyLeased, 'The Attempt is not currently reclaimable.');
        }
        if ($attempt->activeLease()?->isActiveAt($now)) {
            $this->reject(ExecutionStateRejectionReason::AlreadyLeased, 'The Attempt already has an active Lease.');
        }
        if ($ttlSeconds < 1 || trim($acquisitionId) === '') {
            throw new InvalidArgumentException('Acquisition identity and positive TTL are required.');
        }

        $lease = new ExecutionLease($leaseId, $attemptId, $holder, $token, $acquisitionId, LeaseStatus::Active, $now, $now->modify("+{$ttlSeconds} seconds"));
        $next = new ExecutionAttempt($attempt->id, $attempt->runId, $attempt->number, AttemptStatus::Leased, $attempt->createdAt, $attempt->previousAttemptId, [...$attempt->leases, $lease], $attempt->startedAt, $attempt->finishedAt, $attempt->failureReason);

        return $this->replaceAttempt($next);
    }

    public function start(AttemptId $attemptId, LeaseToken $token, DateTimeImmutable $now): self
    {
        $attempt = $this->requireCurrentAttempt($attemptId);
        $lease = $this->requireAuthorizedActiveLease($attempt, $token, $now);
        if ($attempt->status === AttemptStatus::Running) {
            return $this;
        }
        if ($attempt->status !== AttemptStatus::Leased) {
            $this->reject(ExecutionStateRejectionReason::InvalidTransition, 'Only a leased Attempt can start.');
        }
        RunTransitionPolicy::assertAllowed($this->status, RunStatus::Running);
        $next = new ExecutionAttempt($attempt->id, $attempt->runId, $attempt->number, AttemptStatus::Running, $attempt->createdAt, $attempt->previousAttemptId, $attempt->leases, $now);

        return $this->replaceAttempt($next)->copy(status: RunStatus::Running, startedAt: $this->startedAt ?? $now);
    }

    public function requestInput(HumanInputQuestion $question, ?LeaseToken $token = null): self
    {
        foreach ($this->humanInputs as $input) {
            if ($input->question->id !== $question->id) {
                continue;
            }
            if ($input->isOutstanding() && $input->question == $question) {
                $attempt = $this->findAttempt($question->attemptId);
                if ($attempt->leases !== []) {
                    if ($token === null) {
                        $this->reject(ExecutionStateRejectionReason::ForeignLease, 'Replaying a human-input request requires its original Lease token.');
                    }
                    $this->assertAttemptToken($attempt, $token);
                }
                return $this;
            }
            $this->reject(ExecutionStateRejectionReason::InputQuestionConflict, 'A human-input question identity cannot be reused or changed.');
        }
        if ($this->outstandingInput() !== null) {
            $this->reject(ExecutionStateRejectionReason::InputAlreadyPending, 'The Run already has an outstanding human-input question.');
        }
        if (! in_array($this->status, [RunStatus::Preparing, RunStatus::Running], true)) {
            $this->reject(ExecutionStateRejectionReason::InvalidTransition, 'Human input can only be requested while preparing or running.');
        }

        $attempt = $this->requireCurrentAttempt($question->attemptId);
        $leases = $attempt->leases;
        if ($lease = $attempt->activeLease()) {
            if ($token === null || ! hash_equals($lease->token->value, $token->value)) {
                $this->reject(ExecutionStateRejectionReason::ForeignLease, 'Pausing an active Attempt requires its Lease token.');
            }
            $released = $lease->release($lease->holder, $lease->token, 'input:'.$question->id, $question->requestedAt);
            $leases = array_map(static fn (ExecutionLease $item): ExecutionLease => $item->id->value === $released->id->value ? $released : $item, $leases);
        }
        if (! in_array($attempt->status, [AttemptStatus::Ready, AttemptStatus::Leased, AttemptStatus::Running], true)) {
            $this->reject(ExecutionStateRejectionReason::InvalidTransition, 'The current Attempt cannot pause for human input.');
        }

        RunTransitionPolicy::assertAllowed($this->status, RunStatus::NeedsInput);
        $waiting = new ExecutionAttempt($attempt->id, $attempt->runId, $attempt->number, AttemptStatus::NeedsInput, $attempt->createdAt, $attempt->previousAttemptId, $leases, $attempt->startedAt);

        return $this->replaceAttempt($waiting)->copy(
            status: RunStatus::NeedsInput,
            humanInputs: [...$this->humanInputs, HumanInputRecord::open($question)],
        );
    }

    public function recordInputDelivery(string $questionId, string $deliveryId, string $channel, DateTimeImmutable $deliveredAt): self
    {
        $input = null;
        foreach ($this->humanInputs as $candidate) {
            if ($candidate->question->id === $questionId) {
                $input = $candidate;
                break;
            }
        }
        if ($input === null) {
            $this->reject(ExecutionStateRejectionReason::InputNotPending, 'The human-input question does not exist.');
        }
        $delivered = $input->deliver($deliveryId, $channel, $deliveredAt);
        if (! $input->isOutstanding() && $delivered !== $input) {
            $this->reject(ExecutionStateRejectionReason::InputNotPending, 'A resolved human-input question cannot receive a new delivery.');
        }

        return $delivered === $input ? $this : $this->replaceHumanInput($delivered);
    }

    public function respondToInput(HumanInputResponse $response, HumanInputAuthorization $authorization): self
    {
        if (! $authorization->allowed) {
            $this->reject(ExecutionStateRejectionReason::InputResponseUnauthorized, $authorization->reason);
        }
        foreach ($this->humanInputs as $input) {
            if ($input->response?->id !== $response->id) {
                continue;
            }
            if ($input->response == $response) {
                return $this;
            }
            $this->reject(ExecutionStateRejectionReason::InputQuestionConflict, 'A human-input response identity cannot be reused with different evidence.');
        }

        $input = $this->requireOutstandingInput($response->questionId);
        if ($input->question->expiresAt !== null && $response->respondedAt >= $input->question->expiresAt) {
            $this->reject(ExecutionStateRejectionReason::InputExpired, 'The human-input question has expired.');
        }
        if (! in_array($response->value, $input->question->allowedResponses, true)) {
            $this->reject(ExecutionStateRejectionReason::InputResponseInvalid, 'The response does not match the question response shape.');
        }
        $attempt = $this->requireCurrentAttempt($input->question->attemptId);
        if ($attempt->status !== AttemptStatus::NeedsInput || $this->status !== RunStatus::NeedsInput) {
            $this->reject(ExecutionStateRejectionReason::InputNotPending, 'The Run is not waiting for this human-input response.');
        }

        RunTransitionPolicy::assertAllowed($this->status, RunStatus::Preparing);
        $ready = new ExecutionAttempt($attempt->id, $attempt->runId, $attempt->number, AttemptStatus::Ready, $attempt->createdAt, $attempt->previousAttemptId, $attempt->leases, $attempt->startedAt);

        return $this->replaceAttempt($ready)
            ->replaceHumanInput($input->answer($response))
            ->copy(status: RunStatus::Preparing);
    }

    public function timeoutInput(string $questionId, string $operationId, DateTimeImmutable $now): self
    {
        foreach ($this->humanInputs as $input) {
            foreach ($input->events as $event) {
                if ($event->operationId === $operationId && $event->type === HumanInputResolution::TimedOut->value) {
                    if ($input->question->id === $questionId) {
                        return $this;
                    }
                    $this->reject(ExecutionStateRejectionReason::InputQuestionConflict, 'A timeout identity cannot be reused for another question.');
                }
            }
        }
        $input = $this->requireOutstandingInput($questionId);
        if ($input->question->expiresAt === null || $now < $input->question->expiresAt) {
            $this->reject(ExecutionStateRejectionReason::InputNotExpired, 'The human-input question has not expired.');
        }
        $attempt = $this->requireCurrentAttempt($input->question->attemptId);
        RunTransitionPolicy::assertAllowed($this->status, RunStatus::TimedOut);
        $timedOut = new ExecutionAttempt($attempt->id, $attempt->runId, $attempt->number, AttemptStatus::TimedOut, $attempt->createdAt, $attempt->previousAttemptId, $attempt->leases, $attempt->startedAt, $now, 'human input timed out');

        return $this->replaceAttempt($timedOut)
            ->replaceHumanInput($input->close($operationId, HumanInputResolution::TimedOut, $now))
            ->copy(status: RunStatus::TimedOut, finishedAt: $now, activeAttemptId: null, failureReason: 'human input timed out');
    }

    public function renewLease(AttemptId $attemptId, ExecutionNodeRef $holder, LeaseToken $token, string $renewalId, DateTimeImmutable $now, int $ttlSeconds): self
    {
        if ($this->cancellation !== null) {
            $this->reject(ExecutionStateRejectionReason::CancellationPending, 'Cancellation intent prevents Lease renewal.');
        }
        $attempt = $this->requireCurrentAttempt($attemptId);
        $lease = $attempt->activeLease() ?? $this->reject(ExecutionStateRejectionReason::LeaseExpired, 'The Attempt has no active Lease.');
        $renewed = $lease->renew($holder, $token, $renewalId, $now, $ttlSeconds);
        if ($renewed === $lease) {
            return $this;
        }
        return $this->replaceLease($attempt, $renewed);
    }

    public function releaseLease(AttemptId $attemptId, ExecutionNodeRef $holder, LeaseToken $token, string $releaseId, DateTimeImmutable $now): self
    {
        $attempt = $this->requireCurrentAttempt($attemptId);
        $lease = $attempt->activeLease() ?? $this->findReleaseReplay($attempt, $releaseId);
        $released = $lease->release($holder, $token, $releaseId, $now);
        if ($released === $lease) {
            return $this;
        }
        $status = $attempt->status === AttemptStatus::Leased ? AttemptStatus::Ready : $attempt->status;
        return $this->replaceLease($attempt, $released, $status);
    }

    public function expireLease(AttemptId $attemptId, DateTimeImmutable $now): self
    {
        $attempt = $this->requireCurrentAttempt($attemptId);
        $lease = $attempt->activeLease();
        if ($lease === null) {
            if ($attempt->status === AttemptStatus::Expired) {
                return $this;
            }
            $this->reject(ExecutionStateRejectionReason::LeaseExpired, 'The Attempt has no active Lease.');
        }
        return $this->replaceLease($attempt, $lease->expire($now), AttemptStatus::Expired);
    }

    public function nextAttemptAfterExpiry(AttemptId $attemptId, AttemptId $nextAttemptId, DateTimeImmutable $now): self
    {
        $attempt = $this->requireCurrentAttempt($attemptId);
        if ($attempt->status !== AttemptStatus::Expired || $this->status->isTerminal()) {
            $this->reject(ExecutionStateRejectionReason::NotEligibleForLease, 'Only an expired Attempt on a non-terminal Run can be followed by another Attempt.');
        }
        $next = new ExecutionAttempt($nextAttemptId, $this->runId, $attempt->number + 1, AttemptStatus::Ready, $now, $attempt->id);

        return $this->copy(activeAttemptId: $nextAttemptId, attempts: [...$this->attempts, $next], scheduledAt: $now);
    }

    public function observeFailure(AttemptId $attemptId, LeaseToken $token, FailureClassification $classification, string $operationId, string $reason, DateTimeImmutable $now, RetryPolicy $policy): self
    {
        if ($this->recovery?->operationId === $operationId) {
            $this->assertAttemptToken($this->findAttempt($attemptId), $token);
            if ($this->recovery->attemptId->value === $attemptId->value && $this->recovery->classification === $classification && $this->recovery->reason === $reason) {
                return $this;
            }
            $this->reject(ExecutionStateRejectionReason::InvalidTransition, 'A recovery operation identity cannot be reused with different evidence.');
        }
        if ($this->status->isTerminal()) {
            $this->reject(ExecutionStateRejectionReason::AlreadyTerminal, 'A terminal Run cannot enter recovery.');
        }
        if ($this->cancellation !== null) {
            $this->reject(ExecutionStateRejectionReason::CancellationPending, 'Recovery cannot replace accepted cancellation intent.');
        }
        $attempt = $this->requireCurrentAttempt($attemptId);
        $lease = $this->requireAuthorizedActiveLease($attempt, $token, $now);
        $action = $policy->decide($this, $classification);
        $recovery = new RecoveryRecord($operationId, $attemptId, $classification, $action, $reason, $now);

        if ($classification === FailureClassification::AcknowledgementUncertain) {
            RunTransitionPolicy::assertAllowed($this->status, RunStatus::Reconciling);
            $next = new ExecutionAttempt($attempt->id, $attempt->runId, $attempt->number, AttemptStatus::ReconciliationRequired, $attempt->createdAt, $attempt->previousAttemptId, $attempt->leases, $attempt->startedAt, failureReason: $reason);
            return $this->replaceAttempt($next)->copy(status: RunStatus::Reconciling, recovery: $recovery);
        }

        $released = $lease->release($lease->holder, $lease->token, 'recovery:'.$operationId, $now);
        $attemptStatus = $action === RecoveryAction::Fail ? AttemptStatus::Failed : AttemptStatus::ReconciliationRequired;
        $next = $this->replaceLeaseValue($attempt, $released, $attemptStatus, $now, $reason);
        if ($action === RecoveryAction::Fail) {
            RunTransitionPolicy::assertAllowed($this->status, RunStatus::Failed);
            return $this->replaceAttempt($next)->copy(status: RunStatus::Failed, finishedAt: $now, activeAttemptId: null, failureReason: $reason, recovery: $recovery);
        }
        RunTransitionPolicy::assertAllowed($this->status, RunStatus::Reconciling);
        return $this->replaceAttempt($next)->copy(status: RunStatus::Reconciling, activeAttemptId: null, failureReason: $reason, recovery: $recovery);
    }

    public function scheduleRetry(AttemptId $nextAttemptId, DateTimeImmutable $now): self
    {
        foreach ($this->attempts as $attempt) {
            if ($attempt->id->value === $nextAttemptId->value && $attempt->previousAttemptId?->value === $this->recovery?->attemptId->value) {
                return $this;
            }
        }
        if ($this->status->isTerminal()) {
            $this->reject(ExecutionStateRejectionReason::AlreadyTerminal, 'A terminal Run cannot retry.');
        }
        if ($this->recovery === null || $this->recovery->action !== RecoveryAction::Retry || $this->status !== RunStatus::Reconciling) {
            $this->reject(ExecutionStateRejectionReason::ReconciliationRequired, 'Retry requires a retryable recovery decision.');
        }
        if ($this->activeAttemptId !== null) {
            $this->reject(ExecutionStateRejectionReason::InvalidTransition, 'An active Attempt prevents retry scheduling.');
        }
        $previous = $this->findAttempt($this->recovery->attemptId);
        $next = new ExecutionAttempt($nextAttemptId, $this->runId, $previous->number + 1, AttemptStatus::Ready, $now, $previous->id);
        RunTransitionPolicy::assertAllowed($this->status, RunStatus::Preparing);
        return $this->copy(status: RunStatus::Preparing, scheduledAt: $now, activeAttemptId: $nextAttemptId, attempts: [...$this->attempts, $next]);
    }

    public function confirmAcknowledgement(AttemptId $attemptId, LeaseToken $token, DateTimeImmutable $now): self
    {
        $candidate = $this->currentAttempt();
        if ($this->recovery?->action === RecoveryAction::Reconcile && $candidate?->id->value === $attemptId->value && $candidate->status === AttemptStatus::Running) {
            $this->assertAttemptToken($candidate, $token);
            return $this;
        }
        if ($this->recovery === null || $this->recovery->action !== RecoveryAction::Reconcile || $this->status !== RunStatus::Reconciling) {
            $this->reject(ExecutionStateRejectionReason::InvalidTransition, 'The Run is not awaiting acknowledgement reconciliation.');
        }
        $attempt = $this->requireCurrentAttempt($attemptId);
        $this->requireAuthorizedActiveLease($attempt, $token, $now);
        $next = new ExecutionAttempt($attempt->id, $attempt->runId, $attempt->number, AttemptStatus::Running, $attempt->createdAt, $attempt->previousAttemptId, $attempt->leases, $attempt->startedAt ?? $now);
        RunTransitionPolicy::assertAllowed($this->status, RunStatus::Running);
        return $this->replaceAttempt($next)->copy(status: RunStatus::Running, startedAt: $this->startedAt ?? $now);
    }

    public function requestCancellation(string $operationId, CancellationKind $kind, string $requestedBy, string $reason, CancellationAuthorization $authorization, DateTimeImmutable $now): self
    {
        if (! $authorization->allowed) {
            $this->reject(ExecutionStateRejectionReason::CancellationUnauthorized, $authorization->reason);
        }
        if ($this->cancellation?->operationId === $operationId) {
            if ($this->cancellation->kind === $kind && $this->cancellation->requestedBy === $requestedBy && $this->cancellation->reason === $reason) {
                return $this;
            }
            $this->reject(ExecutionStateRejectionReason::CancellationConflict, 'A cancellation identity cannot be reused with different intent.');
        }
        if ($this->status->isTerminal()) {
            $this->reject(ExecutionStateRejectionReason::AlreadyTerminal, 'A terminal Run cannot accept cancellation intent.');
        }
        if ($this->cancellation !== null) {
            $this->reject(ExecutionStateRejectionReason::CancellationConflict, 'The Run already has cancellation intent.');
        }
        $intent = new CancellationIntent($operationId, $kind, $requestedBy, $reason, CancellationStatus::Requested, $now);

        if ($this->status === RunStatus::Pending || $this->status === RunStatus::NeedsInput || $this->activeAttemptId === null || ($this->status === RunStatus::Preparing && $this->startedAt === null)) {
            return $this->cancelBeforeDispatch($intent, $now);
        }

        return $this->copy(cancellation: $intent);
    }

    public function confirmCancellation(AttemptId $attemptId, LeaseToken $token, string $operationId, DateTimeImmutable $now, ?string $partialResultReference = null): self
    {
        if ($this->cancellation === null || $this->cancellation->operationId !== $operationId) {
            $this->reject(ExecutionStateRejectionReason::CancellationConflict, 'Cancellation confirmation does not match accepted intent.');
        }
        if ($this->cancellation->status === CancellationStatus::Confirmed) {
            $this->assertAttemptToken($this->findAttempt($attemptId), $token);
            return $this;
        }
        $attempt = $this->requireCurrentAttempt($attemptId);
        $lease = $attempt->activeLease() ?? $this->reject(ExecutionStateRejectionReason::LeaseExpired, 'Cancellation confirmation requires the active Lease.');
        if (! hash_equals($lease->token->value, $token->value)) {
            $this->reject(ExecutionStateRejectionReason::ForeignLease, 'Cancellation confirmation has a foreign Lease token.');
        }
        $released = $lease->release($lease->holder, $lease->token, 'cancel:'.$operationId, $now);
        $runStatus = $this->cancellation->kind === CancellationKind::Timeout ? RunStatus::TimedOut : RunStatus::Cancelled;
        $attemptStatus = $this->cancellation->kind === CancellationKind::Timeout ? AttemptStatus::TimedOut : AttemptStatus::Cancelled;
        RunTransitionPolicy::assertAllowed($this->status, $runStatus);
        $next = $this->replaceLeaseValue($attempt, $released, $attemptStatus, $now, $this->cancellation->reason);
        return $this->replaceAttempt($next)->copy(status: $runStatus, finishedAt: $now, activeAttemptId: null, failureReason: $this->cancellation->reason, terminalResultReference: $partialResultReference, cancellation: $this->cancellation->confirm($now, $partialResultReference));
    }

    public function finish(AttemptId $attemptId, LeaseToken $token, RunStatus $terminalStatus, DateTimeImmutable $now, ?string $reason = null, ?string $resultReference = null): self
    {
        if (! $terminalStatus->isTerminal()) {
            $this->reject(ExecutionStateRejectionReason::InvalidTransition, 'A finishing status must be terminal.');
        }
        if ($this->status->isTerminal()) {
            if ($this->status === $terminalStatus && $this->failureReason === $reason && $this->terminalResultReference === $resultReference) {
                $accepted = $this->attempts === [] ? null : $this->attempts[array_key_last($this->attempts)];
                if ($accepted?->id->value === $attemptId->value) {
                    $this->assertAttemptToken($accepted, $token);
                    return $this;
                }
            }
            $this->reject(ExecutionStateRejectionReason::AlreadyTerminal, 'A terminal Run cannot be reopened or replaced.');
        }
        $attempt = $this->requireCurrentAttempt($attemptId);
        $lease = $this->requireAuthorizedActiveLease($attempt, $token, $now);
        RunTransitionPolicy::assertAllowed($this->status, $terminalStatus);
        $released = $lease->release($lease->holder, $lease->token, 'terminal:'.$terminalStatus->value, $now);
        $attemptStatus = $terminalStatus === RunStatus::Succeeded ? AttemptStatus::Succeeded : AttemptStatus::Failed;
        $nextAttempt = $this->replaceLeaseValue($attempt, $released, $attemptStatus, $now, $reason);

        return $this->replaceAttempt($nextAttempt)->copy(status: $terminalStatus, finishedAt: $now, activeAttemptId: null, failureReason: $reason, terminalResultReference: $resultReference);
    }

    public function currentAttempt(): ?ExecutionAttempt
    {
        if ($this->activeAttemptId === null) {
            return null;
        }
        foreach ($this->attempts as $attempt) {
            if ($attempt->id->value === $this->activeAttemptId->value) {
                return $attempt;
            }
        }
        return null;
    }

    public function recordApproval(string $approvedByUserId, DateTimeImmutable $approvedAt): self
    {
        if ($this->details === null) {
            throw new InvalidArgumentException('Approval details require an execution details record.');
        }
        $details = $this->details->approved($approvedByUserId, $approvedAt);
        return $details === $this->details ? $this : $this->copy(details: $details);
    }

    /** @param array<string, int|float|string|bool|null>|null $diffStats */
    public function recordExecutionResult(?int $pullRequestNumber, ?string $pullRequestUrl, ?array $diffStats, ?string $outputLogPath, DateTimeImmutable $recordedAt): self
    {
        if ($this->details === null) {
            throw new InvalidArgumentException('Result details require an execution details record.');
        }
        return $this->copy(details: $this->details->result($pullRequestNumber, $pullRequestUrl, $diffStats, $outputLogPath, $recordedAt));
    }

    private function requireCurrentAttempt(AttemptId $id): ExecutionAttempt
    {
        $attempt = $this->currentAttempt();
        if ($attempt === null || $attempt->id->value !== $id->value) {
            $this->reject(ExecutionStateRejectionReason::StaleAttempt, 'The command references a stale or foreign Attempt.');
        }
        return $attempt;
    }

    private function findAttempt(AttemptId $id): ExecutionAttempt
    {
        foreach ($this->attempts as $attempt) {
            if ($attempt->id->value === $id->value) {
                return $attempt;
            }
        }
        return $this->reject(ExecutionStateRejectionReason::StaleAttempt, 'The recovery Attempt is missing.');
    }

    private function assertAttemptToken(ExecutionAttempt $attempt, LeaseToken $token): void
    {
        $lease = $attempt->leases === [] ? null : $attempt->leases[array_key_last($attempt->leases)];
        if ($lease === null || ! hash_equals($lease->token->value, $token->value)) {
            $this->reject(ExecutionStateRejectionReason::ForeignLease, 'The operation replay has a foreign Lease token.');
        }
    }

    private function cancelBeforeDispatch(CancellationIntent $intent, DateTimeImmutable $now): self
    {
        $runStatus = $intent->kind === CancellationKind::Timeout ? RunStatus::TimedOut : RunStatus::Cancelled;
        $attempts = $this->attempts;
        if ($attempt = $this->currentAttempt()) {
            $leases = $attempt->leases;
            if ($lease = $attempt->activeLease()) {
                $released = $lease->release($lease->holder, $lease->token, 'cancel:'.$intent->operationId, $now);
                $leases = array_map(static fn (ExecutionLease $item): ExecutionLease => $item->id->value === $released->id->value ? $released : $item, $leases);
            }
            $attemptStatus = $intent->kind === CancellationKind::Timeout ? AttemptStatus::TimedOut : AttemptStatus::Cancelled;
            $replacement = new ExecutionAttempt($attempt->id, $attempt->runId, $attempt->number, $attemptStatus, $attempt->createdAt, $attempt->previousAttemptId, $leases, $attempt->startedAt, $now, $intent->reason);
            $attempts = array_map(static fn (ExecutionAttempt $item): ExecutionAttempt => $item->id->value === $replacement->id->value ? $replacement : $item, $attempts);
        }
        $humanInputs = $this->humanInputs;
        if ($input = $this->outstandingInput()) {
            $closed = $input->close($intent->operationId, HumanInputResolution::Cancelled, $now, $intent->requestedBy);
            $humanInputs = array_map(static fn (HumanInputRecord $item): HumanInputRecord => $item->question->id === $closed->question->id ? $closed : $item, $humanInputs);
        }
        RunTransitionPolicy::assertAllowed($this->status, $runStatus);
        return $this->copy(status: $runStatus, finishedAt: $now, activeAttemptId: null, attempts: $attempts, failureReason: $intent->reason, cancellation: $intent->confirm($now, null), humanInputs: $humanInputs);
    }

    private function outstandingInput(): ?HumanInputRecord
    {
        foreach (array_reverse($this->humanInputs) as $input) {
            if ($input->isOutstanding()) {
                return $input;
            }
        }
        return null;
    }

    private function requireOutstandingInput(string $questionId): HumanInputRecord
    {
        $input = $this->outstandingInput();
        if ($input === null || $input->question->id !== $questionId) {
            return $this->reject(ExecutionStateRejectionReason::InputNotPending, 'The Run is not waiting for this human-input question.');
        }
        return $input;
    }

    private function replaceHumanInput(HumanInputRecord $replacement): self
    {
        $humanInputs = array_map(
            static fn (HumanInputRecord $input): HumanInputRecord => $input->question->id === $replacement->question->id ? $replacement : $input,
            $this->humanInputs,
        );
        return $this->copy(humanInputs: $humanInputs);
    }

    private function requireAuthorizedActiveLease(ExecutionAttempt $attempt, LeaseToken $token, DateTimeImmutable $now): ExecutionLease
    {
        $lease = $attempt->activeLease() ?? $this->reject(ExecutionStateRejectionReason::LeaseExpired, 'The Attempt has no active Lease.');
        if (! hash_equals($lease->token->value, $token->value)) {
            $this->reject(ExecutionStateRejectionReason::ForeignLease, 'The Lease token is foreign or stale.');
        }
        if (! $lease->isActiveAt($now)) {
            $this->reject(ExecutionStateRejectionReason::LeaseExpired, 'The Lease has expired.');
        }
        return $lease;
    }

    private function findReleaseReplay(ExecutionAttempt $attempt, string $releaseId): ExecutionLease
    {
        foreach (array_reverse($attempt->leases) as $lease) {
            if ($lease->releaseId === $releaseId) {
                return $lease;
            }
        }
        return $this->reject(ExecutionStateRejectionReason::LeaseExpired, 'The Attempt has no active Lease.');
    }

    private function replaceLease(ExecutionAttempt $attempt, ExecutionLease $replacement, ?AttemptStatus $status = null): self
    {
        $leases = array_map(static fn (ExecutionLease $lease): ExecutionLease => $lease->id->value === $replacement->id->value ? $replacement : $lease, $attempt->leases);
        return $this->replaceAttempt(new ExecutionAttempt($attempt->id, $attempt->runId, $attempt->number, $status ?? $attempt->status, $attempt->createdAt, $attempt->previousAttemptId, $leases, $attempt->startedAt, $attempt->finishedAt, $attempt->failureReason));
    }

    private function replaceLeaseValue(ExecutionAttempt $attempt, ExecutionLease $replacement, AttemptStatus $status, DateTimeImmutable $finishedAt, ?string $reason): ExecutionAttempt
    {
        $leases = array_map(static fn (ExecutionLease $lease): ExecutionLease => $lease->id->value === $replacement->id->value ? $replacement : $lease, $attempt->leases);
        return new ExecutionAttempt($attempt->id, $attempt->runId, $attempt->number, $status, $attempt->createdAt, $attempt->previousAttemptId, $leases, $attempt->startedAt, $finishedAt, $reason);
    }

    private function replaceAttempt(ExecutionAttempt $replacement): self
    {
        $attempts = array_map(static fn (ExecutionAttempt $attempt): ExecutionAttempt => $attempt->id->value === $replacement->id->value ? $replacement : $attempt, $this->attempts);
        return $this->copy(attempts: $attempts);
    }

    /** @param list<ExecutionAttempt>|null $attempts */
    private function copy(?RunStatus $status = null, ?DateTimeImmutable $scheduledAt = null, ?DateTimeImmutable $startedAt = null, ?DateTimeImmutable $finishedAt = null, AttemptId|false|null $activeAttemptId = false, ?array $attempts = null, ?string $failureReason = null, ?string $terminalResultReference = null, ?ExecutionStateDetails $details = null, ?RecoveryRecord $recovery = null, ?CancellationIntent $cancellation = null, ?array $humanInputs = null): self
    {
        return new self($this->runId, $status ?? $this->status, $this->createdAt, $scheduledAt ?? $this->scheduledAt, $startedAt ?? $this->startedAt, $finishedAt ?? $this->finishedAt, $activeAttemptId === false ? $this->activeAttemptId : $activeAttemptId, $attempts ?? $this->attempts, $failureReason ?? $this->failureReason, $terminalResultReference ?? $this->terminalResultReference, $this->version + 1, $details ?? $this->details, $recovery ?? $this->recovery, $cancellation ?? $this->cancellation, $humanInputs ?? $this->humanInputs);
    }

    /** @return never */
    private function reject(ExecutionStateRejectionReason $reason, string $message): never
    {
        throw ExecutionStateRejected::because($reason, $message);
    }
}
