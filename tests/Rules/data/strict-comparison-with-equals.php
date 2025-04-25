<?php

namespace StrictComparisonWithEquals;

class ClassA {
    public function equals(ClassA $that): bool {
        return true;
    }
}

function test(): void {
    $a = new ClassA();
    $b = new ClassA();

    if ($a === $b) {
        // Line 20: should trigger rule
    }
}
