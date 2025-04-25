<?php

namespace StrictComparisonWithIncompatibleEquals;

class ClassA {
    public function equals(ClassA $a): bool {
        return true;
    }
}

class ClassB {}

function test(): void {
    $a = new ClassA();
    $b = new ClassB();

    if ($a === $b) {
        // Should not trigger
    }
}