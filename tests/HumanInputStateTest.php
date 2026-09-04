<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sifrious\Logres\AttemptId;
use Sifrious\Logres\AttemptStatus;
use Sifrious\Logres\CancellationAuthorization;
use Sifrious\Logres\CancellationKind;
use Sifrious\Logres\ExecutionNodeRef;
use Sifrious\Logres\ExecutionState;
use Sifrious\Logres\ExecutionStateReadModel;
use Sifrious\Logres\ExecutionStateRejected;
use Sifrious\Logres\ExecutionStateRejectionReason;
use Sifrious\Logres\HumanInputAuthorization;
use Sifrious\Logres\HumanInputQuestion;
use Sifrious\Logres\HumanInputResponse;
use Sifrious\Logres\LeaseId;
use Sifrious\Logres\LeaseStatus;
use Sifrious\Logres\LeaseToken;
use Sifrious\Logres\RunId;
use Sifrious\Logres\RunStatus;
use Sifrious\Logres\Tests\Fixtures\InMemoryExecutionStateStore;

final class HumanInputStateTest extends TestCase
{
    #[Test]
    public function a_question_is_durable_redeliverable_and_survives_browser_disconnect(): void
    {
        $paused = $this->running()->requestInput($this->question(), new LeaseToken('secret:1'));
        self::assertSame(RunStatus::NeedsInput, $paused->status);
        self::assertSame(AttemptStatus::NeedsInput, $paused->currentAttempt()->status);
        self::assertSame(LeaseStatus::Released, $paused->currentAttempt()->leases[0]->status);

        $delivered = $paused
            ->recordInputDelivery('question:1', 'delivery:browser:1', 'browser', $this->at(4))
            ->recordInputDelivery('question:1', 'delivery:browser:2', 'browser', $this->at(5));
        self::assertEquals($delivered, $delivered->recordInputDelivery('question:1', 'delivery:browser:2', 'browser', $this->at(6)));

        $store = new InMemoryExecutionStateStore();
        $store->create($delivered);
        $reloaded = $store->find($delivered->runId);
        $read = ExecutionStateReadModel::fromState($reloaded);

        self::assertSame('question:1', $read->humanInputs[0]['question_id']);
        self::assertSame(['type' => 'string', 'enum' => ['approve', 'reject']], $read->humanInputs[0]['response_shape']);
        self::assertNull($read->humanInputs[0]['resolution']);
        self::assertSame(['requested', 'delivered', 'delivered'], array_column($read->humanInputs[0]['audit'], 'type'));
    }

    #[Test]
    public function only_one_stable_question_can_be_outstanding(): void
    {
        $paused = $this->running()->requestInput($this->question(), new LeaseToken('secret:1'));
        self::assertEquals($paused, $paused->requestInput($this->question(), new LeaseToken('secret:1')));

        $this->assertRejected(
            ExecutionStateRejectionReason::InputQuestionConflict,
            fn () => $paused->requestInput($this->question(prompt: 'Changed prompt'), new LeaseToken('secret:1')),
        );
        $this->assertRejected(
            ExecutionStateRejectionReason::InputAlreadyPending,
            fn () => $paused->requestInput($this->question(id: 'question:2'), new LeaseToken('secret:1')),
        );
    }

    #[Test]
    public function an_authorized_valid_response_resumes_the_same_step_and_attempt_lineage(): void
    {
        $paused = $this->running()->requestInput($this->question(), new LeaseToken('secret:1'));
        $response = new HumanInputResponse('response:1', 'question:1', 'user:approver', 'approve', $this->at(4));

        $this->assertRejected(
            ExecutionStateRejectionReason::InputResponseUnauthorized,
            fn () => $paused->respondToInput($response, HumanInputAuthorization::deny('actor_not_allowed')),
        );
        $this->assertRejected(
            ExecutionStateRejectionReason::InputResponseInvalid,
            fn () => $paused->respondToInput(new HumanInputResponse('response:bad', 'question:1', 'user:approver', 'maybe', $this->at(4)), HumanInputAuthorization::allow()),
        );

        $resumed = $paused->respondToInput($response, HumanInputAuthorization::allow('approval_grant'));
        self::assertSame(RunStatus::Preparing, $resumed->status);
        self::assertSame('attempt:1', $resumed->currentAttempt()->id->value);
        self::assertSame(1, $resumed->currentAttempt()->number);
        self::assertNull($resumed->currentAttempt()->previousAttemptId);
        self::assertSame('step:verify', $resumed->humanInputs[0]->question->stepId);
        self::assertEquals($resumed, $resumed->respondToInput($response, HumanInputAuthorization::allow()));
    }

