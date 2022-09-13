<?php

/**
 * Enables one to create the required tables from remote
 * 
 * @package IO
 */

// define aliases
use db\adaptorGeneric;

// Error logging
// ini_set('display_errors', 1);
// error_reporting(E_ALL);

// add required database tools
require_once("db/adaptor/adaptor_generic.php");
require_once("errors.php");

// set correct header
header("Content-Type: application/json");

// get first param (following params will be ignored)
$param = array_key_first($_GET);

// execute desired action
switch ($param) {
    case "create":
        // connect to database and create tables
        $error = adaptorGeneric::createTables($db = adaptorGeneric::connect());
        break;

    case "drop":
        // connect to database and truncate tables
        $error = adaptorGeneric::dropTables($db = adaptorGeneric::connect());
        break;

    default:
        // exit script early with error
        die(errors::to_error_string([errors::INVALID_REQUEST], true));
        break;
}

// move on with checking the outcome

// echo errors or success message
if ($error != null)
    echo $error;
else
    echo errors::to_error_string([errors::SUCCESS]);

// disconnect form database
$db->close();
