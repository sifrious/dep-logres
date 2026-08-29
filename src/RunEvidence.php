<?php
declare(strict_types=1);
namespace Sifrious\Logres;
use InvalidArgumentException;
final readonly class RunEvidence
{
    public function __construct(public string $kind, public string $reference, public string $observedAt)
    {
        if (trim($kind) === '' || trim($reference) === '' || preg_match('/^\\d{4}-\\d{2}-\\d{2}T/', $observedAt) !== 1) {
            throw new InvalidArgumentException('Run evidence requires kind, immutable reference, and observation time.');
        }
    }
}
