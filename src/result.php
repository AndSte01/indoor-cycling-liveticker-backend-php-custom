<?php

/**
 * Add, edit, remove and get results via the internet
 * 
 * The script to add, edit, remove and get results from the web.
 * 
 * @package IO
 */

// set namespace
namespace IO\result;

// define aliases
use DateTime;
use db\adaptorGeneric;
use db\competition;
use db\discipline;
use db\user;
use db\result;
use errors;
use managerAuthentication;
use managerCompetition;
use managerDiscipline;
use managerUser;
use managerResult;
use function db\utils\authenticationErrorsToString;
use function db\utils\resultErrorsToString;

// Error logging
// ini_set('display_errors', 1);
// error_reporting(E_ALL);

// import required files
require_once("errors.php");
require_once("db/adaptor/adaptor_generic.php");
require_once("db/managers/manager_authentication.php");
require_once("db/managers/manager_user.php");
require_once("db/managers/manager_discipline.php");
require_once("db/managers/manager_competition.php");
require_once("db/managers/manager_result.php");
require_once("db/utils/utils_error_converters.php");

// realm for authentication
$realm = "global";

// get params
$param_id = $_GET["id"];
$param_method = $_GET["method"];
$param_discipline_id = $_GET["discipline"];
$param_timestamp = $_GET["timestamp"];

// get json from body
$json = file_get_contents('php://input'); // empty if GET

// get request body if post request
$body_content = file_get_contents('php://input'); // empty if GET

// set correct content type
header("Content-Type: application/json");

// check wether method is in the desired range
if (!in_array($param_method, [null, "add", "edit", "remove"])) {
    die(errors::to_error_string([errors::PARAM_OUT_OF_RANGE], true));
}

// try to convert parameters to the correct data types (FILTER_VALIDATE_INT doesn't accept null, two birds with one stone)
$param_id = filter_var($param_id, FILTER_VALIDATE_INT);
$param_discipline_id = filter_var($param_discipline_id, FILTER_VALIDATE_INT);
try {
    $param_timestamp = new DateTime("@" . $param_timestamp); // if creation fails it trows an error
} catch (\Throwable $th) {
    $param_timestamp = new DateTime("@0"); // create datetime at unix null
}

// now check if parameters are provided according to method nad also try if the can be converted to the correct types
switch ($param_method) {
    case null: // getting a result
        // we need either an id or a discipline, timestamp is not required
        if ($param_id === false && $param_discipline_id === false) {
            die(errors::to_error_string([errors::MISSING_INFORMATION], true));
        }
        break;

    case "remove":
    case "edit":
        // if we want to remove or add wee need the id
        if ($param_id === false) {
            die(errors::to_error_string([errors::MISSING_INFORMATION], true));
        }
        break;

    case "add":
        // if we want to add a discipline we need to know it's discipline
        if ($param_discipline_id === false) {
            die(errors::to_error_string([errors::MISSING_INFORMATION], true));
        }
        break;
}

// connect to the database
$db = adaptorGeneric::connect();

// create the result, discipline and competition manager
$manager_competition = new managerCompetition($db);
$manager_discipline = new managerDiscipline($db, 0);
$manager_result = new managerResult($db, 0);

// next handle all the request only wanting to get results so they are out of the way
if ($param_method == null) {
    // you need to search for results differently depending on which parameters are provided
    if ($param_id !== false) {
        $result = $manager_result->getResultById($param_id); // get result from database

        $db->close(); // close database connection

        // check wether a result was found or not
        if ($result == null)
            die(errors::to_error_string([errors::NOT_EXISTING], true)); // die with error

        // at this point we know a result was found
        die(json_encode($result, JSON_UNESCAPED_UNICODE)); // die with encoded json
    } else { // so $param_discipline_id mustn't be null
        // check if such discipline exists
        if ($manager_discipline->getDisciplineById($param_discipline_id) == null) {
            $db->close(); // close database connection
            die(errors::to_error_string([errors::NOT_EXISTING], true)); // die with error
        }

        $manager_result->setDisciplineId($param_discipline_id); // set discipline id in results manager

        $results = $manager_result->getResult($param_timestamp); // search for results

        $db->close(); // close database connection
        die(json_encode($results, JSON_UNESCAPED_UNICODE)); // encode the result to json and die with it
    }
}

