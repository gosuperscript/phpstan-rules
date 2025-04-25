<?php

declare(strict_types=1);

namespace Superscript\PHPStanRules\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\VerbosityLevel;

/**
 * @implements Rule<Identical>
 */
class StrictComparisonOfObjectsRule implements Rule
{
    public function getNodeType(): string
    {
        return Identical::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $leftType = $scope->getType($node->left);
        $rightType = $scope->getType($node->right);

        $leftClassRefs = $leftType->getObjectClassReflections();
        if (empty($leftClassRefs)) {
            return [];
        }

        $leftClass = $leftClassRefs[0];

        if (!$leftClass->hasNativeMethod('equals')) {
            return [];
        }

        $equalsMethod = $leftClass->getNativeMethod('equals');
        $rightTypeDescription = $rightType->describe(VerbosityLevel::typeOnly());

        foreach ($equalsMethod->getVariants() as $variant) {
            $parameters = $variant->getParameters();

            if (count($parameters) === 1) {
                $expectedType = $parameters[0]->getType();

                if ($expectedType->accepts($rightType, true)->yes()) {
                    return [
                        RuleErrorBuilder::message(sprintf(
                            'Avoid using "===" to compare %s and %s. The left-hand side implements equals(%s), use ->equals() instead.',
                            $leftClass->getName(),
                            $rightTypeDescription,
                            $expectedType->describe(VerbosityLevel::typeOnly())
                        ))
                            ->build(),
                    ];
                }
            }
        }

        return [];
    }
}