<?php

declare(strict_types=1);

namespace Sifrious\Logres;

final readonly class ProviderInvocationRecord
{
    public function __construct(
        public ProviderInvocationRequest $request,
        public ProviderInvocationStatus $status = ProviderInvocationStatus::Reserved,
        public ?ProviderAcknowledgement $acknowledgement = null,
        public ?string $reason = null,
        public int $version = 0,
    ) {
        if ($version < 0) {
            throw new \InvalidArgumentException('Provider invocation version cannot be negative.');
        }
    }

    public function record(ProviderDispatchResult $result): self
    {
        return new self($this->request, $result->status, $result->acknowledgement, $result->reason, $this->version + 1);
    }

    public function dispatching(): self
    {
        return new self($this->request, ProviderInvocationStatus::Dispatching, version: $this->version + 1);
    }
}
