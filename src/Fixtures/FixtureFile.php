<?php

namespace SilvertipSoftware\Fixtures;

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Helper for loading fixture files (YAML, currently).
 *
 * A configuration row can be defined by using the special label '_fixture'. The only
 * configuration parameter known is 'model_class' which is a namespaced classname of
 * the model.
 *
 * Default attributes are supported as well, using a YAML anchor with name 'DEFAULTS'.
 */
class FixtureFile
{

    /**
     * All the rows as parsed by YAML.
     *
     * @var array
     */
    protected $rawRows;

    /**
     * Cached configuration row, labelled '_fixture'.
     *
     * @var array
     */
    protected $configRow;

    /**
     * Cached data rows, ie. exclusive of the config row.
     *
     * @var array
     */
    protected $rows;

    /**
     * The label of the config row.
     *
     * @var string
     */
    const CONFIG_ROW_LABEL = '_fixture';

    /**
     * Parse a YAML file.
     *
     * @param  string $filename
     * @return FixtureFile
     */
    public static function open($fileName)
    {
        $file = new static;
        $file->rawRows = self::validatedContents($fileName);
        return $file;
    }

    /**
     * Iterate through the data rows. The callback gets label and row data.
     *
     * @param  closure $callback
     * @return void
     */
    public function each($callback)
    {
        foreach ($this->getRows() as $name => $row) {
            $callback($name, $row);
        }
    }

    /**
     * Gets the model classname if one is defined in the config row, null otherwise.
     *
     * @return string
     */
    public function getModelClass()
    {
        return $this->getConfigRow()['model_class'];
    }

    /**
     * Get, cache and return the data rows.
     *
     * @return array
     */
    public function getRows()
    {
        if (!$this->rows) {
            $this->rows = collect($this->rawRows)->except('_fixture')->toArray();
        }
        return $this->rows;
    }

    /**
     * Get, cache and return the config row. If none is present, provide a default.
     *
     * @return array
     */
    private function getConfigRow()
    {
        if (!$this->configRow) {
            $this->configRow = $this->rawRows['_fixture'] ?? ['model_class' => null];
        }
        return $this->configRow;
    }

    private static function validatedContents($fileName)
    {
        if (!is_file($fileName)) {
            throw new FixtureException("$fileName does not exist or is not a file", FixtureException::FILE_NOT_FOUND);
        }

        try {
            $contents = Yaml::parseFile($fileName) ?? [];
        } catch( ParseException $ex) {
            throw new FixtureException("File $fileName is not valid YAML", FixtureException::FORMAT_ERROR);
        }

        if (!is_array($contents)) {
            throw new FixtureException("FixtureSet $fileName is not a map", FixtureException::FORMAT_ERROR);
        }

        $invalidRows = collect($contents)->reject(function ($row, $label) {
            if (is_array($row)) {
                foreach(array_keys($row) as $key) {
                    if (!is_string($key)) {
                        return false;
                    }
                }
                return true;
            }
            return false;
        });

        if ($invalidRows->count() > 0) {
            throw new FixtureException("FixtureSet $fileName contains invalid maps for keys: " . implode(', ', $invalidRows->keys()->toArray()),
                FixtureException::FORMAT_ERROR);
        }

        return $contents;
    }
}
