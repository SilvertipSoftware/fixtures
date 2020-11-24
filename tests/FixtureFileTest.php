<?php

use PHPUnit\Framework\TestCase;
use SilvertipSoftware\Fixtures\FixtureFile;
use SilvertipSoftware\Fixtures\FixtureException;

class FixtureFileTest extends TestCase
{
    protected function setUp() {
        parent::setUp();
        $this->fileDir = __DIR__ . '/ymlfiles/';
    }

    public function testFindsModelClass() {
        $file = $this->openFile('complete.yml');
        $this->assertEquals('CLASSNAME', $file->getModelClass());
    }

    public function testFindsDataRows() {
        $file = $this->openFile('complete.yml');
        $this->assertEquals(['DEFAULTS', 'fixture1', 'fixture2'], array_keys($file->getRows()));
    }

    public function testDataTypesAreAsExpected() {
        $file = $this->openFile('complete.yml');
        $fixture = $file->getRows()['fixture1'];
        $this->assertInternalType('string', $fixture['name']);
        $this->assertInternalType('int', $fixture['age']);
    }

    public function testStringsAreUnquoted() {
        $file = $this->openFile('complete.yml');
        $fixture = $file->getRows()['fixture1'];
        $this->assertEquals('Ace', $fixture['nickname']);
    }

    public function testAnchorsAreResolved() {
        $file = $this->openFile('complete.yml');
        $fixture = $file->getRows()['fixture1'];
        $this->assertEquals('Vancouver', $fixture['city']);
    }

    public function testPhpFilesAreEvaluated() {
        $file = $this->openFile('evaluated.php');
        $fixture = $file->getRows()['fixture1'];
        $this->assertEquals(2, $fixture['age']);
        $fixture = $file->getRows()['fixture2'];
        $this->assertContains('evaluated.php', $fixture['name']);
    }

    public function testEmptyFile() {
        $this->assertNotNull($this->openFile('blank.yml'));
    }

    public function testEmptyWithComment() {
        $this->assertNotNull($this->openFile('comment.yml'));
    }

    public function testNonExistentFile() {
        $this->expectException(FixtureException::class);
        $this->expectExceptionCode(FixtureException::FILE_NOT_FOUND);
        $this->openFile('NOTAFILE.yml');
    }

    public function testTopLevelIsNotAMap() {
        $this->expectException(FixtureException::class);
        $this->expectExceptionCode(FixtureException::FORMAT_ERROR);
        $this->expectExceptionMessageRegExp('/not a map/');
        $this->openFile('scalar.yml');
    }

    public function testFixtureIsNotAMap() {
        $this->expectException(FixtureException::class);
        $this->expectExceptionCode(FixtureException::FORMAT_ERROR);
        $this->expectExceptionMessageRegExp('/invalid maps.*ron, hermione/');
        $this->openFile('badrows.yml');
    }

    protected function openFile($name) {
        return FixtureFile::open($this->fileDir . $name);
    }
}
