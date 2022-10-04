<?php

/**
 * edit and get scoreboards via the internet
 * 
 * @package IO
 */

// set namespace
namespace IO\competition\scoreboards;

// define aliases
use DateTime;
use db\competition;
use db\user;
use errors;
use managerAuthentication;
use managerCompetition;
use managerUser;
use db\adaptorGeneric;
use db\scoreboard;
use managerScoreboard;
use function db\utils\authenticationErrorsToString;
use function db\utils\scoreboardErrorsToString;

// Error logging
// ini_set('display_errors', 1);
// error_reporting(E_ALL);

// import required files
require_once(dirname(__FILE__) . "/../../db/managers/manager_authentication.php");
require_once(dirname(__FILE__) . "/../../db/managers/manager_user.php");
require_once(dirname(__FILE__) . "/../../db/managers/manager_competition.php");
require_once(dirname(__FILE__) . "/../../db/managers/manager_scoreboard.php");
require_once(dirname(__FILE__) . "/../../db/utils/utils_error_converters.php");

// realm for authentication
$realm = "global";

// get params
$param_id = $_GET["id"];
$param_method = $_GET["method"];
$param_competition_id = $_GET["competition"];
$param_timestamp = $_GET["timestamp"];

// get content from body
$body_content = file_get_contents('php://input'); // empty if GET

// set correct content type
header("Content-Type: application/json");

// check wether method is in the desired range
if (!in_array($param_method, [null, "edit"])) {
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

// now check if parameters are provided according to method and also try if the can be converted to the correct types
switch ($param_method) {
    case "edit":
        // if we want to edit we need the id and the competition id
        if ($param_id === false) {
            die(errors::to_error_string([errors::MISSING_INFORMATION], true));
        }
        // no break elsewise default won't run

    default: // param competition id is always required
        if ($param_competition_id === false) {
            die(errors::to_error_string([errors::MISSING_INFORMATION], true));
        }
}

// connect to the database
$db = adaptorGeneric::connect();

// create and competition manager and check wether the competition (under the provided id exists)
$manager_competition = new managerCompetition($db);
$selected_competition =  $manager_competition->getCompetitionById($param_competition_id);
if ($selected_competition == null)
    die(errors::to_error_string([errors::NOT_EXISTING], true)); // die with error

// create the scoreboard and competition manager
$manager_scoreboard = new managerScoreboard($db, $param_competition_id);

// next handle all the requests only wanting to get scoreboards so they are out of the way.
if ($param_method == null) {
    // you need to search for scoreboards differently depending on which parameters are provided
    if ($param_id !== false) {
        $scoreboard = $manager_scoreboard->getScoreboardByExternalId($param_id);

        $db->close(); // close database connection

        // check wether a scoreboard was found or not
        if ($scoreboard == null)
            die(errors::to_error_string([errors::NOT_EXISTING], true)); // die with error

        // at this point we know a scoreboard was found
        die(json_encode($scoreboard, JSON_UNESCAPED_UNICODE)); // die with encoded json
    } else { // so $param_competition_id mustn't be null
        $scoreboards = $manager_scoreboard->getScoreboards($param_timestamp);

        $db->close(); // close database connection

        die(json_encode($scoreboards, JSON_UNESCAPED_UNICODE)); // encode the result to json and die with it
    }
}

// so now we know, the user wants to edit a scoreboard, and we have the scoreboard and it's parent
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

// now set the authenticated user in the competition manager, and check wether he has access to the competition selected by the user or not
$manager_competition->setCurrentUserId($manager_authentication->getCurrentUser()->{user::KEY_ID});
if ($manager_competition->userHasAccess($selected_competition->{competition::KEY_ID}) != 0) {
    $db->close(); // close database connection
    die(errors::to_error_string([errors::ACCESS_DENIED], true)); // die with error
}

// now we also know that the user has access to the competition and therefor scoreboard

// now edit the scoreboard in the database

// get scoreboard from the database
// TODO this is now done by the manager can be removed (after testing)
/*$selected_scoreboard = $manager_scoreboard->getScoreboardByExternalId($param_id);
if ($selected_scoreboard == null)
    die(errors::to_error_string([errors::NOT_EXISTING], true)); // die with error*/

// first try to decode and parse scoreboard

// decode json to assoc array
$decoded = json_decode($body_content, true);

// check if decode was possible
if (gettype($decoded) != "array") {
    $db->close(); // close database connection
    die(errors::to_error_string([errors::INVALID_JSON], true)); // die with error
}

// create scoreboard and parse data into it
$scoreboard = new scoreboard();
$parsing_errors = $scoreboard->parse(
    "",
    strval($param_id),
    "",
    strval($param_competition_id),
    strval($decoded[scoreboard::KEY_CONTENT]),
    strval($decoded[scoreboard::KEY_CUSTOM_TEXT])
);

// unset variables of fields that unsuccessfully parsed (only relevant for editing)
switch (true) { // inverted switch, don't use break since multiple errors could occur
        // ignored errors are: ERROR_CUSTOM_TEXT (and ERROR_INTERNAL_ID, ERROR_EXTERNAL_ID, ERROR_TIMESTAMP, ERROR_COMPETITION_ID since they aren't modified anyways)
    case $parsing_errors & scoreboard::ERROR_CONTENT:
        unset($decoded[scoreboard::KEY_CONTENT]);
}

// now edit the scoreboard in the database
$result = $manager_scoreboard->edit($scoreboard, array_keys($decoded));
die(scoreboardErrorsToString($result, true));
