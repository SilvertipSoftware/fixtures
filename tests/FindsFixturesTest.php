<?php

use PHPUnit\Framework\TestCase;
use SilvertipSoftware\Fixtures\FixtureFile;

class FindsFixturesTest extends TestCase
{
    public function testFindsEngineBasedFiles() {
        $results = FixtureFile::findAllFixturesInPath('tests/fixtures');

        $this->assertEquals(3, count($results));
    }

    public function testFindsNamedEngineBasedFiles() {
        $results = FixtureFile::findFixtureFilesAtPath('tests/fixtures/profiles');

        $this->assertEquals(1, count($results));
    }

}