    #[Test]
    public function expiry_is_terminal_idempotent_and_rejects_late_response(): void
    {
        $paused = $this->running()->requestInput($this->question(expiresAt: $this->at(10)), new LeaseToken('secret:1'));
        $this->assertRejected(
            ExecutionStateRejectionReason::InputNotExpired,
            fn () => $paused->timeoutInput('question:1', 'timeout:1', $this->at(9)),
        );
        $this->assertRejected(
            ExecutionStateRejectionReason::InputExpired,
            fn () => $paused->respondToInput(new HumanInputResponse('response:late', 'question:1', 'user:1', 'approve', $this->at(10)), HumanInputAuthorization::allow()),
        );

        $timedOut = $paused->timeoutInput('question:1', 'timeout:1', $this->at(10));
        self::assertSame(RunStatus::TimedOut, $timedOut->status);
        self::assertSame(AttemptStatus::TimedOut, $timedOut->attempts[0]->status);
        self::assertEquals($timedOut, $timedOut->timeoutInput('question:1', 'timeout:1', $this->at(11)));
        self::assertSame('timed_out', $timedOut->humanInputs[0]->resolution->value);
    }

    #[Test]
    public function cancellation_closes_the_question_and_preserves_its_audit_history(): void
    {
        $paused = $this->running()
            ->requestInput($this->question(), new LeaseToken('secret:1'))
            ->recordInputDelivery('question:1', 'delivery:1', 'browser', $this->at(4));

        $cancelled = $paused->requestCancellation(
            'cancel:1',
            CancellationKind::Manual,
            'user:operator',
            'no longer needed',
            CancellationAuthorization::allow(),
            $this->at(5),
        );

        self::assertSame(RunStatus::Cancelled, $cancelled->status);
        self::assertSame('cancelled', $cancelled->humanInputs[0]->resolution->value);
        self::assertSame(['requested', 'delivered', 'cancelled'], array_map(
            static fn ($event): string => $event->type,
            $cancelled->humanInputs[0]->events,
        ));
    }

    private function running(): ExecutionState
    {
        return ExecutionState::create(new RunId('run:1'), $this->at(0))
            ->scheduleAttempt(new AttemptId('attempt:1'), $this->at(0))
            ->acquireLease(new AttemptId('attempt:1'), new LeaseId('lease:1'), new ExecutionNodeRef('node:1'), new LeaseToken('secret:1'), 'acquire:1', $this->at(1), 60)
            ->start(new AttemptId('attempt:1'), new LeaseToken('secret:1'), $this->at(2));
    }

    private function question(
        string $id = 'question:1',
        string $prompt = 'Approve the observed result?',
        ?DateTimeImmutable $expiresAt = null,
    ): HumanInputQuestion {
        return new HumanInputQuestion(
            $id,
            new AttemptId('attempt:1'),
            'step:verify',
            $prompt,
            ['approve', 'reject'],
            $this->at(3),
            $expiresAt,
        );
    }

    private function at(int $seconds): DateTimeImmutable
    {
        return (new DateTimeImmutable('2026-09-04T12:00:00Z'))->modify("+{$seconds} seconds");
    }

    private function assertRejected(ExecutionStateRejectionReason $reason, callable $operation): void
    {
        try {
            $operation();
            self::fail('Expected an execution-state rejection.');
        } catch (ExecutionStateRejected $rejected) {
            self::assertSame($reason, $rejected->reason);
        }
    }
}
