<?php

declare(strict_types=1);

namespace Sifrious\Logres;

enum ExecutionProvenanceClassification: string
{
    case Complete = 'complete';
    case LegacyStacksV1 = 'legacy_stacks_v1';
    case LegacyMissing = 'legacy_missing';

    /** @return array{classification: string, workspace_id: null} */
    public static function missingRecord(): array
    {
        return [
            'classification' => self::LegacyMissing->value,
            'workspace_id' => null,
        ];
    }
}
