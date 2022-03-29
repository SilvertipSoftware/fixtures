<?php

namespace SilvertipSoftware\Fixtures;

use ArrayAccess;

/**
 * Not much more than an array now... but I think needs to be class to be passed by
 * reference (so that setting the id in TableRow updates the cache in FixtureSet).
 */
class Fixture implements ArrayAccess
{

    /**
     * The attributes defined on the model fixture.
     *
     * @var array
     */
    protected $attributes;

    /**
     * Create a new fixture for a model with given attributes.
     *
     * @param  array  $attributes
     * @return void
     */
    public function __construct($attributes)
    {
        $this->attributes = $attributes;
    }

    /**
     * Iterate through the attributes. The callback gets key and value data.
     *
     * @param  closure $callback(string, mixed)
     * @return void
     */
    public function each($callback)
    {
        foreach ($this->attributes as $key => $value)
        {
            $callback($key, $value);
        }
    }

    /**
     * Convert to array. Just return the attributes.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->attributes;
    }

    /**
     * ArrayAccess check if the attribute referenced by $key exists.
     *
     * @param  string $key
     * @return bool
     */
    public function offsetExists($key)
    {
        return array_key_exists($key, $this->attributes);
    }

    /**
     * ArrayAccess return the attribute referenced by $key. Returns null if not
     * present.
     *
     * @param  string $key
     * @return mixed
     */
    public function offsetGet($key)
    {
        return isset($this->attributes[$key])
            ? $this->attributes[$key]
            : null;
    }

    /**
     * ArrayAccess sets the attribute referenced by $key.
     *
     * @param  string $key
     * @param  mixed  $value
     * @return void
     */
    public function offsetSet($key, $value)
    {
        $this->attributes[$key] = $value;
    }

    /**
     * ArrayAccess delete the attribute referenced by $key.
     *
     * @param  string $key
     * @return void
     */
    public function offsetUnset($key)
    {
        unset($this->attributes[$key]);
    }
}
