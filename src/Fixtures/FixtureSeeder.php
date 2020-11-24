<?php

namespace SilvertipSoftware\Fixtures;

use Illuminate\Database\Seeder;

/**
 * Use fixtures to seed the database. If you've got a non-standard setup, you'll have to subclass this,
 * as there's no way to pass parameters to seeders.
 */
class FixtureSeeder extends Seeder
{
    /**
     * Fixture name to classname mapping. By default, use defaults.
     */
    protected $fixtureClassNames = [];

    /**
     * The discovered fixtures.
     */
    private $fixtureSetsToLoad = [];

    /**
     * Seed the application's database. This looks suspiciously like the process in UsesFixtures... refactor.
     *
     * @return void
     */
    public function run()
    {
        $this->findFixtures()
            ->loadFixtures();
    }

    protected function getFixturePath()
    {
        return $this->fixturePath ?? 'tests/fixtures';
    }

    protected function findFixtures()
    {
        $this->fixtureSetsToLoad = FixtureFile::findAllFixturesInPath($this->getFixturePath());

        return $this;
    }

    protected function loadFixtures() {
        if ($this->modelNamespace ?? false) {
            FixtureSet::setModelNamespace($this->modelNamespace);
        }

        FixtureSet::createFixtures(base_path($this->getFixturePath()), $this->fixtureSetsToLoad, $this->fixtureClassNames, []);
        return $this;
    }
}