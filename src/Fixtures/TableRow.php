<?php

namespace SilvertipSoftware\Fixtures;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Arr;

/**
 * Builder for a model database row. Adds timestamps, ids, relations, etc as appropriate.
 */
class TableRow
{

    /**
     * The container object which holds rows for multiple tables. Used for inserting related tables.
     *
     * @var TableRow
     */
    protected $container;

    /**
     * The label of the fixture from the definition
     *
     * @var string
     */
    protected $label;

    /**
     * The timestamp to use for the created_at, update_at standard timestamps.
     *
     * @var Carbon\Carbon
     */
    protected $now;

    /**
     * The fixture to process.
     *
     * @var Fixture
     */
    protected $fixture;

    /**
     * An instance of the model for which we are building. Need it for metadata properties which are not
     * static in Laravel.
     *
     * @var Eloquent\Model
     */
    protected $model;

    /**
     * Create and process a fixture.
     *
     * @param  Fixture   $fixture
     * @param  TableRows $container
     * @param  string    $label
     * @param  Carbon    $now
     */
    public function __construct($fixture, $container, $label, $now)
    {
        $this->fixture = $fixture;
        $this->container = $container;
        $this->label = $label;
        $this->now = $now;
        $clz = $container->getModelClass();
        $this->model = new $clz;
        $this->fillRowModelAttributes();
    }

    /**
     * Convert to array. Just delegate to the fixture, since we update it during processing.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->fixture->toArray();
    }

    /**
     * Process the fixture.
     *
     * @return void
     */
    private function fillRowModelAttributes()
    {
        $this->fillTimestamps()
            ->interpolateLabel()
            ->generatePrimaryKey()
            ->resolveRelations();
    }

    /**
     * Fill in the timestamps if the model is using it. If a value is already specified in the fixture, we
     * don't overwrite it.
     *
     * @return $this
     */
    private function fillTimestamps()
    {
        if ($this->model->usesTimestamps())
        {
            foreach ([$this->model->getCreatedAtColumn(), $this->model->getUpdatedAtColumn()] as $stampColName)
            {
                $this->fixture[$stampColName] = $this->fixture[$stampColName] ?? $this->now;
            }
        }
        return $this;
    }

    /**
     * Replace the '$LABEL' token with the actual label in all the string attributes.
     *
     * @return $this
     */
    private function interpolateLabel()
    {
        $this->fixture->each(function ($key, $value)
        {
            if (is_string($value))
            {
                $this->fixture[$key] = str_replace('$LABEL', $this->label, $value);
            }
        });

        return $this;
    }

    /**
     * Generate a primary key if one is not supplied. Delegetes to FixtureSet::identify() for generation. We make a best
     * guess as to the type of the primary key by whether it's auto increment or not...
     *
     * @return $this
     */
    private function generatePrimaryKey()
    {
        $key = $this->model->getKeyName();
        if ($key != null && !isset($this->fixture[$key]))
        {
            $this->fixture[$key] = FixtureSet::identify($this->label, $this->model->incrementing ? 'integer' : 'uuid');
        }

        return $this;
    }

    /**
     * Process any relations defined. Slightly hokey way of determining what is a relation. Currently only supports BelongsTo
     * and MorphTo but many to manys are also possible. Has* probably won't be supported; those should be defined as the
     * inverse relation.
     *
     * @return $this
     *
     * @throws \Exception
     */
    private function resolveRelations()
    {
        $this->fixture->each(function ($key, $value) {
            if (method_exists($this->model, $key)) {
                $type = $this->model->{$key}();
                if ($type instanceof BelongsTo) {
                    $related = $type->getParent();
                    $fkName = method_exists($type, 'getForeignKeyName') ? $type->getForeignKeyName() : $type->getForeignKey();
                    $label = Arr::pull($this->fixture, $key);

                    if ($type instanceof MorphTo && preg_match('/(\S+)\s*\(([^\)]+)\)\s*$/', $label, $matches)) {
                        $label = $matches[1];
                        $this->fixture[$type->getMorphType()] = $matches[2];
                    }

                    $this->fixture[$fkName] = FixtureSet::identify($label, $related->incrementing ? 'integer' : 'uuid');
                } elseif ($type instanceof BelongsToMany) {
                    $pivotTableName = $type->getTable();
                    $related = $type->getParent();
                    $pivotRows = [];
                    $rawLabel = Arr::pull($this->fixture, $key);
                    $labels = is_array($rawLabel) ? $rawLabel : preg_split('/\s*,\s*/', $rawLabel);

                    foreach($labels as $label) {
                        // prevent morphedByMany direction
                        if ($type instanceof MorphToMany && $type->getInverse() == false) {
                            $pivotRow[$type->getMorphType()] = $type->getMorphClass();
                        }

                        $pivotRow[$type->getForeignPivotKeyName()] = $this->fixture[$this->model->getKeyName()];
                        $pivotRow[$type->getRelatedPivotKeyName()] =FixtureSet::identify($label, $related->incrementing ? 'integer' : 'uuid');
                        $pivotRows[] = $pivotRow;
                    }
                    $this->container->addRowsToTable($pivotTableName, $pivotRows);
                }
            }
        });

        return $this;
    }
}
