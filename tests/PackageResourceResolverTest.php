<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sifrious\Logres\PackageResourceResolver;

final class PackageResourceResolverTest extends TestCase
{
    #[Test]
    public function it_resolves_existing_resources_inside_the_package_root(): void
    {
        $root = sys_get_temp_dir().'/logres-resource-'.bin2hex(random_bytes(6));
        mkdir($root.'/scripts', 0777, true);
        file_put_contents($root.'/scripts/check.php', '<?php return true;');

        try {
            $resolver = new PackageResourceResolver($root);

            self::assertSame(realpath($root.'/scripts/check.php'), $resolver->resolve('scripts/check.php'));
        } finally {
            unlink($root.'/scripts/check.php');
            rmdir($root.'/scripts');
            rmdir($root);
        }
    }

    #[Test]
    public function it_rejects_traversal_outside_the_package_root(): void
    {
        $root = sys_get_temp_dir().'/logres-resource-'.bin2hex(random_bytes(6));
        mkdir($root, 0777, true);
        $resolver = new PackageResourceResolver($root);

        try {
            $this->expectException(InvalidArgumentException::class);
            $resolver->resolve('../outside.txt');
        } finally {
            rmdir($root);
        }
    }
}
