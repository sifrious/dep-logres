<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class RepositoryIdentity
{
    public function __construct(public string $value)
    {
        if (preg_match('/^repository:[a-zA-Z0-9._-]+(?:\/[a-zA-Z0-9._-]+)*$/', $value) !== 1) {
            throw new InvalidArgumentException('A repository identity must be canonical and independent of a local path.');
        }
    }

    public static function fromRemote(string $remote): self
    {
        $remote = trim($remote);

        if (preg_match('/^[^@]+@([^:]+):(.+)$/', $remote, $matches) === 1) {
            return self::fromHostAndPath($matches[1], $matches[2]);
        }

        $parts = parse_url($remote);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'], $parts['path']) || ! in_array($parts['scheme'], ['http', 'https', 'ssh'], true)) {
            throw new InvalidArgumentException('Repository identity requires a canonical HTTP or SSH remote.');
        }

        return self::fromHostAndPath($parts['host'], $parts['path']);
    }

    private static function fromHostAndPath(string $host, string $path): self
    {
        $host = strtolower(trim($host));
        $path = trim(preg_replace('/\.git$/', '', trim($path, '/')) ?? '', '/');

        if ($host === 'github.com') {
            $path = strtolower($path);
        }

        return new self("repository:{$host}/{$path}");
    }
}
