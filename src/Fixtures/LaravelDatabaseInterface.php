<?php

namespace SilvertipSoftware\Fixtures;

class LaravelDatabaseInterface implements DatabaseInterface
{
    /**
     * Wipes a database table of all records
     *
     * @param string $connection The name of the connection
     * @param string $table      The name of the table to wipe
     */
    public function wipe($connection, $table)
    {
        \DB::connection($connection)->table($table)->delete();
    }

    /**
     * Bulk insert rows into a given table
     *
     * @param string $connection The name of the connection
     * @param string $table      The name of the table to wipe
     * @param array  $rows       The rows to insert as a column_name => value map
     */
    public function insert($connection, $table, $rows)
    {
        \DB::connection($connection)->table($table)->insert($rows);
    }

    /**
     * Finds and returns a model.
     *
     * @param string $clz The model class
     * @param string $key The primary key
     * @return Eloquent\Model instance, or null if not found
     */
    public function findModel($clz, $key)
    {
        return $clz ? $clz::find($key) : null;
    }
}