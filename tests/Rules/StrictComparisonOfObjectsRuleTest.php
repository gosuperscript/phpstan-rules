<?php

namespace Superscript\PHPStanRules\Tests\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Superscript\PHPStanRules\Rules\StrictComparisonOfObjectsRule;

/**
 * @extends RuleTestCase<StrictComparisonOfObjectsRule>
 */
class StrictComparisonOfObjectsRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new StrictComparisonOfObjectsRule();
    }

    public function test(): void
    {
        $this->analyse([__DIR__ . '/data/strict-comparison-with-equals.php'], [
            [
                'Avoid using "===" to compare StrictComparisonWithEquals\ClassA and StrictComparisonWithEquals\ClassA. The left-hand side implements equals(StrictComparisonWithEquals\ClassA), use ->equals() instead.',
                15,
            ],
        ]);
    }

    public function testIgnoresWhenEqualsIsNotCompatible(): void
    {
        $this->analyse([__DIR__ . '/data/strict-comparison-with-incompatible-equals.php'], []);
    }

    public function testIgnoresWhenEqualsNotImplemented(): void
    {
        $this->analyse([__DIR__ . '/data/strict-comparison-without-equals.php'], []);
    }
}
