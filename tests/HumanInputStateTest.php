<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sifrious\Elwin\Clarification\AllowedResponseShape;
use Sifrious\Elwin\Clarification\ClarificationQuestion;
use Sifrious\Elwin\Clarification\ClarificationResponse;
use Sifrious\Elwin\Handoff\HandoffPayload;
use Sifrious\Elwin\Handoff\ResumableHandoff;
use Sifrious\Elwin\Handoff\ResumeContext;
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
use Sifrious\Logres\LeaseId;
use Sifrious\Logres\LeaseStatus;
use Sifrious\Logres\LeaseToken;
use Sifrious\Logres\NeedsInputPauseStatus;
use Sifrious\Logres\RunId;
use Sifrious\Logres\RunStatus;
use Sifrious\Logres\Tests\Fixtures\InMemoryExecutionStateStore;
use Sifrious\ReferenceContract\CrossPackageReference;

final class HumanInputStateTest extends TestCase
{
    #[Test]
    public function pause_is_durable_idempotent_and_references_only_elwin_handoff_context(): void
    {
        $handoff = $this->handoff();
        $paused = $this->running()->pauseForInput($handoff, new AttemptId('attempt:1'), new LeaseToken('secret:1'));
        self::assertSame(RunStatus::NeedsInput, $paused->status);
        self::assertSame(AttemptStatus::NeedsInput, $paused->currentAttempt()->status);
        self::assertSame(LeaseStatus::Released, $paused->currentAttempt()->leases[0]->status);
        self::assertEquals($paused, $paused->pauseForInput($handoff, new AttemptId('attempt:1'), new LeaseToken('secret:1')));

        $store = new InMemoryExecutionStateStore();
        $store->create($paused);
        $reloaded = $store->find($paused->runId);
        $read = ExecutionStateReadModel::fromState($reloaded);
        $pause = $read->needsInputPauses[0];

        self::assertSame($handoff->reference()->toArray(), $pause['handoff']);
        self::assertSame($handoff->resumeContext->jsonSerialize(), $pause['resume_context']);
        self::assertArrayNotHasKey('question', $pause);
        self::assertArrayNotHasKey('payload', $pause);
    }

    #[Test]
    public function exactly_one_stable_elwin_handoff_can_be_outstanding(): void
    {
        $paused = $this->running()->pauseForInput($this->handoff(), new AttemptId('attempt:1'), new LeaseToken('secret:1'));

        $this->assertRejected(
            ExecutionStateRejectionReason::InputAlreadyPending,
            fn () => $paused->pauseForInput($this->handoff('handoff:2'), new AttemptId('attempt:1'), new LeaseToken('secret:1')),
        );
        $this->assertRejected(
            ExecutionStateRejectionReason::InputQuestionConflict,
            fn () => $paused->pauseForInput($this->handoff(resumeToken: 'changed'), new AttemptId('attempt:1'), new LeaseToken('secret:1')),
        );
    }

    #[Test]
    public function elwin_acceptance_resumes_the_same_attempt_and_authorization_is_required(): void
    {
        $question = new ClarificationQuestion(
            'question:1',
            $this->ref('sifrious/elwin', 'conversation', 'conversation:1'),
            'Transport selection is required.',
            'Approve transport?',
            AllowedResponseShape::confirmation(),
            $this->at(3),
        );
        $response = ClarificationResponse::confirmation('response:1', $question->id, true, $this->at(4));
        self::assertTrue($question->accepts($response));

        $waitingHandoff = $this->handoff();
        $paused = $this->running()->pauseForInput($waitingHandoff, new AttemptId('attempt:1'), new LeaseToken('secret:1'));
        $answeredHandoff = $waitingHandoff->answer(
            $this->ref('sifrious/elwin', 'response', $response->id),
            $response->recordedAt,
        );

        $this->assertRejected(
            ExecutionStateRejectionReason::InputResponseUnauthorized,
            fn () => $paused->resumeFromInput($answeredHandoff, 'resume:1', HumanInputAuthorization::deny('actor_not_allowed'), $this->at(5)),
        );
        $resumed = $paused->resumeFromInput($answeredHandoff, 'resume:1', HumanInputAuthorization::allow(), $this->at(5));

        self::assertSame(RunStatus::Preparing, $resumed->status);
        self::assertSame('attempt:1', $resumed->currentAttempt()->id->value);
        self::assertSame(1, $resumed->currentAttempt()->number);
        self::assertNull($resumed->currentAttempt()->previousAttemptId);
        self::assertSame(NeedsInputPauseStatus::Resumed, $resumed->needsInputPauses[0]->status);
        self::assertEquals($resumed, $resumed->resumeFromInput($answeredHandoff, 'resume:1', HumanInputAuthorization::allow(), $this->at(6)));
    }

    #[Test]
    public function elwin_expiry_and_logres_cancellation_close_the_existing_pause(): void
    {
        $handoff = $this->handoff();
        $paused = $this->running()->pauseForInput($handoff, new AttemptId('attempt:1'), new LeaseToken('secret:1'));
        $expiredHandoff = $handoff->expire($this->at(63));
        $timedOut = $paused->timeoutInput($expiredHandoff, 'timeout:1', $this->at(63));

        self::assertSame(RunStatus::TimedOut, $timedOut->status);
        self::assertSame(NeedsInputPauseStatus::TimedOut, $timedOut->needsInputPauses[0]->status);
        self::assertEquals($timedOut, $timedOut->timeoutInput($expiredHandoff, 'timeout:1', $this->at(64)));

        $cancelled = $this->running()
            ->pauseForInput($this->handoff(), new AttemptId('attempt:1'), new LeaseToken('secret:1'))
            ->requestCancellation('cancel:1', CancellationKind::Manual, 'user:1', 'stop', CancellationAuthorization::allow(), $this->at(5));
        self::assertSame(RunStatus::Cancelled, $cancelled->status);
        self::assertSame(NeedsInputPauseStatus::Cancelled, $cancelled->needsInputPauses[0]->status);
    }

    private function running(): ExecutionState
    {
        return ExecutionState::create(new RunId('run:1'), $this->at(0))
            ->scheduleAttempt(new AttemptId('attempt:1'), $this->at(0))
            ->acquireLease(new AttemptId('attempt:1'), new LeaseId('lease:1'), new ExecutionNodeRef('node:1'), new LeaseToken('secret:1'), 'acquire:1', $this->at(1), 120)
            ->start(new AttemptId('attempt:1'), new LeaseToken('secret:1'), $this->at(2));
    }

    private function handoff(string $id = 'handoff:1', string $resumeToken = 'resume-secret'): ResumableHandoff
    {
        return new ResumableHandoff(
            $id,
            $this->ref('sifrious/elwin', 'conversation', 'conversation:1'),
            $this->ref('sifrious/logres', 'run', 'run:1'),
            $this->ref('sifrious/elwin', 'question', 'question:1'),
            new ResumeContext($resumeToken, $this->ref('sifrious/logres', 'turn-checkpoint', 'checkpoint:1')),
            new HandoffPayload('sifrious.elwin.intervention-context/v1', ['summary' => 'Presentation remains Elwin-owned.']),
            $this->at(3),
            $this->at(63),
        );
    }

    private function ref(string $owner, string $type, string $id): CrossPackageReference
    {
        return new CrossPackageReference($owner, $type, $id);
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
