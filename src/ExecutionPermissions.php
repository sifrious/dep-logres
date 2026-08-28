<?php

declare(strict_types=1);

namespace Sifrious\Logres;

final readonly class ExecutionPermissions
{
    public function __construct(
        public bool $network,
        public bool $filesystemWrite,
        public bool $externalCommunication,
    ) {}
}
