<?php

use PHPUnit\Framework\TestCase;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Facades\DB;
use SilvertipSoftware\Fixtures\TableRows;
use SilvertipSoftware\Fixtures\Fixture;

include('TestModels.php');

class RowBuildingTest extends TestCase
{
    protected function setUp()
    {
        parent::setUp();
        $this->setUpDatabase();

        $this->userFixtures = [
            'caleb' => new Fixture([
                'name' => 'Caleb Widogast',
                'email' => '$LABEL@domain.com',
                'ad_name' => 'DOMAIN\\$LABEL'
            ]),
            'jester' => new Fixture([
                'name' => 'Jester Lavorre',
                'email' => '$LABEL@domain.com',
                'ad_name' => 'DOMAIN\\$LABEL'
            ])
        ];

        $this->globalUserFixtures = [
            'admin' => new Fixture([
                'user' => 'caleb',
            ]),
        ];

        $this->profileFixtures = [
            'caleb' => new Fixture([
                'avatar' => 'caleb.png',
                'user' => 'caleb'
            ]),
            'jester' => new Fixture([
                'avatar' => 'jester.png'
            ]),
            'frumpkin' => new Fixture([
                'owner' => 'caleb (user)'
            ])
        ];

        $this->roleFixtures = [
            'admin' => new Fixture([
                'name' => 'admin',
                'users' => 'caleb, jester'
            ]),
            'super' => new Fixture([
                'name' => 'superuser',
                'users' => ['caleb'],
                'managers' => 'jester'
            ])
        ];
    }

    public function testItBuildsCorrectStructure()
    {
        $tableRows = new TableRows('users', __User::class, $this->userFixtures);
        $builtRows = $tableRows->toArray();
        $this->assertEquals(1, count($builtRows));
        $this->assertEquals(2, count($builtRows['users']));
    }

    public function testItAssignsNumericIds()
    {
        $tableRows = new TableRows('users', __User::class, $this->userFixtures);
        $builtRows = $tableRows->toArray();
        $caleb = $builtRows['users'][0];
        $this->assertInternalType('int', $caleb['id']);
    }

    public function testItAssignsUuid5Ids()
    {
        $tableRows = new TableRows('users', __GlobalUser::class, $this->userFixtures);
        $builtRows = $tableRows->toArray();
        $caleb = $builtRows['users'][0];
        $this->assertRegExp('/[0-9a-f]{8}\-[0-9a-f]{4}\-[0-9a-f]{4}\-[0-9a-f]{4}\-[0-9a-f]{12}/', $caleb['id']);
    }

    public function testItInterpolatesLabelToken()
    {
        $tableRows = new TableRows('users', __User::class, $this->userFixtures);
        $builtRows = $tableRows->toArray();
        $this->assertEquals('caleb@domain.com', $builtRows['users'][0]['email']);
        $this->assertEquals('jester@domain.com', $builtRows['users'][1]['email']);
        $this->assertEquals('DOMAIN\caleb', $builtRows['users'][0]['ad_name']);
        $this->assertEquals('DOMAIN\jester', $builtRows['users'][1]['ad_name']);
    }

    public function testItSetsTimestampsWhenNeeded()
    {
        $tableRows = new TableRows('users', __User::class, $this->userFixtures);
        $builtRows = $tableRows->toArray();
        $this->assertArrayHasKey('created_at', $builtRows['users'][0]);
        $this->assertArrayHasKey('updated_at', $builtRows['users'][0]);
    }

    public function testItDoesNotSetTimestampsWhenDisabled()
    {
        $tableRows = new TableRows('users', __GlobalUser::class, $this->userFixtures);
        $builtRows = $tableRows->toArray();
        $this->assertArrayNotHasKey('created_at', $builtRows['users'][0]);
        $this->assertArrayNotHasKey('updated_at', $builtRows['users'][0]);
    }

    public function testItSetsBelongsToRelation()
    {
        DB::shouldReceive('getDriverName')->andReturn('mysql');
        $userRows = (new TableRows('users', __User::class, $this->userFixtures))->toArray();
        $tableRows = new TableRows('profiles', __Profile::class, $this->profileFixtures);
        $builtRows = $tableRows->toArray();
        $this->assertArrayHasKey('user_id', $builtRows['profiles'][0]);
        $this->assertEquals($userRows['users'][0]['id'], $builtRows['profiles'][0]['user_id']);

        $this->assertArrayNotHasKey('user_id', $builtRows['profiles'][1]);
    }

    public function testParentIdIsNotUuid()
    {
        DB::shouldReceive('getDriverName')->andReturn('mysql');
        $userRows = (new TableRows('users', __User::class, $this->userFixtures))->toArray();
        $builtRows = (new TableRows('global_users', __GlobalUser::class, $this->globalUserFixtures))->toArray();
        $this->assertInternalType('int', $builtRows['global_users'][0]['user_id']);
        $this->assertInternalType('string', $builtRows['global_users'][0]['id']);
    }

    public function testItSetsMorphToRelation()
    {
        $userRows = (new TableRows('users', __User::class, $this->userFixtures))->toArray();
        $builtRows = (new TableRows('profiles', __Profile::class, $this->profileFixtures))->toArray();

        $this->assertArrayHasKey('owner_id', $builtRows['profiles'][2]);
        $this->assertArrayHasKey('owner_type', $builtRows['profiles'][2]);
        $this->assertEquals($userRows['users'][0]['id'], $builtRows['profiles'][2]['owner_id']);
        $this->assertEquals('user', $builtRows['profiles'][2]['owner_type']);
    }

    public function testItAddsPivotRowsForBelongsToMany()
    {
        $userRows = (new TableRows('users', __User::class, $this->userFixtures))->toArray();
        $roleRows = (new TableRows('roles', __Role::class, $this->roleFixtures))->toArray();

        $this->assertArrayHasKey('role_user', $roleRows);
        $this->assertEquals(3, count($roleRows['role_user']));
        $this->assertEquals($userRows['users'][0]['id'], $roleRows['role_user'][0]['user_id']);
        $this->assertEquals($roleRows['roles'][0]['rid'], $roleRows['role_user'][0]['role_id']);
        $this->assertEquals($userRows['users'][1]['id'], $roleRows['role_user'][1]['user_id']);
        $this->assertEquals($roleRows['roles'][0]['rid'], $roleRows['role_user'][1]['role_id']);

        $this->assertEquals($userRows['users'][0]['id'], $roleRows['role_user'][2]['user_id']);
        $this->assertEquals($roleRows['roles'][1]['rid'], $roleRows['role_user'][2]['role_id']);
    }

    public function testItAddsPivotRowsForMorphToMany()
    {
        $userRows = (new TableRows('users', __User::class, $this->userFixtures))->toArray();
        $roleRows = (new TableRows('roles', __Role::class, $this->roleFixtures))->toArray();

        $this->assertEquals(1, count($roleRows['managables']));
        $this->assertEquals($userRows['users'][1]['id'], $roleRows['managables'][0]['user_id']);
        $this->assertEquals($roleRows['roles'][1]['rid'], $roleRows['managables'][0]['managable_id']);
        $this->assertEquals(__Role::class, $roleRows['managables'][0]['managable_type']);
    }

    private function setUpDatabase()
    {
        $capsule = new Capsule;

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
    }
}
