<?php

/**
 * Add, edit, remove and get competitions via the internet
 * 
 * The script to add, edit, remove and get competitions from the web.
 * 
 * @package IO
 */

// set namespace
namespace IO\competition;

// define aliases
use db\competition;
use db\user;
use errors;
use managerAuthentication;
use managerCompetition;
use managerUser;
use mysqli;
use db\adaptorGeneric;
use function db\utils\authenticationErrorsToString;
use function db\utils\competitionErrorsToString;

// some constants used later
const GET_COMPETITIONS_LIMIT_DEFAULT = 10;
const GET_COMPETITIONS_LIMIT_MAX = 100;

// Error logging
// ini_set('display_errors', 1);
// error_reporting(E_ALL);

// import required files
require_once("db/managers/manager_authentication.php");
require_once("db/managers/manager_user.php");
require_once("db/managers/manager_competition.php");
require_once("db/utils/utils_error_converters.php");

// realm for authentication
$realm = "global";

// get params
$param_method = $_GET["method"];
$param_days = $_GET["days"];
$param_limit = $_GET["limit"];
$param_id = $_GET["id"];

// get json from body
$json = file_get_contents('php://input'); // empty if GET

// set correct content type
header("Content-Type: application/json");

// check what the client wants to do

// if it is null the client wants to get competitions
if ($param_method == null) {
    switch ($param_id) {
        case null:
            die(getCompetitionsGeneric($param_days, $param_limit));
            break;

        default:
            die(getCompetitionsId($param_id));
            break;
    }
}

// $param_method is at this point obviously not null (so check if it contains an correct keyword)
// checked to prevent unnecessary code execution
if (!in_array($param_method, ["add", "edit", "remove"]))
    die(errors::to_error_string([errors::PARAM_OUT_OF_RANGE], true));

// all available methods require authentication and a user management so initiate that

// connect to database
$db = adaptorGeneric::connect();

// create user manager and authentication manager
$user_manager = new managerUser($db);
$authentication_manager = new managerAuthentication($user_manager, $realm);

// initiated login routine
$authentication_result = $authentication_manager->authenticate(managerAuthentication::AUTHENTICATION_METHOD_BEARER, 0);

// check if login was successful, else die with error as string
if ($authentication_result != 0) {
    printf(authenticationErrorsToString($result));
    $db->close();
    exit();
}

// check the id parameter in case the user wants to edit or remove the competition
if (in_array($param_method, ["edit", "remove"])) {
    // check if id is provided and wether it is an int or not
    if ($param_id == null) {
        printf(errors::to_error_string([errors::MISSING_INFORMATION], true));
        $db->close();
        exit();
    }
    if (filter_var($param_id, FILTER_VALIDATE_INT) === false) {
        printf(errors::to_error_string([errors::NaN], true));
        $db->close();
        exit();
    }
}

