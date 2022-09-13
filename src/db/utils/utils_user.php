<?php

/**
 * Some functions that might be useful for working with authentication
 * 
 * @package Database\Utilities
 */

// assign namespace
namespace db\utils;

// define aliases
use errors;

// import required filed
require_once(dirname(__FILE__) . "/../../errors.php");

// define constant for user name and password
// (those are set here since the key in the representative make no sense
// e.g. there is no password key only hashes are stored)
/** @var string Key used for transferring user passwords */
const KEY_USER_PASSWORD = "password";
/** @var string Key used for transferring user names */
const KEY_USER_NAME =  "name";

/**
 * parses and verifies an user
 * 
 * @param string $json JSON representation of the user to add or update
 * @param bool $prepareDie sets response code to 400 if true
 * 
 * @return string|array Either the error string or a array containing username and password (see KEY_USER_NAME, KEY_USER_PASSWORD)
 */
function parseVerifyUser(string $json, bool $prepareDie = false): string|array
{
    // decode json to assoc array
    $decoded = json_decode($json, true);

    // check if decode was possible
    if (gettype($decoded) != "array")
        return errors::to_error_string([errors::INVALID_JSON], $prepareDie);

    // check if decode contained all required fields
    if ($decoded[KEY_USER_NAME] == null || $decoded[KEY_USER_PASSWORD] == null)
        return errors::to_error_string([errors::MISSING_INFORMATION], $prepareDie);

    return $decoded;
}
