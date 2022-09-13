<?php

/**
 * Add, edit, remove and get disciplines via the internet
 * 
 * The script to add, edit, remove and get disciplines from the web.
 * 
 * @package IO
 */

// set namespace
namespace IO\discipline;

// define aliases
use DateTime;
use db\adaptorGeneric;
use db\competition;
use db\discipline;
use db\user;
use errors;
use managerAuthentication;
use managerCompetition;
use managerDiscipline;
use managerUser;
use function db\utils\authenticationErrorsToString;
use function db\utils\disciplineErrorsToString;

// Error logging
// ini_set('display_errors', 1);
// error_reporting(E_ALL);

// import required files
require_once("errors.php");
require_once("db/adaptor/adaptor_generic.php");
require_once("db/managers/manager_discipline.php");
require_once("db/managers/manager_competition.php");
require_once("db/managers/manager_authentication.php");
require_once("db/managers/manager_user.php");
require_once("db/utils/utils_error_converters.php");

// realm for authentication
$realm = "global";

// get params
$param_id = $_GET["id"];
$param_method = $_GET["method"];
$param_competition_id = $_GET["competition"];
$param_timestamp = $_GET["timestamp"];

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
$param_competition_id = filter_var($param_competition_id, FILTER_VALIDATE_INT);
try {
    $param_timestamp = new DateTime("@" . $param_timestamp); // if creation fails it trows an error
} catch (\Throwable $th) {
    $param_timestamp = new DateTime("@0"); // create datetime at unix null
}


