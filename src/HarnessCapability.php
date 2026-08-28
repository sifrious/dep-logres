<?php

declare(strict_types=1);

namespace Sifrious\Logres;

final readonly class HarnessCapability
{
    public function __construct(
        public string $transport,
        public bool $streamsOutput,
        public bool $supportsCancellation,
        public bool $producesArtifacts,
        public bool $supportsInteraction,
    ) {}
}
