<?php

namespace StrictComparisonWithoutEquals;

class ClassA {}

function test(): void {
    $a = new ClassA();
    $b = new ClassA();

    if ($a === $b) {
        // Should not trigger
    }
}