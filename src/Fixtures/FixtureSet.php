<?php

namespace SilvertipSoftware\Fixtures;

use ArrayAccess;
use Illuminate\Database\ConnectionResolverInterface as Resolver;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

class FixtureSet implements ArrayAccess
{
    const MAX_ID = 2**30 - 1;

    protected static $allCachedFixtures = [];
    protected static $allLoadedFixtures = [];
    protected static $database;

    protected $name;
    protected $path;
    protected $config;
    protected $modelClass;
    protected $fixtures;

    public function __construct($name, $className, $path, $config = [])
    {
        $this->name = $name;
        $this->path = $path;
        $this->config = $config;
        $this->modelClass = $className;

        $this->fixtures = $this->readFixtureFiles($path);
        $this->tableName = (new $this->modelClass)->getTable() ?? static::defaultFixtureTableName($name, $config);
    }

    public function getName()
    {
        return $this->name;
    }

    public function offsetExists($label)
    {
        return array_key_exists($label, $this->fixtures);
    }

    public function offsetGet($label)
    {
        return $this->fixtures[$label] ?? null;
    }

    public function offsetSet($label, $value)
    {
        $this->fixtures[$label] = $value;
    }

    public function offsetUnset($label)
    {
        unset($this->fixtures[$label]);
    }

    public function each($callback)
    {
        foreach ($this->fixtures as $fixture) {
            $callback($fixture);
        }
    }

    public function count()
    {
        return count($this->fixtures);
    }

    public function loadModel($label)
    {
        if (isset($this[$label])) {
            $key = $this[$label][$this->getKeyName()] ?? 0;
            return $this->getDatabaseInterface()->findModel($this->modelClass, $key);
        }
        return null;
    }

    public function getTableRows()
    {
        if (array_key_exists('DEFAULTS', $this->fixtures)) {
            unset($this->fixtures['DEFAULTS']);
        }

        return (new TableRows($this->tableName, $this->modelClass, $this->fixtures, $this->config))->toArray();
    }

    public static function defaultFixtureModelName($fsName, $config = [])
    {
        return Str::studly(Str::singular($fsName));
    }

    public static function defaultFixtureTableName($fsName, $config = [])
    {
        return ($config->tableNamePrefix ?? '') . str_replace('/', '_', $fsName);
    }

    public static function resetCache()
    {
        self::$allCachedFixtures = [];
    }

    public static function cacheForConnection($connectionName)
    {
        return self::$allCachedFixtures[$connectionName] ?? [];
    }

    public static function fixtureIsCached($connectionName, $table_name)
    {
        $cache = self::cacheForConnection($connectionName);
        return (!empty($cache) && array_key_exists($table_name, $cache));
    }

    public static function cachedFixtures($connectionName, $keysToFetch = null)
    {
        if ($keysToFetch) {
            return array_values(Arr::only(self::cacheForConnection($connectionName), $keysToFetch));
        } else {
            return array_values(self::cacheForConnection($connectionName));
        }
    }

    public static function cacheFixtures($connectionName, $fixturesMap)
    {
        $cache = self::cacheForConnection($connectionName);
        foreach ($fixturesMap as $table_name => $value) {
            $cache[$table_name] = $value;
        }
        self::$allCachedFixtures[$connectionName] = $cache;
    }

    public static function createFixtures($fixturesDirectory, $fixtureSetNames, $classNames = [], $config = [])
    {
        $classNameCache = new ClassCache($classNames);
        $connectionName = config('database.default');

        $fixtureFilesToRead = array_filter($fixtureSetNames, function ($fsName) use ($connectionName) {
            return !self::fixtureIsCached($connectionName, $fsName);
        });

        if (!empty($fixtureFilesToRead)) {
            $fixturesMap = self::readAndInsert($fixturesDirectory, $fixtureFilesToRead, $classNameCache, $connectionName);
            self::cacheFixtures($connectionName, $fixturesMap);
        }
        return self::cachedFixtures($connectionName, $fixtureSetNames);
    }

    public static function identify($label, $columnType = 'integer')
    {
        if ($columnType == 'uuid') {
            return Uuid::uuid5(Uuid::NAMESPACE_OID, $label)->toString();
        } else {
            return crc32($label) % self::MAX_ID;
        }
    }

    private function readFixtureFiles($path)
    {
        $fs = new Filesystem;
        $yamlFiles = array_merge($fs->glob($path . '/*.yml'), $fs->glob($path . '.yml'));

        $ret = [];
        foreach ($yamlFiles as $yamlFile) {
            $fixtureFile = FixtureFile::open($yamlFile);
            $this->modelClass = $fixtureFile->getModelClass() ?: $this->modelClass;
            $fixtureFile->each(function ($label, $attrs) use (&$ret) {
                $ret[$label] = new Fixture($attrs);
            });
        }
        return $ret;
    }

    private static function readAndInsert($fixturesDirectory, $fixtureFiles, $classNames, $connectionName)
    {
        $fixturesMap = [];
        $fixtureSets = array_map(function ($fsName) use ($fixturesDirectory, $classNames, &$fixturesMap) {
            $clz = $classNames[$fsName];
            $set = new FixtureSet($fsName, $clz, $fixturesDirectory . '/' . $fsName);
            $fixturesMap[$fsName] = $set;
            return $set;
        }, $fixtureFiles);

        self::updateAllLoadedFixtures($fixturesMap);
        self::insert($fixtureSets, $connectionName);

        return $fixturesMap;
    }

    private static function insert($fixtureSets, $connectionName)
    {
        $fixtureSetsByConnection = collect($fixtureSets)->groupBy(function ($set) use ($connectionName) {
            $m = new $set->modelClass;
            return $m->getConnectionName() ?? $connectionName;
        });

        $fixtureSetsByConnection->each(function ($sets, $conn) use ($connectionName) {
            $getTableRowsForConnection = [];

            foreach ($sets as $set) {
                foreach ($set->getTableRows() as $table => $rows) {
                    $curRows = $tableRowsForConnection[$table] ?? [];
                    $tableRowsForConnection[$table] = array_merge($curRows, $rows);
                }
            }

            self::insertFixturesSet($connectionName, $tableRowsForConnection, array_keys($tableRowsForConnection));
        });
    }

    private static function updateAllLoadedFixtures($fixturesMap)
    {
        self::$allLoadedFixtures = array_merge(self::$allLoadedFixtures, $fixturesMap);
    }

    private static function insertFixturesSet($connectionName, $tableRowsForConnection, $tablesToClear)
    {
        foreach ($tablesToClear as $table) {
            static::getDatabaseInterface()->wipe($connectionName, $table);
        }

        foreach ($tableRowsForConnection as $table => $rows) {
            static::getDatabaseInterface()->insert($connectionName, $table, $rows);
        }
    }

    public static function getDatabaseInterface()
    {
        return self::$database ?: new LaravelDatabaseInterface();
    }

    public static function setDatabaseInterface(DatabaseInterface $db)
    {
        static::$database = $db;
    }

    private function getKeyName()
    {
        $m = new $this->modelClass;
        return $m->getKeyName();
    }
}
