<?php

namespace SilvertipSoftware\Fixtures;

use ArrayAccess;
use Illuminate\Database\Eloquent\Model;

/**
 * Keeps a mapping between fixture set names (eg. 'users') and model class names (eg '\App\Users').
 * Only stores the default name; overrides in fixture files are not retained.
 */
class ClassCache implements ArrayAccess
{

    /**
     * The map between fixture set names and class names.
     *
     * @var array
     */
    protected $mapping;

    /**
     * Create and fill the cache with initial mappings.
     *
     * @param  array $mapping
     * @return void
     */
    public function __construct(array $mapping = [])
    {
        $this->mapping = $mapping;
    }

    /**
     * ArrayAccess check if mapping exists for a fixture set.
     *
     * @param  string $key
     * @return bool
     */
    public function offsetExists($key)
    {
        return array_key_exists($key, $this->mapping);
    }

    /**
     * ArrayAccess return the mapping referenced by $key. If not present, add the default
     * class name to the cache.
     *
     * @param  string $key
     * @return mixed
     */
    public function offsetGet($key)
    {
        if (!array_key_exists($key, $this->mapping))
        {
            $this->insertClassFor($key);
        }
        return $this->mapping[$key];
    }

    /**
     * ArrayAccess sets the class name referenced by $key. The class must be a subclass of
     * an Eloquent model.
     *
     * @param  string $key
     * @param  string $clz
     * @return void
     */
    public function offsetSet($key, $clz)
    {
        $this->mapping[$key] = is_subclass_of($clz, Model::class) ? $clz : null;
    }

    /**
     * ArrayAccess delete the class name referenced by $key.
     *
     * @param  string $key
     * @return void
     */
    public function offsetUnset($key)
    {
        unset($this->mapping[$key]);
    }

    /**
     * Insert the default class name for a given fixture name. Delegates to FixtureSet API class.
     *
     * @param  string $key
     * @return void
     */
    private function insertClassFor($key)
    {
        $clz = FixtureSet::defaultFixtureModelName($key);
        $this->offsetSet($key, $clz);
    }
}