// now check if parameters are provided according to method nad also try if the can be converted to the correct types
switch ($param_method) {
    case null: // getting a discipline
        // we need either an id or a competition, timestamp is used but not required
        if ($param_id === false && $param_competition_id === false) {
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
        // if we want to add a discipline we need to know it's competition
        if ($param_competition_id === false) {
            die(errors::to_error_string([errors::MISSING_INFORMATION], true));
        }
        break;
}

// connect to the database
$db = adaptorGeneric::connect();

// create the discipline and competition manager
$manager_competition = new managerCompetition($db);
$manager_discipline = new managerDiscipline($db, 0);

// next handle all the requests only wanting to get disciplines so they are out of the way.
if ($param_method == null) {
    // you need to search for disciplines differently depending on which parameters are provided
    if ($param_id !== false) {
        $discipline = $manager_discipline->getDisciplineById($param_id); // get discipline from database

        $db->close(); // close database connection

        // check wether a discipline was found or not
        if ($discipline == null)
            die(errors::to_error_string([errors::NOT_EXISTING], true)); // die with error

        // at this point we know a discipline was found
        die(json_encode($discipline, JSON_UNESCAPED_UNICODE)); // die with encoded json
    } else { // so $param_competition_id mustn't be null
        // check if such competition exists
        if ($manager_competition->getCompetitionById($param_competition_id) == null) {
            $db->close(); // close database connection
            die(errors::to_error_string([errors::NOT_EXISTING], true)); // die with error
        }

        $manager_discipline->setCompetitionId($param_competition_id); // set competition id in discipline manager

        $disciplines = $manager_discipline->getDiscipline($param_timestamp); // search for disciplines

        $db->close(); // close database connection
        die(json_encode($disciplines, JSON_UNESCAPED_UNICODE)); // encode the result to json and die with it
    }
}

// next try to get additional data from the database (according to method)
switch ($param_method) {
    case "remove":
    case "edit":
        $selected_discipline = $manager_discipline->getDisciplineById($param_id);
        // now check if any discipline was found
        if ($selected_discipline == null) {
            $db->close();
            die(errors::to_error_string([errors::NOT_EXISTING], true)); // die with error
        }

        // now search for the competition (must exist due to the foreign key setup in the database)
        $selected_competition =  $manager_competition->getCompetitionById($selected_discipline->{discipline::KEY_COMPETITION_ID});
        break;

    case "add":
        // we only need the selected competition (and wether it exists or not)
        $selected_competition =  $manager_competition->getCompetitionById($param_competition_id);

        //check wether any competition was found
        if ($selected_competition == null)
            die(errors::to_error_string([errors::NOT_EXISTING], true)); // die with error

        break;
}

// so now we know, the user wants to add, edit or remove a discipline, we have the discipline and/or it's parent (the competition)
// and we have all parameters parsed to useful values, so let's get started with work.

// create user and authentication manager
$manager_user = new managerUser($db);
$manager_authentication = new managerAuthentication($manager_user, $realm);

// authenticate the user
// initiated login routine
$authentication_result = $manager_authentication->authenticate(managerAuthentication::AUTHENTICATION_METHOD_BEARER, 0);

// check if login was successful, else die with error as string
if ($authentication_result != 0) {
    printf(authenticationErrorsToString($result));
    $db->close();
    exit();
}

// now set the authenticate user in the competition manager, and check wether he has access to the competition selected by the user or not
$manager_competition->setCurrentUserId($manager_authentication->getCurrentUser()->{user::KEY_ID});
if ($manager_competition->userHasAccess($selected_competition->{competition::KEY_ID}) != 0) {
    $db->close(); // close database connection
    die(errors::to_error_string([errors::ACCESS_DENIED], true)); // die with error
}

// now we also know that the user has access to the competition and therefor discipline

// set the correct competition in the discipline manager
$manager_discipline->setCompetitionId($selected_competition->{competition::KEY_ID});

// if he just wants to remove the discipline get that out of the way
if ($param_method == "remove") {
    $errors = $manager_discipline->remove($selected_discipline); // remove discipline form database

    $db->close(); // close database connection
    die(disciplineErrorsToString($errors, true));
}

// now add or edit the discipline in the database

// first try to decode and parse discipline

// decode json to assoc array
$decoded = json_decode($body_content, true);

// check if decode was possible
if (gettype($decoded) != "array") {
    $db->close(); // close database connection
    die(errors::to_error_string([errors::INVALID_JSON], true)); // die with error
}

// create discipline and parse data into it
$discipline = new discipline();
$discipline->parse(
    strval($selected_discipline->{discipline::KEY_ID}),
    "", // timestamp is auto generated
    strval($selected_competition->{competition::KEY_ID}),
    strval($decoded[discipline::KEY_TYPE]),
    strval($decoded[discipline::KEY_FALLBACK_NAME]),
    strval($decoded[discipline::KEY_ROUND]),
    strval($decoded[discipline::KEY_FINISHED])
);

// unset variables of fields that unsuccessfully parsed (only relevant for editing)
switch (true) { // inverted switch, don't use break since multiple errors could occur
        // ignored errors are: ERROR_FALLBACK_NAME (and ERROR_ID, ERROR_TIMESTAMP, ERROR_COMPETITION_ID since they aren't modified anyways)
    case $parsing_errors & discipline::ERROR_TYPE:
        unset($decoded[discipline::KEY_TYPE]);

    case $parsing_errors & discipline::ERROR_ROUND:
        unset($decoded[discipline::KEY_ROUND]);

    case $parsing_errors & discipline::ERROR_FALLBACK_NAME:
        unset($decoded[discipline::KEY_FALLBACK_NAME]);
}

// either add, edit or remove discipline
switch ($param_method) {
    case "add":
        // try to add discipline to database
        $result = $manager_discipline->add($discipline);

        $db->close(); // close database connection

        // do error handling
        if (is_int($result))
            die(disciplineErrorsToString($result, true));

        // if no error ocurred die with discipline as json
        die(json_encode($result, JSON_UNESCAPED_UNICODE));

    case "edit": // edit
        $result = $manager_discipline->edit($discipline, array_keys($decoded));
        die(disciplineErrorsToString($result, true));
}
