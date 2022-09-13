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
use db\adaptorCompetition;
use db\adaptorDiscipline;
use db\discipline;
use db\user;
use errors;
use managerAuthentication;
use managerCompetition;
use managerDiscipline;
use managerUser;
use mysqli;
use db\adaptorGeneric;
use db\result;
use managerResults;
use function db\utils\authenticationErrorsToString;
use function db\utils\competitionErrorsToString;
use function db\utils\resultErrorsToString;

// Error logging
// ini_set('display_errors', 1);
// error_reporting(E_ALL);

// import required files
require_once("db/managers/db_managers_authentication.php");
require_once("db/managers/db_managers_user.php");
require_once("db/managers/db_managers_discipline.php");
require_once("db/managers/db_managers_competition.php");
require_once("db/managers/db_managers_result.php");
require_once("db/utils/db_utils_competition.php");
require_once("db/utils/db_utils_authentication.php");

// realm for authentication
$realm = "global";

// get params
$param_method = $_GET["method"];
$param_discipline_id = $_GET["discipline"];
$param_timestamp = $_GET["timestamp"];

// get json from body
$json = file_get_contents('php://input'); // empty if GET

// set correct content type
header("Content-Type: application/json");


// check if discipline_id is (correctly) provided
if (filter_var($param_discipline_id, FILTER_VALIDATE_INT) !== false) {
    // if discipline_id is a number convert it to an int
    $param_discipline_id = intval($param_discipline_id);

    // check wether discipline_id is in rage
    if ($param_discipline_id < 1)
        die(errors::to_error_string([errors::PARAM_OUT_OF_RANGE], true));
} else {
    // if discipline_id is neither null nor a number return error
    die(errors::to_error_string([errors::MISSING_INFORMATION], true));
}


// check what the client wants to do

// if it is null the client wants to get disciplines
if ($param_method == null)
    die(getResults($param_discipline_id, $param_timestamp));

// $param_method is at this point obviously not null (so check if it contains an correct keyword)
// checked to prevent unnecessary code execution
if (!in_array($param_method, ["add", "edit", "remove"]))
    die(errors::to_error_string([errors::PARAM_OUT_OF_RANGE], true));


// all available methods require authentication and a user management so initiate that

// connect to database
$db = adaptorGeneric::connect();

// check if discipline exists
$discipline_to_use = adaptorDiscipline::search($db, $param_discipline_id)[0];

if ($discipline_to_use == null) {
    printf(errors::to_error_string([errors::INVALID_PARENT]));
    $db->close();
    exit();
}

// create user manager and authentication manager
$user_manager = new managerUser($db);
$authentication_manager = new managerAuthentication($user_manager, $realm);

// initiated login routine
$result = $authentication_manager->initiateLoginRoutine();

// check if login was successful, else die with error as string
if ($result != 0) {
    printf(authenticationErrorsToString($result));
    $db->close();
    exit();
}

// verify if user has access to desired competition (and discipline)
$competition_manager = new managerCompetition($db);
$competition_manager->setCurrentUserId($authentication_manager->getCurrentUser()->{user::KEY_ID});

$errors = $competition_manager->userHasAccess($discipline_to_use->{discipline::KEY_COMPETITION_ID});
if ($errors != 0) {
    printf(competitionErrorsToString($errors, true));
    $db->close();
    exit();
}

// decide what the user want's todo
switch ($param_method) {
    case "add":
        printf(parseVerifyModifyDiscipline($json, 0, $authentication_manager->getCurrentUser()->{user::KEY_ID}, $db));
        $db->close();
        exit();

    case "edit":
        printf(parseVerifyModifyDiscipline($json, 1, $authentication_manager->getCurrentUser()->{user::KEY_ID}, $db));
        $db->close();
        exit();

    case "remove":
        printf(parseVerifyModifyDiscipline($json, 2, $authentication_manager->getCurrentUser()->{user::KEY_ID}, $db));
        $db->close();
        exit();

    default:
        $db->close();
        exit();
}

// unnecessary but safe is safe
$db->close();
exit();


// --- functions used above ---

/**
 * Gets results from database
 * 
 * @param string $discipline_id The id of the discipline of whom results shall be returned
 * @param string $timestamp only get results that were modified (or added) after this timestamp
 * 
 * @return string String of a JSON array either containing the occurred errors or the results
 */
function getResults($discipline_id, $timestamp): string
{
    // work with the result manager to get the disciplines

    // connect to database
    $db = adaptorGeneric::connect();

    // create result manager
    $result_manager = new managerResults($db, $discipline_id);

    // get current timestamp from database (it is important to get it before asking for disciplines)
    $new_timestamp =  adaptorGeneric::getCurrentTime($db)->getTimestamp();

    // decide what to do
    switch (true) {
        case ($timestamp == ""): // if $timestamp is not set make it null
            $result = $result_manager->getResult();
            break;

        case (filter_var($timestamp, FILTER_VALIDATE_INT) !== false): // if timestamp is a number try to convert it to unix time
            // create new datetime, convert timestamp to int and apply timestamp to DateTime object
            // get results with the timestamp
            $result = $result_manager->getResult((new DateTime())->setTimestamp(intval($timestamp)));
            break;

        default: // if timestamp is neither null nor a number return error
            return errors::to_error_string([errors::NaN], true);
    }

    // merge new timestamp and result into array and return ist as json
    return json_encode(array_merge([$new_timestamp], $result), JSON_UNESCAPED_UNICODE);
}

/**
 * Adds, edits or removes a result to/from database
 * 
 * @param string $json JSON representation of the result to work with
 * @param int $action 0 = try to add the result, 1 = try to edit the given result, 2 = remove result
 * @param int $disciplineId The discipline_id to work with
 * @param mysqli $db The database to work with
 * 
 * @return string String ready for sending to client
 */
function parseVerifyModifyDiscipline(string $json, int $action, int $disciplineId, mysqli $db): string
{
    // decode json to assoc array
    $decoded = json_decode($json, true);

    // check if decode was possible
    if (gettype($decoded) != "array")
        return errors::to_error_string([errors::INVALID_JSON]);

    // create empty result and parse data
    $result = new result();
    $result->parse(
        $decoded[result::KEY_ID],
        $decoded[result::KEY_TIMESTAMP],
        $decoded[result::KEY_DISCIPLINE_ID],
        $decoded[result::KEY_START_NUMBER],
        $decoded[result::KEY_NAME],
        $decoded[result::KEY_CLUB],
        $decoded[result::KEY_SCORE_SUBMITTED],
        $decoded[result::KEY_SCORE_ACCOMPLISHED],
        $decoded[result::KEY_TIME],
        $decoded[result::KEY_FINISHED]
    );

    // use result manager to complete task
    $result_manager = new managerResults($db, $disciplineId);

    // either add, edit or remove result
    switch ($action) {
        case 0: // add
            // try to add competition to database
            $result_from_manager = $result_manager->add($result);

            // do error handling
            if (is_int($result_from_manager))
                return resultErrorsToString($result_from_manager, true);

            // if no error ocurred return discipline (in $result) as json
            return json_encode($result, JSON_UNESCAPED_UNICODE);

        case 1: // edit
            $result_from_manager = $result_manager->edit($result);
            return resultErrorsToString($result_from_manager, true);

        case 2: // remove
            $result_from_manager = $result_manager->remove($result);
            return resultErrorsToString($result_from_manager, true);
    }
}
