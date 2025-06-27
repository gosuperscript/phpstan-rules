<?php
declare(strict_types=1);

namespace Superscript\PHPStanRules\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function Superscript\PHPStanRules\basepath;

final class BasepathTest extends TestCase
{
    #[Test]
    public function it_gets_the_base_path(): void
    {
        $this->assertEquals(realpath(__DIR__ . '/../'), basepath());
    }
}