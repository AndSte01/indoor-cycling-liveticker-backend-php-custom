<?php

/**
 * Some functions that might be useful for working with results
 * 
 * @package Database\Utilities
 */

// assign namespace
namespace db\utils;

// define aliases
use errors;
use managerResultInterface;

// import required filed
require_once(dirname(__FILE__) . "/../managers/db_managers_result_interface.php");
require_once(dirname(__FILE__) . "/../../errors.php");

/**
 * Converts results errors to a string of errors defined in errors.php
 * 
 * @param int $error the error from managerResultInterface
 * @param bool $prepareDie Prepares the header for immediate call of die() afterwards
 * 
 * @return string the errors as a string
 */
function resultErrorsToString(int $error, bool $prepareDie = false): string
{
    switch ($error) {
        case managerResultInterface::ERROR_WRONG_DISCIPLINE_ID:
            return errors::to_error_string([errors::INVALID_PARENT], $prepareDie);

        case managerResultInterface::ERROR_OUT_OF_RANGE:
            return errors::to_error_string([errors::PARAM_OUT_OF_RANGE], $prepareDie);

        case managerResultInterface::ERROR_NOT_EXISTING:
            return errors::to_error_string([errors::NOT_EXISTING], $prepareDie);

        case managerResultInterface::ERROR_ALREADY_EXISTING:
            return errors::to_error_string([errors::ALREADY_EXISTS], $prepareDie);

        case managerResultInterface::ERROR_MISSING_INFORMATION:
            return errors::to_error_string([errors::MISSING_INFORMATION], $prepareDie);

        case managerResultInterface::ERROR_adaptor:
            return errors::to_error_string([errors::INTERNAL_ERROR], $prepareDie);

        default:
            return errors::to_error_string([errors::SUCCESS]);
    }
}
