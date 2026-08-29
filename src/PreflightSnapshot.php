<?php
declare(strict_types=1);
namespace Sifrious\Logres;
use InvalidArgumentException;
/** Immutable dispatch input assembled from owner-supplied workspace and runtime facts. */
final readonly class PreflightSnapshot
{
    public function __construct(public RunProvenance $provenance, public DispatchAuthorizationSnapshot $authorization, public array $startingEvidence, public string $capturedAt)
    {
        if ($authorization->actor !== $provenance->initiatingActor || $authorization->targetId->value !== $provenance->targetSelection->target->id->value) {
            throw new InvalidArgumentException('Preflight authorization must match the frozen Run provenance.');
        }
        if (preg_match('/^\\d{4}-\\d{2}-\\d{2}T/', $capturedAt) !== 1) {
            throw new InvalidArgumentException('Preflight capture time is required.');
        }
        foreach ($startingEvidence as $evidence) {
            if (! $evidence instanceof RunEvidence) {
                throw new InvalidArgumentException('Preflight evidence must contain RunEvidence values.');
            }
        }
    }
}
