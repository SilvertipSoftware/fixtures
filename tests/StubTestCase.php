<?php

use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Stub out Laravel TestCase
 */
abstract class StubTestCase extends BaseTestCase
{
    protected function setUp() {
        parent::setUp();
        $this->setUpTraits();
    }

    protected function setUpTraits()
    {
        // noop
    }
}