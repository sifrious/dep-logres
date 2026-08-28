<?php

declare(strict_types=1);

namespace Sifrious\Logres;

final class ExecutionRequestValidator
{
    public function validate(ExecutionRequest $request): array
    {
        $failures = [];

        if (trim($request->originalPrompt) === '') {
            $failures[] = new ExecutionRequestFailure('prompt_required', 'original_prompt', 'The original prompt is required.');
        }

        if (trim($request->desiredResult) === '') {
            $failures[] = new ExecutionRequestFailure('desired_result_required', 'desired_result', 'The desired result is required.');
        }

        if ($this->blank($request->context->projectReference) && $this->blank($request->context->repositoryReference)) {
            $failures[] = new ExecutionRequestFailure('target_required', 'context', 'A project or repository reference is required.');
        }

        if ($request->constraints->timeoutSeconds < 1) {
            $failures[] = new ExecutionRequestFailure('timeout_invalid', 'constraints.timeout_seconds', 'The timeout must be at least one second.');
        }

        if ($request->constraints->writablePaths !== [] && ! $request->permissions->filesystemWrite) {
            $failures[] = new ExecutionRequestFailure('write_permission_required', 'permissions.filesystem_write', 'Writable paths require filesystem-write permission.');
        }

        if ($request->permissions->externalCommunication && ! $request->permissions->network) {
            $failures[] = new ExecutionRequestFailure('network_permission_required', 'permissions.network', 'External communication requires network permission.');
        }

        foreach ($request->constraints->writablePaths as $path) {
            if (! is_string($path) || trim($path) === '' || ! str_starts_with($path, '/')) {
                $failures[] = new ExecutionRequestFailure('writable_path_invalid', 'constraints.writable_paths', 'Writable paths must be non-empty absolute paths.');
                break;
            }
        }

        foreach ($request->attachments as $attachment) {
            if (! $attachment instanceof ExecutionAttachment) {
                $failures[] = new ExecutionRequestFailure('attachment_invalid', 'attachments', 'Attachments must use the execution attachment contract.');
                continue;
            }

            if (preg_match('/^(artifact|upload):[a-zA-Z0-9._-]+$/', $attachment->reference) !== 1) {
                $failures[] = new ExecutionRequestFailure('attachment_reference_unsupported', 'attachments', 'Attachment references must use the artifact: or upload: namespace.');
            }

            if (trim($attachment->name) === '') {
                $failures[] = new ExecutionRequestFailure('attachment_name_required', 'attachments', 'Attachment names are required.');
            }
        }

        if (trim($request->requester->reference) === '') {
            $failures[] = new ExecutionRequestFailure('requester_reference_required', 'requester.reference', 'A requester reference is required.');
        }

        if (trim($request->requester->displayName) === '') {
            $failures[] = new ExecutionRequestFailure('requester_name_required', 'requester.display_name', 'A requester display name is required.');
        }

        if ($request->relationship === RequestRelationship::Original && $request->parentRequestId !== null) {
            $failures[] = new ExecutionRequestFailure('original_parent_forbidden', 'parent_request_id', 'An original request cannot have a parent.');
        }

        if ($request->relationship !== RequestRelationship::Original && $request->parentRequestId === null) {
            $failures[] = new ExecutionRequestFailure('lineage_parent_required', 'parent_request_id', 'A correction or child request requires a parent.');
        }

        if ($request->parentRequestId?->value === $request->id->value) {
            $failures[] = new ExecutionRequestFailure('lineage_self_reference', 'parent_request_id', 'A request cannot be its own parent.');
        }

        return $failures;
    }

    private function blank(?string $value): bool
    {
        return $value === null || trim($value) === '';
    }
}