// decide what the user want's todo
switch ($param_method) {
    case "add":
        printf(parseVerifyModifyCompetition($json, 0, $authentication_manager->getCurrentUser()->{user::KEY_ID}, 0, $db));
        $db->close();
        exit();

    case "edit":
        printf(parseVerifyModifyCompetition($json, 1, $authentication_manager->getCurrentUser()->{user::KEY_ID}, intval($param_id), $db));
        $db->close();
        exit();

    case "remove":
        // this is done here since parseVerifyModifyCompetition would require a json send by client
        $competition_manager = new managerCompetition($db);
        $competition_manager->setCurrentUserId($authentication_manager->getCurrentUser()->{user::KEY_ID});

        $temp_competition = new competition();
        $temp_competition->parse($param_id);

        printf(competitionErrorsToString($competition_manager->remove($temp_competition, true), true));

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
 * Gets competition from database
 * 
 * @param string $daysSinceToday how many days back competitions should be displayed
 * @param string $limit how many competitions should be returned (default GET_DEFAULT_COMPETITIONS_LIMIT)
 * 
 * @return string String of a JSON array either containing the occurred errors or the competitions
 */
function getCompetitionsGeneric($daysSinceToday, $limit): string
{
    // check variables, it is very important to correctly interpret null

    // use switch inversion trick
    switch (true) {
        case ($daysSinceToday == ""): // if $day is not set make variable null
            $daysSinceToday = null;
            break;

        case (filter_var($daysSinceToday, FILTER_VALIDATE_INT) !== false): // if date is a number convert it to an int
            $daysSinceToday = abs(intval($daysSinceToday)); // ignore negative values
            break;

        default: // if date is neither null nor a number return error
            return errors::to_error_string([errors::NaN], true);
            break;
    }

    switch (true) {
        case ($limit == ""): // if $limit is not set make variable null
            $limit = GET_COMPETITIONS_LIMIT_DEFAULT; // default limit
            break;

        case (filter_var($limit, FILTER_VALIDATE_INT) !== false): // if limit is a number convert it to an int
            $limit = intval($limit);

            // put limit in maximal range
            $limit = $limit > GET_COMPETITIONS_LIMIT_MAX ? GET_COMPETITIONS_LIMIT_MAX : $limit;
            break;

        default: // if limit is neither null nor a number return error
            return errors::to_error_string([errors::NaN], true);
            break;
    }

    // work with the competition manager to get the competitions

    // connect to database
    $db = adaptorGeneric::connect();

    // create competition manager
    $competition_manager = new managerCompetition($db);

    // try to get competitions
    $result = $competition_manager->getCompetitionsGeneric($daysSinceToday, $limit);

    // return the result
    return json_encode($result, JSON_UNESCAPED_UNICODE);
}

/**
 * Gets a single competition by looking up his id
 * 
 * @param string $id the id of the competition one wants to get
 * 
 * @return string String of an json array either containing the occurred errors or the competitions
 */
function getCompetitionsId($id): string
{
    // validate input variables

    // check if id is an int
    if (filter_var($id, FILTER_VALIDATE_INT) !== false) {
        $id = intval($id);
    } else {
        return errors::to_error_string([errors::NaN], true);
    }

    // check if id is in valid range
    if ($id < 1) {
        return errors::to_error_string([errors::PARAM_OUT_OF_RANGE], true);
    }

    // connect to database
    $db = adaptorGeneric::connect();

    // create competition manager
    $competition_manager = new managerCompetition($db);

    // try to get competitions
    $result = $competition_manager->getCompetitionById($id);

    if ($result == null) {
        return errors::to_error_string([errors::NOT_EXISTING]);
    }

    // return the result
    return json_encode($result, JSON_UNESCAPED_UNICODE);
}

/**
 * Adds, edits or removes a competitions to/from database
 * 
 * @param string $json JSON representation of the competition to work with
 * @param int $action 0 = try to add the competition, 1 = try to edit the given competition
 * @param int $userId The user id to work with
 * @param int $competition_id The id of the competition, only required when editing competition
 * @param mysqli $db The database to work with
 * 
 * @return string String ready for sending to client
 */
function parseVerifyModifyCompetition(string $json, int $action, int $userId, int $competition_id = 0, mysqli $db): string
{
    // decode json to assoc array
    $decoded = json_decode($json, true);

    // check if decode was possible
    if (gettype($decoded) != "array")
        return errors::to_error_string([errors::INVALID_JSON], true);

    // create empty competition to parse data to
    $competition = new competition();
    $parsing_errors = $competition->parse(
        strval($competition_id),
        strval($decoded[competition::KEY_DATE]),
        strval($decoded[competition::KEY_NAME]),
        strval($decoded[competition::KEY_LOCATION]),
        "", // no user id required all done by authentication manager
        strval($decoded[competition::KEY_AREAS]),
        strval($decoded[competition::KEY_FEATURE_SET]),
        strval($decoded[competition::KEY_LIVE])
    );

    // unset variables of fields that unsuccessfully parsed (only relevant for editing)
    switch (true) { // inverted switch, don't use break since multiple errors could occur
            // ignored errors are: ERROR_NAME, ERROR_LOCATION (and ERROR_USER, ERROR_ID since they aren't modified anyways)
        case $parsing_errors & competition::ERROR_DATE:
            unset($decoded[competition::KEY_DATE]);

        case $parsing_errors & competition::ERROR_AREAS:
            unset($decoded[competition::KEY_AREAS]);

        case $parsing_errors & competition::ERROR_FEATURE_SET:
            unset($decoded[competition::KEY_FEATURE_SET]);

        case $parsing_errors & competition::ERROR_LIVE:
            unset($decoded[competition::KEY_LIVE]);
    }

    // use competition manager to complete task
    $competition_manager = new managerCompetition($db);

    // set the user id in competition manager
    $competition_manager->setCurrentUserId($userId);

    // either add, edit or remove competition
    switch ($action) {
        case 0: // add
            // try to add competition to database
            $result = $competition_manager->add($competition);

            // do error handling
            if (is_int($result))
                return competitionErrorsToString($result, true);

            // if no error ocurred return competition (in $result) as json
            return json_encode($result, JSON_UNESCAPED_UNICODE);

        case 1: // edit
            $result = $competition_manager->edit($competition, array_keys($decoded));
            return competitionErrorsToString($result, true);

            /*case 2: // remove
            $result = $competition_manager->remove($competition);
            return competitionErrorsToString($result, true);*/
    }
}
