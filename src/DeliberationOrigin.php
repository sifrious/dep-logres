<?php
declare(strict_types=1);
namespace Sifrious\Logres;
use InvalidArgumentException;
/** References upstream meaning without making Logres own deliberation or planning. */
final readonly class DeliberationOrigin
{
    public function __construct(public string $userInputReference, public ?string $intentReference = null, public ?string $conversationReference = null, public ?string $planReference = null, public ?string $planStepReference = null)
    {
        if (trim($userInputReference) === '') {
            throw new InvalidArgumentException('An execution request must retain its originating user input.');
        }
        if ($planStepReference !== null && $planReference === null) {
            throw new InvalidArgumentException('A plan step origin requires its plan origin.');
        }
    }
}
