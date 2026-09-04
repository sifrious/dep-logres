<?php

declare(strict_types=1);

namespace Sifrious\Logres;

enum RunArtifactAttachmentStatus: string
{
    case Attached = 'attached';
    case HashMismatch = 'hash_mismatch';
    case StorageMissing = 'storage_missing';
}
