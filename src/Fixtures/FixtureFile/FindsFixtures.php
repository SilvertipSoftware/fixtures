<?php

namespace SilvertipSoftware\Fixtures\FixtureFile;

use Illuminate\Filesystem\Filesystem;

trait FindsFixtures
{
    /**
     * Finds all fixture files in the given path and subdirectories. Names returned here are stripped of engine
     * suffixes for consistency.
     *
     * @param string  The path to search in
     * @return array
     */
    public static function findAllFixturesInPath($fixturePath)
    {
        $fs = new Filesystem;
        $fixtureSetNames = [];

        foreach (self::$extensions as $ext) {
            $glob = base_path($fixturePath) . '/*.' . $ext;
            $newFiles = array_map(function ($fsName) use ($ext) {
                preg_match('/.*\/([^\/]*)\.' . $ext . '$/', $fsName, $matches);
                return $matches[1];
            }, $fs->glob($glob));

            $fixtureSetNames = array_merge($fixtureSetNames, $newFiles);
        }

        return $fixtureSetNames;
    }

    /**
     * Finds all the fixture files for a given path. This includes any .yml/.php files at the top-level, and any
     * files in a subdirectory of the given name. Eg. passing 'tests/users' will return:
     *    tests/users.yml
     *    tests/users/admins.php
     *    tests/users/regular.yml
     *
     * @param string $path, including fixture set name
     * @return array
     */
    public static function findFixtureFilesAtPath($path) {
        $fs = new Filesystem;
        $files = [];

        foreach (self::$extensions as $extension) {
            $files = array_merge(
                $files,
                $fs->glob($path . '/*.' . $extension),
                $fs->glob($path . '.' . $extension)
            );
        }

        return $files;
    }
}
