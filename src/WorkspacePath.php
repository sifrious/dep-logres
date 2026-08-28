<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class WorkspacePath
{
    public function __construct(public string $value)
    {
        if ($value === '' || $value[0] !== '/' || str_contains($value, "\0") || str_contains($value, '\\')) {
            throw new InvalidArgumentException('Workspace paths must be absolute POSIX paths.');
        }

        if ($value !== self::normalize($value)) {
            throw new InvalidArgumentException('Workspace paths must be normalized before use.');
        }
    }

    public static function fromInput(string $value): self
    {
        return new self(self::normalize($value));
    }

    public function contains(self $path): bool
    {
        return $path->value === $this->value || str_starts_with($path->value, rtrim($this->value, '/').'/');
    }

    private static function normalize(string $value): string
    {
        if ($value === '' || $value[0] !== '/' || str_contains($value, "\0") || str_contains($value, '\\')) {
            throw new InvalidArgumentException('Workspace paths must be absolute POSIX paths.');
        }

        $segments = [];

        foreach (explode('/', $value) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        return '/'.implode('/', $segments);
    }
}
