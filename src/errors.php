<?php

/**
 * List of possible errors, their codes and a conversion function
 * 
 * This file contains a list of errors that can happen, error codes and a function to convert
 * errors from the list to an error code.
 * 
 * @package errors
 */


/**
 * Class containing possible errors
 */
class errors
{
    /** @var string no error occurred, action succeeded */
    const SUCCESS = "SUCCESS";
    /** @var string some information that is required is missing */
    const MISSING_INFORMATION = "MISSING_INFORMATION";
    /** @var string one of the parameters is out of range */
    const PARAM_OUT_OF_RANGE = "PARAM_OUT_OF_RANGE";
    /** @var string something that should be a number isn't */
    const NaN = "NaN";
    /** @var string what you were looking for doesn't exist */
    const NOT_EXISTING = "NOT_EXISTING";
    /** @var string the json input couldn't be decoded */
    const INVALID_JSON = "INVALID_JSON";
    /** @var string  access for the desired resource was denied */
    const ACCESS_DENIED = "ACCESS_DENIED";
    /** @var string authentication information is required but wasn't send */
    const AUTHENTICATION_REQUIRED = "AUTHENTICATION_REQUIRED";
    /** @var string the resource you're trying to add already exists */
    const ALREADY_EXISTS = "ALREADY_EXISTS";
    /** @var string the request made is invalid (in a very generic way) */
    const INVALID_REQUEST = "INVALID_REQUEST";
    /** @var string the request contained invalid data */
    const INVALID_DATA = "INVALID_DATA";
    /** @var string your request response contained invalid characters */
    const INVALID_CHARACTERS = "INVALID_CHARACTERS";
    /** @var string the server encountered an internal error */
    const INTERNAL_ERROR = "INTERNAL_ERROR";
    /** @var string the elements parent isn't the parent you specified, or the parent you wanted to attach the child to doesn't exist */
    const INVALID_PARENT = "INVALID_PARENT";
    /** @var string the input was invalid (for some undefined reason) */
    const INVALID_INPUT = "INVALID_INPUT";

    /**
     * Convert an array of different errors into an string
     * 
     * @param array $errors array of errors
     * @param bool $prepareDie Prepares the header for immediate call of die() afterwards
     * 
     * @return string string representation (JSON array) of errors
     */
    public static function to_error_string(array $errors = [], bool $prepareDie = false): string
    {
        // prepares header response code
        if ($prepareDie)
            http_response_code(400);

        return json_encode($errors);
    }
}

/**
 * Definitions of error codes
 * 
 * @todo jet to implement
 */
class errors_codes
{
    const SUCCESS = 0;


    /**
     * Convert an array of different errors into an error code
     * 
     * @param array $errors array of errors
     * @return int error code
     * 
     * @todo jet to implement
     */
    public static function to_error_code(array $errors = []): int
    {
        return 0;
    }
}
