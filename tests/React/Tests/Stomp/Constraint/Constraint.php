<?php

namespace React\Tests\Stomp\Constraint;

if (class_exists('PHPUnit\Framework\Constraint\Constraint', true)) {
    // PHPUnit 6+
    abstract class Constraint extends \PHPUnit\Framework\Constraint\Constraint { }
} else {
    // PHPUnit 5 / PHPUnit 4
    abstract class Constraint extends \PHPUnit_Framework_Constraint { }
}
