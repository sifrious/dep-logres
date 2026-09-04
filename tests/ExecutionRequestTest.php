<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sifrious\Logres\DeliveryChannel;
use Sifrious\Logres\ExecutionConstraints;
use Sifrious\Logres\ExecutionContext;
use Sifrious\Logres\ExecutionPermissions;
use Sifrious\Logres\ExecutionRequest;
use Sifrious\Logres\ExecutionRequestPersistenceFailure;
use Sifrious\Logres\ExecutionRequestId;
use Sifrious\Logres\ExecutionRequestReadModel;
use Sifrious\Logres\ExecutionRequestResultStatus;
use Sifrious\Logres\ExecutionRequestStore;
use Sifrious\Logres\ExecutionRequestSubmission;
use Sifrious\Logres\ExecutionRequestValidator;
use Sifrious\Logres\RequestRelationship;
use Sifrious\Logres\SubmitExecutionRequest;
use Sifrious\Logres\Tests\Fixtures\ExecutionRequestFixtures;

final class ExecutionRequestTest extends TestCase
{
    #[Test]
    public function accepted_fixture_preserves_prompt_bytes_and_identity(): void
    {
        $request = ExecutionRequestFixtures::accepted();
        $store = new InMemoryExecutionRequestStore;
        $result = $this->submission($store)->handle(new SubmitExecutionRequest($request));

        self::assertSame(ExecutionRequestResultStatus::Accepted, $result->status);
        self::assertSame('request:accepted-fixture', $result->requestId?->value);
        self::assertSame(" Preserve these bytes.\n", $store->find($request->id)?->originalPrompt);
    }

    #[Test]
    public function rejected_fixture_reports_each_unrepresentable_input(): void
    {
        $result = $this->submission(new InMemoryExecutionRequestStore)
            ->handle(new SubmitExecutionRequest(ExecutionRequestFixtures::rejected()));

        self::assertSame(ExecutionRequestResultStatus::Rejected, $result->status);
        self::assertSame(
            [
                'desired_result_required',
                'target_required',
                'timeout_invalid',
                'write_permission_required',
                'network_permission_required',
                'attachment_reference_unsupported',
                'attachment_name_required',
                'authorization_context_required',
            ],
            array_map(static fn ($failure): string => $failure->code, $result->failures),
        );
    }

    #[Test]
    public function corrections_and_children_create_lineage_without_mutating_the_parent(): void
    {
        $parent = ExecutionRequestFixtures::accepted();
        $correction = $this->relatedRequest('request:correction', RequestRelationship::Correction, $parent->id);
        $child = $this->relatedRequest('request:child', RequestRelationship::Child, $parent->id);
        $store = new InMemoryExecutionRequestStore;
        $submission = $this->submission($store);

        $submission->handle(new SubmitExecutionRequest($parent));
        $submission->handle(new SubmitExecutionRequest($correction));
        $submission->handle(new SubmitExecutionRequest($child));

        self::assertSame(" Preserve these bytes.\n", $store->find($parent->id)?->originalPrompt);
        self::assertSame($parent->id->value, $store->find($correction->id)?->parentRequestId?->value);
        self::assertSame($parent->id->value, $store->find($child->id)?->parentRequestId?->value);
        self::assertCount(3, $store->requests);
    }

    #[Test]
    public function missing_or_self_referencing_lineage_is_rejected(): void
    {
        $missingParent = $this->relatedRequest('request:missing-parent', RequestRelationship::Correction, null);
        $selfParent = $this->relatedRequest('request:self-parent', RequestRelationship::Child, new ExecutionRequestId('request:self-parent'));

        self::assertSame(
            ['lineage_parent_required'],
            array_map(static fn ($failure): string => $failure->code, (new ExecutionRequestValidator)->validate($missingParent)),
        );
        self::assertSame(
            ['lineage_self_reference'],
            array_map(static fn ($failure): string => $failure->code, (new ExecutionRequestValidator)->validate($selfParent)),
        );
    }

    #[Test]
    public function submission_rejects_unknown_parents_and_duplicate_identities(): void
    {
        $store = new InMemoryExecutionRequestStore;
        $submission = $this->submission($store);
        $parent = ExecutionRequestFixtures::accepted();
        $unknownParent = $this->relatedRequest(
            'request:unknown-parent-child',
            RequestRelationship::Child,
            new ExecutionRequestId('request:not-stored'),
        );

        $unknownResult = $submission->handle(new SubmitExecutionRequest($unknownParent));
        $submission->handle(new SubmitExecutionRequest($parent));
        $duplicateResult = $submission->handle(new SubmitExecutionRequest($parent));

        self::assertSame('parent_request_not_found', $unknownResult->failures[0]->code);
        self::assertSame('request_identity_exists', $duplicateResult->failures[0]->code);
        self::assertCount(1, $store->requests);
    }

    #[Test]
    public function persistence_failure_never_returns_an_accepted_result(): void
    {
        $result = $this->submission(new FailingExecutionRequestStore)
            ->handle(new SubmitExecutionRequest(ExecutionRequestFixtures::accepted()));

        self::assertFalse($result->acceptedSuccessfully());
        self::assertSame(ExecutionRequestResultStatus::PersistenceFailed, $result->status);
        self::assertNull($result->requestId);
        self::assertSame('persistence_failed', $result->failures[0]->code);
    }

    #[Test]
    public function read_model_exposes_package_meaning_without_host_types(): void
    {
        $model = ExecutionRequestReadModel::fromRequest(ExecutionRequestFixtures::accepted());

        self::assertSame('request:accepted-fixture', $model->id);
        self::assertSame(" Preserve these bytes.\n", $model->originalPrompt);
        self::assertSame('repository:atlas-api', $model->repositoryReference);
        self::assertSame([['reference' => 'artifact:parser-log', 'name' => 'parser.log']], $model->attachments);
        self::assertSame('web', $model->channel);
        self::assertSame('original', $model->relationship);
    }

    private function submission(ExecutionRequestStore $store): ExecutionRequestSubmission
    {
        return new ExecutionRequestSubmission(new ExecutionRequestValidator, $store);
    }

    private function relatedRequest(string $id, RequestRelationship $relationship, ?ExecutionRequestId $parent): ExecutionRequest
    {
        return new ExecutionRequest(
            id: new ExecutionRequestId($id),
            originalPrompt: 'Follow the parent without changing it.',
            context: new ExecutionContext('project:atlas'),
            desiredResult: 'A lineage-preserving follow-up.',
            attachments: [],
            constraints: new ExecutionConstraints(300),
            permissions: new ExecutionPermissions(false, false, false),
            authorization: ExecutionRequestFixtures::authorization(),
            channel: DeliveryChannel::Web,
            relationship: $relationship,
            parentRequestId: $parent,
            executionIdentity: ExecutionRequestFixtures::executionIdentity(),
        );
    }
}

final class InMemoryExecutionRequestStore implements ExecutionRequestStore
{
    public array $requests = [];

    public function save(ExecutionRequest $request): void
    {
        $this->requests[$request->id->value] = $request;
    }

    public function find(ExecutionRequestId $id): ?ExecutionRequest
    {
        return $this->requests[$id->value] ?? null;
    }
}

final class FailingExecutionRequestStore implements ExecutionRequestStore
{
    public function save(ExecutionRequest $request): void
    {
        throw new ExecutionRequestPersistenceFailure('fixture persistence failure');
    }

    public function find(ExecutionRequestId $id): ?ExecutionRequest
    {
        return null;
    }
}
