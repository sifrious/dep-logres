<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class RunnerLeaseAcknowledgement
{
    public function __construct(
        public string $leaseId,
        public RunnerIdentity $runnerId,
        public string $acknowledgementId,
        public DateTimeImmutable $acknowledgedAt,
    ) {
        if (trim($leaseId) === '' || trim($acknowledgementId) === '') {
            throw new InvalidArgumentException('Runner lease acknowledgements require stable lease and acknowledgement identities.');
        }
    }
}
