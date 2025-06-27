<?php
declare(strict_types=1);

namespace Rules\RestrictImplicitDepndencyUsage;

use PHPStan\Analyser\ScopeContext;
use PHPStan\Analyser\ScopeFactory;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\ClassNameUsageLocation;
use PHPStan\Rules\RestrictedUsage\RestrictedStaticMethodUsageRule;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleLevelHelper;
use PHPStan\Testing\PHPStanTestCase;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psl\Collection\Vector;
use Superscript\PHPStanRules\Rules\RestrictImplicitDependencyUsage;

final class RestrictImplicitDependencyUsageTest extends PHPStanTestCase
{
    private ReflectionProvider $reflectionProvider;
    private ScopeFactory $scopeFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reflectionProvider = self::createReflectionProvider();
        $this->scopeFactory = self::getContainer()->getByType(ScopeFactory::class);
        $this->extension = new RestrictImplicitDependencyUsage();
    }

    #[Test]
    #[DataProvider('allowedCases')]
    public function it_can_be_allowed(string $class): void
    {
        $this->assertNull($this->extension->isRestrictedClassNameUsage(
            $this->reflectionProvider->getClass($class),
            $this->scopeFactory->create(ScopeContext::create(__DIR__)),
            ClassNameUsageLocation::from(ClassNameUsageLocation::STATIC_METHOD_CALL),
        ));
    }

    public static function allowedCases(): \Generator
    {
        yield 'global namespace' => [\JsonSerializable::class];
        yield 'class from defined dependency' => [\DeepCopy\DeepCopy::class];
        yield 'class from defined dev dependency' => [\PHPStan\TrinaryLogic::class];
    }

    #[Test]
    #[DataProvider('restrictedCases')]
    public function it_can_be_restricted(string $class): void
    {
        $this->assertNotNull($this->extension->isRestrictedClassNameUsage(
            $this->reflectionProvider->getClass($class),
            $this->scopeFactory->create(ScopeContext::create(__DIR__)),
            ClassNameUsageLocation::from(ClassNameUsageLocation::STATIC_METHOD_CALL),
        ));
    }

    public static function restrictedCases(): \Generator
    {
        yield 'class from undefined package' => [\Psl\Collection\Vector::class];
    }
}
