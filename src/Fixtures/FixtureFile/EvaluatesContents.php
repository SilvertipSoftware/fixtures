<?php

namespace SilvertipSoftware\Fixtures\FixtureFile;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\Yaml\Yaml;

/**
 * Evaluates and parses contents of fixture files. Currently, handles raw yaml and PHP that evals to yaml.
 *
 * PHP files need an extra space after any closing ?>, otherwise the yaml will probably be invalid.
 */
trait EvaluatesContents
{
    /**
     * Returns the evaluated contents of the file. No validation is done at this point (other than yaml syntax).
     *
     * @param  $path  The filename to evaluate
     * @return array|string
     */
    protected function getContents($path)
    {
        $contents = null;

        if (! $extension = $this->getExtension($path)) {
            throw new InvalidArgumentException("Unrecognized extension in file: {$path}.");
        }

        switch ($extension) {
            case 'php':
                $str = $this->evaluatePath($path);
                $contents = Yaml::parse($str);
                break;
            case 'yml':
                $contents = Yaml::parseFile($path);
                break;
            default:
                throw new InvalidArgumentException("Unknown fixture extension " . $extension . " for " . $path);
                break;
        }

        return isset($contents)
            ? $contents
            : [];
    }

    /**
     * Get the evaluated contents of the file at the given path.
     *
     * @param  string  $path
     * @return string
     */
    private function evaluatePath($path)
    {
        $obLevel = ob_get_level();
        ob_start();

        try {
            (new Filesystem)->getRequire($path);
        } catch (Throwable $e) {
            throw new InvalidArgumentException("Evaluation error in fixture file " . $path);
        }

        return ob_get_clean();
    }

    /**
     * Get the extension of the file so we know what we are dealing with.
     *
     * @param string $path
     * @return string|null
     */
    private function getExtension($path)
    {
        return Arr::first(static::$extensions, function ($value) use ($path) {
            return Str::endsWith($path, '.' . $value);
        });
    }
}
