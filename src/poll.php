<?php

/**
 * Add, edit, remove and get disciplines via the internet
 * 
 * The script to add, edit, remove and get disciplines from the web.
 * 
 * @package IO
 */

// set namespace
namespace IO;

// define aliases
use DateTime;
use db\adaptorGeneric;
use errors;
use managerCompetition;
use managerDiscipline;
use managerResults;

// Error logging
// ini_set('display_errors', 1);
// error_reporting(E_ALL);

// import required files
require_once("db/managers/db_managers_competition.php");
require_once("db/managers/db_managers_discipline.php");
require_once("db/managers/db_managers_result.php");
require_once("db/adaptor/adaptor_generic.php");
require_once("errors.php");

// get params
$param_method = $_GET["method"];
$param_competition_id = $_GET["competition"];
$param_timestamp = $_GET["timestamp"] ?? 0; // if no timestamp is provided assume 0

// set correct content type
header("Content-Type: application/json");

// check if competition id is (correctly) provided
if (filter_var($param_competition_id, FILTER_VALIDATE_INT) !== false) {
    // if competition_id is a number convert it to an int
    $param_competition_id = intval($param_competition_id);

    // check if competition id is in rage
    if ($param_competition_id < 1)
        die(errors::to_error_string([errors::PARAM_OUT_OF_RANGE], true));
} else {
    // if competition_id is neither null nor a number return error
    die(errors::to_error_string([errors::MISSING_INFORMATION], true));
}

// check if timestamp is correctly provided
if (filter_var($param_timestamp, FILTER_VALIDATE_INT) !== false) {
    // if timestamp is a number try to convert it to unix time
    // create new datetime, convert timestamp to int and apply timestamp to DateTime object
    $param_timestamp = (new DateTime())->setTimestamp(intval($timestamp));
} else {
    // timestamp is not a number
    die(errors::to_error_string([errors::NaN], true));
}

// connect to the database
$db = adaptorGeneric::connect();

// check if competition exists
$competition_manager = new managerCompetition($db);
if ($competition_manager->getCompetitionById($param_competition_id) == null) {
    printf(errors::to_error_string([errors::NOT_EXISTING], true));
    $db->close();
    exit();
}

// get current timestamp from database (it is important to get it before performing any requests)
$new_timestamp =  adaptorGeneric::getCurrentTime($db)->getTimestamp();

// get for changed disciplines
$discipline_manager = new managerDiscipline($db, $param_competition_id);
$disciplines = $discipline_manager->getDiscipline($timestamp);

// get changed results
$result_manager = new managerResults($db);
$results = $result_manager->getResultByCompetition($param_competition_id);

// close database connection
$db->close();

// print out results as json array
die(json_encode([$new_timestamp, $disciplines, $results]));
