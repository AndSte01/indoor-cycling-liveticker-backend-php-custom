<?php

/**
 * Interface used to define an authentication manager
 * 
 * @package Database\Managers
 */

// define aliases
use db\user;

// add required database tools
require_once(dirname(__FILE__) . "/../representatives/db_representatives_user.php");
require_once(dirname(__FILE__) . "/../../errors.php");


/**
 * Interface used for describing an authentication manager
 */
interface managerAuthenticationInterface
{
    // Errors that might happen during authentication
    /** @var int Error if user was unwilling to provide authentication */
    public const ERROR_DISMISSED_AUTHENTICATION = 1;
    /** @var int Error if the Username doesn't exists */
    public const ERROR_NO_SUCH_USER = 2;
    /** @var int Error if the password isn't correct */
    public const ERROR_INVALID_PASSWORD = 4;
    /** @var int Client didn't respond with a correct authentication header */
    public const ERROR_INVALID_RESPONSE = 8;
    /** @var int A new authentication was forced by a previous call of logout(), This is not an error but desired behavior */
    public const ERROR_FORCED_AUTHENTICATION = 16;

    /**
     * Returns the database id of the current user
     * 
     * @return user The current user
     */
    public function getCurrentUser(): ?user;

    /**
     * Checks wether a user is logged in or not
     * 
     * @return bool Wether a user is logged in or not
     */
    public function isLoggedIn(): bool;

    /**
     * Initiates a login routine.
     * 
     * @return int Error that happened during routine
     */
    public function initiateLoginRoutine(): int;

    /**
     * Logs the current user out.
     */
    public function logout(): void;
}