// next get additional information form database (according to method)
switch ($param_method) {
    case "remove":
    case "edit":
        $selected_result = $manager_result->getResultById($param_id);
        // now check if any result was found
        if ($selected_result == null) {
            $db->close();
            die(errors::to_error_string([errors::NOT_EXISTING], true)); // die with error
        }

        // now search for the discipline (must exist due to the foreign key setup in the database)
        $selected_discipline = $manager_discipline->getDisciplineById($selected_result->{result::KEY_DISCIPLINE_ID});

        // now search for the competition (must exist due to the foreign key setup in the database)
        $selected_competition =  $manager_competition->getCompetitionById($selected_discipline->{discipline::KEY_COMPETITION_ID});
        break;

    case "add":
        // we need the selected discipline (and wether it exists or not)
        $selected_discipline = $manager_discipline->getDisciplineById($param_discipline_id);

        //check wether any discipline was found
        if ($selected_discipline == null)
            die(errors::to_error_string([errors::NOT_EXISTING], true)); // die with error

        // we also need the competition the discipline is assigned to (must exist due to the foreign key setup in the database)
        $selected_competition =  $manager_competition->getCompetitionById($selected_discipline->{discipline::KEY_COMPETITION_ID});
        break;
}

// so now we know, the user wants to add, edit or remove a result, we have the result and/or it's parents (the discipline and the competition)
// and we have all parameters parsed to useful values, so let's get started with work.

// create user and authentication manager
$manager_user = new managerUser($db);
$manager_authentication = new managerAuthentication($manager_user, $realm);

// authenticate the user
// initiate login routine
$authentication_result = $manager_authentication->authenticate(managerAuthentication::AUTHENTICATION_METHOD_BEARER, 0);

// check if login was successful, else die with error as string
if ($authentication_result != 0) {
    printf(authenticationErrorsToString($result));
    $db->close();
    exit();
}

// now set the authenticated user in the competition manager, and check wether he has access to the competition selected by the user or not
$manager_competition->setCurrentUserId($manager_authentication->getCurrentUser()->{user::KEY_ID});
if ($manager_competition->userHasAccess($selected_competition->{competition::KEY_ID}) != 0) {
    $db->close(); // close database connection
    die(errors::to_error_string([errors::ACCESS_DENIED], true)); // die with error
}

// now we know that the user has access to the competition and all it's children

// set the correct discipline id in the result manager
$manager_result->setDisciplineId($selected_discipline->{discipline::KEY_ID});

// if the user just wants to remove the result get that out of the way
if ($param_method == "remove") {
    $errors = $manager_result->remove($selected_result); // remove result form database

    $db->close(); // close database connection
    die(resultErrorsToString($errors, true));
}

// now add or edit the result in the database

// first try ti decode and parse result

// decode json to assoc array
$decoded = json_decode($body_content, true);

// check if decode was possible
if (gettype($decoded) != "array") {
    $db->close(); // close database connection
    die(errors::to_error_string([errors::INVALID_JSON], true)); // die with error
}

// create result and parse data into it
$result = new result();
$parsing_errors = $result->parse(
    strval($selected_result->{result::KEY_ID}),
    "", // timestamp is auto generated
    strval($selected_discipline->{discipline::KEY_ID}),
    strval($decoded[result::KEY_START_NUMBER]),
    strval($decoded[result::KEY_NAME]),
    strval($decoded[result::KEY_CLUB]),
    strval($decoded[result::KEY_SCORE_SUBMITTED]),
    strval($decoded[result::KEY_SCORE_ACCOMPLISHED]),
    strval($decoded[result::KEY_TIME]),
    strval($decoded[result::KEY_FINISHED])
);

// unset variables for fields that unsuccessfully parsed (only relevant for editing)
switch (true) { // inverted switch, don't use break since multiple errors could occur
        // ignored errors are: ERROR_NAME, ERROR_CLUB (and ERROR_ID, ERROR_TIMESTAMP, ERROR_DISCIPLINE_ID since they aren't modified anyways)
    case $parsing_errors & result::ERROR_START_NUMBER:
        unset($decoded[result::KEY_START_NUMBER]);

    case $parsing_errors & result::ERROR_SCORE_SUBMITTED:
        unset($decoded[result::KEY_SCORE_SUBMITTED]);

    case $parsing_errors & result::ERROR_SCORE_ACCOMPLISHED:
        unset($decoded[result::KEY_SCORE_ACCOMPLISHED]);

    case $parsing_errors & result::ERROR_TIME:
        unset($decoded[result::KEY_TIME]);

    case $parsing_errors & result::ERROR_FINISHED:
        unset($decoded[result::KEY_FINISHED]);
}

// either add or edit result
switch ($param_method) {
    case "add":
        // try to add result to database
        $return = $manager_result->add($result);

        $db->close(); // close database connection

        // do error handling
        if (is_int($return))
            die(resultErrorsToString($return, true));

        // if no error ocurred die with result as json
        die(json_encode($return, JSON_UNESCAPED_UNICODE));

    case "edit":
        $return = $manager_result->edit($result, array_keys($decoded));
        die(resultErrorsToString($return, true));
}
