<?php

/**
 * work with scoreboard timers over the internet
 * 
 * @package IO
 */

// set namespace
namespace IO\competition\scoreboards\timers;

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
$param_at = $_GET["at"];
$param_count = $_GET["count"];

// get content from body
$body_content = file_get_contents('php://input'); // empty if GET

// set correct content type
header("Content-Type: application/json");

// param method mustn't be null
if ($param_method == null)
    die(errors::to_error_string([errors::PARAM_OUT_OF_RANGE], true));

// check wether method is in the desired range
if (!in_array($param_method, ["start", "stop", "reset"]))
    die(errors::to_error_string([errors::PARAM_OUT_OF_RANGE], true));

// try to convert parameters to the correct data types (FILTER_VALIDATE_INT doesn't accept null, two birds with one stone)
$param_id = filter_var($param_id, FILTER_VALIDATE_INT);
$param_competition_id = filter_var($param_competition_id, FILTER_VALIDATE_INT);
if ($param_count != null) // preserve null (important for correctly stopping timer)
    $param_count = filter_var($param_count, FILTER_VALIDATE_INT);
if ($param_at != null) //preserve null (important for correctly setting timer)
    $param_at = filter_var($param_at, FILTER_VALIDATE_INT);

// we always need the external id anf competition id to start or stop a timer
if ($param_id === false || $param_competition_id === false) {
    die(errors::to_error_string([errors::MISSING_INFORMATION], true));
}

// connect to the database
$db = adaptorGeneric::connect();

// create and competition manager and check wether the competition (under the provided id exists)
$manager_competition = new managerCompetition($db);
$selected_competition =  $manager_competition->getCompetitionById($param_competition_id);
if ($selected_competition == null)
    die(errors::to_error_string([errors::NOT_EXISTING], true)); // die with error

// to start or stop a timer you always need to authenticate

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

// create the scoreboard and competition manager
$manager_scoreboard = new managerScoreboard($db, $param_competition_id);

// now set the authenticated user in the competition manager, and check wether he has access to the competition selected by the user or not
$manager_competition->setCurrentUserId($manager_authentication->getCurrentUser()->{user::KEY_ID});
if ($manager_competition->userHasAccess($selected_competition->{competition::KEY_ID}) != 0) {
    $db->close(); // close database connection
    die(errors::to_error_string([errors::ACCESS_DENIED], true)); // die with error
}

// ok we have all the information we need and the user is authenticated and has access
switch ($param_method) {
    case "start":
        $result = $manager_scoreboard->startTimer($param_id, $param_at);
        break;

    case "stop":
        $result = $manager_scoreboard->stopTimer($param_id, $param_at, $param_count); // count is ignored if at is provided
        break;

    case "reset":
        $result = $manager_scoreboard->stopTimer($param_id, null, 0);
        break;
}

die(scoreboardErrorsToString($result, true));
