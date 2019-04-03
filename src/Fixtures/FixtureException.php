<?php

namespace SilvertipSoftware\Fixtures;

use \Exception;

class FixtureException extends Exception {

    const FILE_NOT_FOUND = -1;
    const FORMAT_ERROR = -2;
    const DB_ERROR = -3;

    public function __construct($message, $code, $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}
