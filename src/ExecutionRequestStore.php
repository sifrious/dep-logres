<?php

declare(strict_types=1);

namespace Sifrious\Logres;

interface ExecutionRequestStore
{
    public function save(ExecutionRequest $request): void;

    public function find(ExecutionRequestId $id): ?ExecutionRequest;
}
