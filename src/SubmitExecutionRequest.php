<?php

declare(strict_types=1);

namespace Sifrious\Logres;

final readonly class SubmitExecutionRequest
{
    public function __construct(public ExecutionRequest $request) {}
}
