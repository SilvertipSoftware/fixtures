<?php

namespace SilvertipSoftware\Fixtures;

use \Exception;

class FixtureException extends Exception {

    const FILE_NOT_FOUND = -1;
    const FORMAT_ERROR = -2;

    public function __construct($message, $code) {
        parent::__construct($message, $code);
    }
}
