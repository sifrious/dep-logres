<?php

declare(strict_types=1);

namespace Sifrious\Logres;

final readonly class ExecutionRequestSubmission
{
    public function __construct(
        private ExecutionRequestValidator $validator,
        private ExecutionRequestStore $store,
    ) {}

    public function handle(SubmitExecutionRequest $command): ExecutionRequestResult
    {
        $failures = $this->validator->validate($command->request);

        if ($failures !== []) {
            return ExecutionRequestResult::rejected($failures);
        }

        if ($this->store->find($command->request->id) !== null) {
            return ExecutionRequestResult::rejected([
                new ExecutionRequestFailure('request_identity_exists', 'id', 'The execution request identity already exists.'),
            ]);
        }

        $parentId = $command->request->parentRequestId;

        if ($parentId !== null && $this->store->find($parentId) === null) {
            return ExecutionRequestResult::rejected([
                new ExecutionRequestFailure('parent_request_not_found', 'parent_request_id', 'The parent execution request does not exist.'),
            ]);
        }

        try {
            $this->store->save($command->request);
        } catch (ExecutionRequestPersistenceFailure) {
            return ExecutionRequestResult::persistenceFailed();
        }

        return ExecutionRequestResult::accepted($command->request->id);
    }
}
