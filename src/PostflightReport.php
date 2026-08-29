<?php
declare(strict_types=1);
namespace Sifrious\Logres;
use InvalidArgumentException;
/** Independently observed terminal facts; provider prose is retained separately. */
final readonly class PostflightReport
{
    public function __construct(public array $evidence, public string $observedOutcome, public string $completedAt)
    {
        if (trim($observedOutcome) === '' || preg_match('/^\\d{4}-\\d{2}-\\d{2}T/', $completedAt) !== 1) {
            throw new InvalidArgumentException('Postflight requires an observed outcome and completion time.');
        }
        foreach ($evidence as $item) {
            if (! $item instanceof RunEvidence) {
                throw new InvalidArgumentException('Postflight evidence must contain RunEvidence values.');
            }
        }
    }
}
