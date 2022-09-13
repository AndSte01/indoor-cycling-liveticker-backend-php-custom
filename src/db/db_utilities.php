<?php

/**
 * Functions to interact with Database
 * 
 * Script containing the functions zu interact with the db safely
 * 
 * @package Database\Utilities
 */

/**
 * Removes illegal characters form string
 * 
 * @param string $input string to clean
 * @return string cleaned string
 * 
 * @todo check if used, elsewise remove it
 */
function cleanString(string $input): string
{
    // Remove special character from user
    $result = preg_replace("/[%\"\'|<>]/", "", $input);
    // Remove double spaces
    $result = preg_replace('/\s\s+/', ' ', $result);

    return $result;
}
