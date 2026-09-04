<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class VerificationPlan
{
    /** @var list<CheckDefinition> */
    public array $checks;

    /** @param list<mixed> $checks */
    public function __construct(
        public string $id,
        public string $version,
        array $checks,
    ) {
        if (trim($this->id) === '' || trim($this->version) === '') {
            throw new InvalidArgumentException('Verification plans require identity and version.');
        }

        $seen = [];
        foreach ($checks as $check) {
            if (! $check instanceof CheckDefinition) {
                throw new InvalidArgumentException('Verification plans only contain CheckDefinition values.');
            }
            if (isset($seen[$check->id])) {
                throw new InvalidArgumentException("Duplicate verification check [{$check->id}].");
            }
            $seen[$check->id] = true;
        }

        $this->checks = $checks;
    }
}
