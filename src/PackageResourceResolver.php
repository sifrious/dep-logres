<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class PackageResourceResolver
{
    private string $packageRoot;

    public function __construct(string $packageRoot)
    {
        $resolved = realpath($packageRoot);

        if ($resolved === false || ! is_dir($resolved)) {
            throw new InvalidArgumentException('A package resource root must be an existing directory.');
        }

        $this->packageRoot = rtrim($resolved, DIRECTORY_SEPARATOR);
    }

    public function resolve(string $resource): string
    {
        if ($resource === '' || str_starts_with($resource, DIRECTORY_SEPARATOR)) {
            throw new InvalidArgumentException('A package resource must be a relative path.');
        }

        $resolved = realpath($this->packageRoot.DIRECTORY_SEPARATOR.$resource);
        $prefix = $this->packageRoot.DIRECTORY_SEPARATOR;

        if ($resolved === false || ! str_starts_with($resolved, $prefix)) {
            throw new InvalidArgumentException("Package resource {$resource} is missing or outside the package root.");
        }

        return $resolved;
    }
}
