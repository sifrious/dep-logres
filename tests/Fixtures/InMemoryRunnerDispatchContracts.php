<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests\Fixtures;

use InvalidArgumentException;
use Sifrious\Logres\RunnerLease;
use Sifrious\Logres\RunnerLeaseAcknowledgement;
use Sifrious\Logres\RunnerLeaseAcknowledgementResult;
use Sifrious\Logres\RunnerLeaseAcknowledgementStatus;
use Sifrious\Logres\RunnerLeaseAcknowledger;
use Sifrious\Logres\RunnerLocalRecord;
use Sifrious\Logres\RunnerLocalReservation;
use Sifrious\Logres\RunnerLocalStateStore;
use Sifrious\Logres\RunnerPollRequest;
use Sifrious\Logres\RunnerPollResponse;
use Sifrious\Logres\RunnerTerminalResult;
use Sifrious\Logres\RunnerTerminalResultDeliveryStatus;
use Sifrious\Logres\RunnerTerminalResultReceipt;
use Sifrious\Logres\RunnerTerminalResultSink;
use Sifrious\Logres\RunnerWorkPoller;
use Throwable;

final class InMemoryRunnerDispatchContracts implements RunnerWorkPoller, RunnerLeaseAcknowledger, RunnerTerminalResultSink, RunnerLocalStateStore
{
    /** @var array<string, RunnerLease> */
    private array $leases = [];

    /** @var array<string, RunnerLeaseAcknowledgement> */
    private array $acknowledgements = [];

    /** @var list<RunnerPollResponse> */
    private array $pollResponses = [];

    /** @var list<RunnerTerminalResultDeliveryStatus> */
    private array $reportStatuses = [];

    /** @var list<RunnerTerminalResult> */
    public array $reported = [];

    public int $acknowledgementCalls = 0;

    /** @var array<string, RunnerLocalRecord> */
    private array $records = [];

    /** @var array<string, string> */
    private array $idempotency = [];

    public function registerLease(RunnerLease $lease): void
    {
        $this->leases[$lease->id] = $lease;
    }

    public function enqueuePollResponse(RunnerPollResponse $response): void
    {
        $this->pollResponses[] = $response;
    }

    public function queueReportStatus(RunnerTerminalResultDeliveryStatus $status): void
    {
        $this->reportStatuses[] = $status;
    }

    public function poll(RunnerPollRequest $request): RunnerPollResponse
    {
        if (trim($request->authenticationMaterial) === '') {
            throw new InvalidArgumentException('Authentication material is required.');
        }

        return array_shift($this->pollResponses) ?? RunnerPollResponse::noWork(15);
    }

    public function acknowledge(RunnerLeaseAcknowledgement $acknowledgement): RunnerLeaseAcknowledgementResult
    {
        ++$this->acknowledgementCalls;
        $lease = $this->leases[$acknowledgement->leaseId] ?? null;
        if ($lease === null) {
            throw new InvalidArgumentException('Lease not found for acknowledgement.');
        }
        if ($lease->runnerId !== $acknowledgement->runnerId->value) {
            return new RunnerLeaseAcknowledgementResult(RunnerLeaseAcknowledgementStatus::Conflict, $lease, 'lease_runner_mismatch');
        }

        $existing = $this->acknowledgements[$acknowledgement->acknowledgementId] ?? null;
        if ($existing !== null) {
            if ($existing->leaseId === $acknowledgement->leaseId && $existing->runnerId->value === $acknowledgement->runnerId->value) {
                return new RunnerLeaseAcknowledgementResult(RunnerLeaseAcknowledgementStatus::Duplicate, $lease, 'duplicate_acknowledgement');
            }

            return new RunnerLeaseAcknowledgementResult(RunnerLeaseAcknowledgementStatus::Conflict, $lease, 'acknowledgement_identity_conflict');
        }

        try {
            $lease = $lease->acknowledge($acknowledgement->acknowledgedAt);
        } catch (Throwable $error) {
            return new RunnerLeaseAcknowledgementResult(RunnerLeaseAcknowledgementStatus::Rejected, $lease, $error->getMessage());
        }

        $this->leases[$lease->id] = $lease;
        $this->acknowledgements[$acknowledgement->acknowledgementId] = $acknowledgement;

        return new RunnerLeaseAcknowledgementResult(RunnerLeaseAcknowledgementStatus::Acknowledged, $lease);
    }

    public function report(RunnerTerminalResult $result): RunnerTerminalResultReceipt
    {
        $this->reported[] = $result;
        $status = array_shift($this->reportStatuses) ?? RunnerTerminalResultDeliveryStatus::Accepted;

        return new RunnerTerminalResultReceipt($status, $result);
    }

    public function find(string $executionKey): ?RunnerLocalRecord
    {
        return $this->records[$executionKey] ?? null;
    }

    public function reserve(RunnerLocalRecord $record): RunnerLocalReservation
    {
        $existingKey = $this->idempotency[$record->idempotencyIdentity] ?? null;
        $existing = $this->records[$record->executionKey] ?? ($existingKey === null ? null : $this->records[$existingKey]);
        if ($existing !== null) {
            return new RunnerLocalReservation(false, $existing);
        }

        $this->records[$record->executionKey] = $record;
        $this->idempotency[$record->idempotencyIdentity] = $record->executionKey;

        return new RunnerLocalReservation(true, $record);
    }

    public function save(RunnerLocalRecord $record): void
    {
        $this->records[$record->executionKey] = $record;
        $this->idempotency[$record->idempotencyIdentity] = $record->executionKey;
    }

    public function putReportingRecord(RunnerLocalRecord $record): void
    {
        if ($record->terminalResult === null) {
            throw new InvalidArgumentException('Reporting records require a terminal result.');
        }

        $this->save(new RunnerLocalRecord(
            $record->executionKey,
            $record->idempotencyIdentity,
            $record->envelopeFingerprint,
            \Sifrious\Logres\RunnerLocalStage::Reporting,
            $record->updatedAt,
            $record->terminalResult,
        ));
    }
}
