<?php

namespace SilvertipSoftware\Fixtures;

use Illuminate\Support\Arr;
use Illuminate\Support\Traits\Macroable;

/**
 * Mark the test case as needing fixtures. Assumes running transactional tests for now!
 * Automatically loads all fixtures, but can be controlled by setting $fixtures on the
 * test class.
 *
 * The path defaults to 'tests/fixtures', but can also be customized per-testcase or full
 * suite.
 */
trait UsesFixtures
{
    use Macroable;

    /**
     * Cache of loaded fixtures by class.
     *
     * @var array
     */
    protected static $alreadyLoadedFixtures = [];

    /**
     * A test-supplied default fixture name to class name mapping. Populated with defaults if not
     * supplied; just makes config rows in fixtures not required in certain cases.
     *
     * @var array
     */
    protected $fixtureClassNames = [];

    /**
     * Calculated based on the YAML files found in the fixture path.
     */
    protected $fixtureSetsToLoad = [];

    /**
     * Cache of the loaded models, keyed by fixture set name and then fixture label. Ie.
     * $modelCache['users']['harry_potter'] is a User model.
     *
     * Cleared before every test.
     *
     * @var array
     */
    protected $modelCache = [];

    /**
     * Clears the cache. Not usually necessary.
     */
    protected function clearCache()
    {
        static::$alreadyLoadedFixtures = [];
        $this->modelCache = [];
    }

    /*
     * Get the directory where fixtures are stored, without a trailing slash.
     *
     * @return string The fixure path if specified. Defaults to 'tests/fixtures'
     */
    protected function getFixturePath()
    {
        return $this->fixturePath ?? 'tests/fixtures';
    }

    /**
     * Load the specified fixture sets and return keyed by name.
     *
     * @return array
     */
    protected function loadFixtures()
    {
        $fixtures = FixtureSet::createFixtures(base_path($this->getFixturePath()), $this->fixtureSetsToLoad, $this->fixtureClassNames, []);
        $byName = [];
        foreach ($fixtures as $fixture) {
            $byName[$fixture->getName()] = $fixture;
        }
        return $byName;
    }


    /**
     * Setup/load the specified fixtures before a test run. We do this before other the parent setup because
     * we want fixtures loaded before transactions get started.
     *
     * @return void
     */
    protected function setUpTraits()
    {
        FixtureSet::setModelNamespace($this->modelNamespace ?? '\App');

        $this->withFixtures($this->fixtures ?? 'all')
            ->setUpFixtures();
        parent::setUpTraits();
    }

    /**
     * Load the fixtures if we haven't already for this class, otherwise use the cache.
     *
     * @return void
     */
    protected function setUpFixtures()
    {
        $this->modelCache = [];

        $clz = get_class($this);
        if (!empty(static::$alreadyLoadedFixtures[$clz])) {
            $this->loadedFixtures = static::$alreadyLoadedFixtures[$clz];
        } else {
            $this->loadedFixtures = $this->loadFixtures();
            static::$alreadyLoadedFixtures[$clz] = $this->loadedFixtures;
        }
    }

    /**
     * Setup accessor macros for the loaded fixture sets. Example usage of accessors:
     *  $test->users('harry_potter') returns Harry User model
     *  $test->users(['harry_potter', 'hermione_granger']) reeturns [Harry, Hermione] array of User models
     *
     * @param  string|array         $names
     * @return Eloquent\Model|array
     */
    protected function setupFixtureAccessors($names)
    {
        foreach ($names as $name) {
            $this->macro($name, function ($labels) use ($name) {
                $labels = Arr::flatten([$labels]);
                $isSingleRecord = count($labels) == 1;

                $this->modelCache[$name] = $this->modelCache[$name] ?? [];
                $records = array_map(function ($label) use ($name) {
                    if (isset($this->loadedFixtures[$name][$label])) {
                        $this->modelCache[$name][$label] = $this->modelCache[$name][$label]
                            ?? $this->loadedFixtures[$name]->loadModel($label);
                    } else {
                        throw new \Exception("No fixture named '$label' found for fixture set '$name'");
                    }
                    return $this->modelCache[$name][$label];
                }, $labels);

                return $isSingleRecord ? $records[0] : $records;
            });
        }
    }

    /**
     * Determine the list of fixture sets to load. If 'all' is specified (the default), search the fixture
     * path for YAML files.
     *
     * @return $this
     */
    protected function withFixtures($fixtureSetNames)
    {
        $fixtureSetNames = Arr::flatten([$fixtureSetNames]);

        if (count($fixtureSetNames) == 1 && $fixtureSetNames[0] == 'all') {
            $fixtureSetNames = FixtureFile::findAllFixturesInPath($this->getFixturePath());
        }

        $this->fixtureSetsToLoad = $fixtureSetNames;
        $this->setupFixtureAccessors($fixtureSetNames);
        return $this;
    }
}
