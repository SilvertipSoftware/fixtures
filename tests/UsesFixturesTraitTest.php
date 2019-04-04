<?php

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\TestCase;
use SilvertipSoftware\Fixtures\FixtureException;
use SilvertipSoftware\Fixtures\FixtureSet;
use SilvertipSoftware\Fixtures\UsesFixtures;

include('TestModels.php');

$container = null;

function __container($obj = null) {
    global $container;

    if ($obj != null) {
        $container = $obj;
    }

    return $container;
}

function base_path($path) {
    return $path;
}

function config($name) {
    global $container;

    return $container['config']->get($key, $default);
}


abstract class Sample extends StubTestCase {
    use UsesFixtures;

    public function __construct($fixtures) {
        parent::__construct();
        $this->fixtures = $fixtures;
    }
    public function getGlobalCache() {
        return static::$alreadyLoadedFixtures;
    }

    public function getLoadedCache() {
        return $this->loadedFixtures;
    }

    public function getModelCache() {
        return $this->modelCache;
    }

    public function exposedClearCache() {
        $this->clearCache();
    }
    public function setDirectory($path) {
        $this->fixturePath = $path;
    }
}

class UsesFixturesTraitTest extends TestCase
{
    protected $mockLoader;

    protected function setUp() {
        parent::setUp();
        __container(new Container);
        $this->setUpDatabase();
    }

    protected function tearDown() {
        Mockery::close();
    }

    public function testCacheIsPerTestClass() {
        $test = $this->createTest();
        FixtureSet::resetCache();
        $test->exposedClearCache();
        $test->setUp();
        $this->assertInternalType('array', $test->getGlobalCache());
        $this->assertContains(get_class($test), array_keys($test->getGlobalCache()));
    }

    public function testClearsAndLoadsFixtures() {
        $this->mockLoader->shouldReceive('disableForeignKeyConstraints')->with(null);
        $this->mockLoader->shouldReceive('wipe')->with(null, 'users');
        $this->mockLoader->shouldReceive('insert')->andReturnUsing(function($c, $t, $r) {
            $this->assertNull($c);
            $this->assertEquals('users', $t);
            $this->assertInternalType('array', $r);
            $this->assertEquals(1, count($r));
            $this->assertEquals('Caleb Widogast', $r[0]['name']);
        });
        $this->mockLoader->shouldReceive('enableForeignKeyConstraints')->with(null);
        $test = $this->createTest(['users']);
        FixtureSet::resetCache();
        $test->exposedClearCache();
        $test->setUp();
        $this->assertTrue(true);
    }

    public function testGlobalCacheContainsLoadedFixtures() {
        $this->mockLoader->shouldIgnoreMissing();
        $test = $this->createTest(['all']);
        FixtureSet::resetCache();
        $test->exposedClearCache();
        $test->setUp();
        $fixtures = $test->getGlobalCache()[get_class($test)];

        $this->assertInternalType('array', $fixtures);
        $this->assertEquals(2, count($fixtures));
        $this->assertInstanceOf(FixtureSet::class, $fixtures['users']);
    }

    public function testGlobalCacheIsNotWipedAcrossTests() {
        $this->mockLoader->shouldIgnoreMissing();
        $test = $this->createTest(['all']);
        FixtureSet::resetCache();
        $test->exposedClearCache();
        $test->setUp();
        $test->tearDown();
        $fixtures = $test->getGlobalCache()[get_class($test)];
        $this->assertEquals(2, count($fixtures));
    }

    public function testOnlyLoadsWantedFixtureSets() {
        $this->mockLoader->shouldIgnoreMissing();
        $test = $this->createTest(['users']);
        FixtureSet::resetCache();
        $test->exposedClearCache();
        $test->setUp();
        $fixtures = $test->getGlobalCache()[get_class($test)];
        $this->assertEquals(1, count($fixtures));
    }

    public function testIgnoresFixturesWhenSpecified() {
        $test = $this->createTest([]);
        $test->exposedClearCache();
        $test->setUp();
        $this->assertTrue(true);
    }

    public function testErrorsOnMissingFixtureSet() {
        $this->expectException(FixtureException::class);
        $this->expectExceptionCode(FixtureException::FILE_NOT_FOUND);
        $test = $this->createTest(['BADNAME']);
        $test->exposedClearCache();
        $test->setUp();
        $this->assertTrue(true);
    }

    public function testDoesNotNeedToReseed() {
        $test = $this->createTest(['users']);
        $test->exposedClearCache();
        $test->setUp();
        $this->assertTrue(true);
    }

    public function testUsesGivenDirectory() {
        $this->mockLoader->shouldIgnoreMissing();
        $test = $this->createTest(['all']);
        FixtureSet::resetCache();
        $test->exposedClearCache();
        $test->setDirectory('tests/other_fixtures');
        $test->setUp();
        $fixtures = $test->getGlobalCache()[get_class($test)];
        $this->assertEquals(1, count($fixtures));
    }

    public function testCachesFoundModels() {
        $this->mockLoader->shouldReceive('disableForeignKeyConstraints')->with(null);
        $this->mockLoader->shouldReceive('wipe');
        $this->mockLoader->shouldReceive('insert');
        $this->mockLoader->shouldReceive('enableForeignKeyConstraints')->with(null);
        $this->mockLoader->shouldReceive('findModel')->with(__User::class, Mockery::any())->andReturnUsing(function($clz, $id) {
            return $this->makeTestUser($id);
        });

        $test = $this->createTest(['users']);
        FixtureSet::resetCache();
        $test->exposedClearCache();
        $test->setUp();

        $user1 = $test->users('caleb');
        $this->assertEquals(1, count($test->getModelCache()));
        $this->assertNotNull($user1);
        $user2 = $test->users('caleb');
        $this->assertEquals(1, count($test->getModelCache()));
        $this->assertEquals($user1, $user2);
    }

    public function testModelsAreReloadedForNextTest() {
        $this->mockLoader->shouldReceive('disableForeignKeyConstraints')->with(null);
        $this->mockLoader->shouldReceive('wipe');
        $this->mockLoader->shouldReceive('insert');
        $this->mockLoader->shouldReceive('enableForeignKeyConstraints')->with(null);
        $this->mockLoader->shouldReceive('findModel')->twice()->with(__User::class, Mockery::any())->andReturnUsing(function($clz, $id) {
            return $this->makeTestUser($id);
        });
        $test = $this->createTest(['all']);
        FixtureSet::resetCache();
        $test->exposedClearCache();
        $test->setUp();
        $test->users('caleb');
        $test->tearDown();
        $test->setUp();
        $test->users('caleb');
        $this->assertTrue(true);
    }

    private function makeTestUser($id) {
        return new __User([
            'id' => $id,
            'name' => 'Caleb Widogast'
        ]);
    }

    private function createTest($fixtures = []) {
        $test = new class($fixtures) extends Sample {};
        return $test;
    }

    private function setUpDatabase()
    {
        $capsule = new Capsule(__container());

        $capsule->addConnection([
            'driver'    => 'mysql',
            'host'      => 'localhost',
            'database'  => 'NOTADATABASE',
            'username'  => 'root',
            'password'  => 'password',
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => '',
        ]);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
        $this->mockLoader = Mockery::mock('\SilvertipSoftware\Fixtures\DatabaseInterface');
        FixtureSet::setDatabaseInterface($this->mockLoader);
    }
}
