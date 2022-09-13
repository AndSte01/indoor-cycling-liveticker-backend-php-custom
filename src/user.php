<?php

/**
 * Add, Update and get users via the internet
 * 
 * The script to add, update and get users from the web.
 * 
 * @package IO
 */

// set namespace
namespace IO\user;

// define aliases
use errors;
use managerAuthentication;
use managerUser;
use db\adaptorGeneric;
use db\user;

use const db\utils\KEY_USER_NAME;
use const db\utils\KEY_USER_PASSWORD;

use function db\utils\authenticationErrorsToString;
use function db\utils\userErrorsToString;
use function db\utils\parseVerifyUser;

// Error logging
// ini_set('display_errors', 1);
// error_reporting(E_ALL);

// import required files
require_once("db/managers/manager_authentication.php");
require_once("db/managers/manager_user.php");
require_once("db/adaptor/adaptor_generic.php");
require_once("db/utils/utils_user.php");
require_once("db/utils/utils_error_converters.php");

// realm for authentication
$realm = "global";

// get params
$param_method = $_GET["method"];

// get json from body
$json = file_get_contents('php://input'); // empty if GET

// set correct content type
header("Content-Type: application/json");

// check what the client wants to do

// check if method has valid options
if (!in_array($param_method, [null, "add", "edit", "remove", "logout"]))
    die(errors::to_error_string([errors::PARAM_OUT_OF_RANGE], true));

// connect to the database
$db = adaptorGeneric::connect();

// if the client wants to add a user no authentication is required
if ($param_method == "add") {
    // try to parse json
    $user = parseVerifyUser($json, true);

    // check if any error string was returned, and die with the error string
    if (is_string($user)) {
        printf($user);
        $db->close();
        exit();
    }

    // now we know, the user was parsed successfully

    // create user manager (create it only after we know we have valid data)
    $user_manager = new managerUser($db);

    // try to add user to the database
    $error = $user_manager->add($user[KEY_USER_NAME], $user[KEY_USER_PASSWORD], 0);

    //check for any errors
    if ($error != 0) {
        printf(userErrorsToString($error, true));
        $db->close();
        exit();
    }

    // if no errors happened die with success message
    $db->close();
    die(errors::to_error_string([errors::SUCCESS]));
}

// all other actions require authentication

// create user and authentication manager
$user_manager = new managerUser($db);
$authentication_manager = new managerAuthentication($user_manager, $realm);

// if the user wants to logout do it before initiating new login routine
if ($param_method == "logout") {
    $authentication_manager->logout();
    // also allow authentication with any method for once (but prefer basic)
    $result = $authentication_manager->authenticate(managerAuthentication::AUTHENTICATION_METHOD_ANY);
} else {
    // initiated login routine using basic authentication
    $result = $authentication_manager->authenticate(managerAuthentication::AUTHENTICATION_METHOD_BASIC);
}

// check if login was successful, else die with error as string
if ($result != 0) {
    printf(authenticationErrorsToString($result));
    $db->close();
    exit();
}

// decide how to proceed
switch ($param_method) {
    case null:
        // if method is null, a successful authentication is all we are looking for
        // so return the bearer token and die
        printf($authentication_manager->getCurrentToken());
        $db->close();
        exit();

    case "remove":
        // get the currently logged in user and remove it
        $user_manager->remove($authentication_manager->getCurrentUser()->{user::KEY_ID});

        // close db and return success
        $db->close();
        die(errors::to_error_string([errors::SUCCESS]));

    case "edit":
        // decode json to assoc array
        $user = json_decode($json, true);

        // check if decode was possible
        if (gettype($user) != "array") {
            $db->close();
            die(errors::to_error_string([errors::INVALID_JSON], true));
        }

        // check if password was provided and if so set it as new password
        if ($user[KEY_USER_PASSWORD] != null) {
            $errors = $user_manager->editPassword($authentication_manager->getCurrentUser()->{user::KEY_NAME}, $user[KEY_USER_PASSWORD]);
        } else {
            $db->close();
            die(errors::to_error_string([errors::MISSING_INFORMATION], true));
        }

        // Note: even though the user manager supports it, we currently don't implement changing of roles

        //check for any errors
        if ($error != 0) {
            printf(userErrorsToString($error));
            $db->close();
            exit();
        }

        // if no errors happened die with success message
        $db->close();
        die(errors::to_error_string([errors::SUCCESS]));
}

// unnecessary but safe is safe
exit();
