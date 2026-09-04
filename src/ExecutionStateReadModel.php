<?php

declare(strict_types=1);

namespace Sifrious\Logres;

final readonly class ExecutionStateReadModel
{
    public function __construct(
        public string $runId,
        public string $status,
        public string $createdAt,
        public ?string $scheduledAt,
        public ?string $startedAt,
        public ?string $finishedAt,
        public ?string $failureReason,
        public ?string $terminalResultReference,
        public ?array $currentAttempt,
        public array $attempts,
        public int $version,
        public ?array $details,
        public ?array $recovery,
        public ?array $cancellation,
        public array $humanInputs,
    ) {}

    public static function fromState(ExecutionState $state): self
    {
        $attempts = array_map(self::attempt(...), $state->attempts);
        $current = $state->currentAttempt();

        return new self(
            $state->runId->value,
            $state->status->value,
            $state->createdAt->format(DATE_ATOM),
            $state->scheduledAt?->format(DATE_ATOM),
            $state->startedAt?->format(DATE_ATOM),
            $state->finishedAt?->format(DATE_ATOM),
            $state->failureReason,
            $state->terminalResultReference,
            $current === null ? null : self::attempt($current),
            $attempts,
            $state->version,
            $state->details === null ? null : [
                'repo_id' => $state->details->workspaceId,
                'parent_task_id' => $state->details->parentTaskId,
                'title' => $state->details->title,
                'prompt' => $state->details->prompt,
                'base_branch' => $state->details->baseBranch,
                'branch_name' => $state->details->branchName,
                'worktree_path' => $state->details->worktreePath,
                'sqlite_path' => $state->details->sqlitePath,
                'pr_number' => $state->details->pullRequestNumber,
                'pr_url' => $state->details->pullRequestUrl,
                'diff_stats' => $state->details->diffStats,
                'output_log_path' => $state->details->outputLogPath,
                'error_message' => $state->failureReason,
                'created_by_user_id' => $state->details->createdByUserId,
                'approved_by_user_id' => $state->details->approvedByUserId,
                'approved_at' => $state->details->approvedAt?->format(DATE_ATOM),
                'runtime_invocation_id' => $state->details->runtimeInvocationId,
                'target_reference' => $state->details->targetReference,
                'updated_at' => $state->details->updatedAt?->format(DATE_ATOM),
            ],
            $state->recovery === null ? null : [
                'operation_id' => $state->recovery->operationId,
                'attempt_id' => $state->recovery->attemptId->value,
                'classification' => $state->recovery->classification->value,
                'action' => $state->recovery->action->value,
                'reason' => $state->recovery->reason,
                'observed_at' => $state->recovery->observedAt->format(DATE_ATOM),
            ],
            $state->cancellation === null ? null : [
                'operation_id' => $state->cancellation->operationId,
                'kind' => $state->cancellation->kind->value,
                'requested_by' => $state->cancellation->requestedBy,
                'reason' => $state->cancellation->reason,
                'status' => $state->cancellation->status->value,
                'requested_at' => $state->cancellation->requestedAt->format(DATE_ATOM),
                'confirmed_at' => $state->cancellation->confirmedAt?->format(DATE_ATOM),
                'partial_result_reference' => $state->cancellation->partialResultReference,
            ],
            array_map(static fn (HumanInputRecord $input): array => [
                'question_id' => $input->question->id,
                'attempt_id' => $input->question->attemptId->value,
                'step_id' => $input->question->stepId,
                'prompt' => $input->question->prompt,
                'response_shape' => $input->question->responseShape(),
                'requested_at' => $input->question->requestedAt->format(DATE_ATOM),
                'expires_at' => $input->question->expiresAt?->format(DATE_ATOM),
                'resolution' => $input->resolution?->value,
                'resolved_at' => $input->resolvedAt?->format(DATE_ATOM),
                'response' => $input->response === null ? null : [
                    'id' => $input->response->id,
                    'responder_id' => $input->response->responderId,
                    'value' => $input->response->value,
                    'responded_at' => $input->response->respondedAt->format(DATE_ATOM),
                ],
                'audit' => array_map(static fn (HumanInputEvent $event): array => [
                    'operation_id' => $event->operationId,
                    'type' => $event->type,
                    'occurred_at' => $event->occurredAt->format(DATE_ATOM),
                    'actor_id' => $event->actorId,
                    'channel' => $event->channel,
                ], $input->events),
            ], $state->humanInputs),
        );
    }

    private static function attempt(ExecutionAttempt $attempt): array
    {
        return [
            'id' => $attempt->id->value,
            'run_id' => $attempt->runId->value,
            'number' => $attempt->number,
            'status' => $attempt->status->value,
            'previous_attempt_id' => $attempt->previousAttemptId?->value,
            'created_at' => $attempt->createdAt->format(DATE_ATOM),
            'started_at' => $attempt->startedAt?->format(DATE_ATOM),
            'finished_at' => $attempt->finishedAt?->format(DATE_ATOM),
            'failure_reason' => $attempt->failureReason,
            'leases' => array_map(static fn (ExecutionLease $lease): array => [
                'id' => $lease->id->value,
                'attempt_id' => $lease->attemptId->value,
                'holder' => $lease->holder->value,
                'status' => $lease->status->value,
                'acquired_at' => $lease->acquiredAt->format(DATE_ATOM),
                'expires_at' => $lease->expiresAt->format(DATE_ATOM),
                'renewed_at' => $lease->renewedAt?->format(DATE_ATOM),
                'released_at' => $lease->releasedAt?->format(DATE_ATOM),
            ], $attempt->leases),
        ];
    }
}
