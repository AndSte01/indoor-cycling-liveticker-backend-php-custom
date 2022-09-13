<?php

/**
 * Interface used to define an user manager
 * 
 * @package Database\Managers
 */

// global namespace is desired

// define aliases
use db\user;

// add required database tools
require_once(dirname(__FILE__) . "/../representatives/db_representatives_user.php");

/**
 * Interface used for describing an user manager
 * 
 * Please note all actions (other than add) require a user provided in the argument that matches the user in the storage in name and password,
 * if it doesn't the action wont succeed and an error code will be returned
 */
interface managerUserInterface
{
    // Errors
    /** @var int Error happened at adaptor level */
    const ERROR_adaptor = 1;
    /** @var int There is no such user with this id (or id's don't match) */
    const ERROR_ID = 2;
    /** @var int There is no such user with this name (or names's don't match) */
    const ERROR_NAME = 4;
    /** @var int The passwords of the users don't match */
    const ERROR_PASSWORD = 8;
    /** @var int A user with the same name already exist */
    const ERROR_ALREADY_EXISTING = 16;
    /** @var int The username or password contained invalid characters */
    const ERROR_INVALID_CHARACTERS = 32;

    /**
     * Adds a user, name and password are defined in the input object of the user element
     * 
     * @param user $user The user to add
     * 
     * @return int Error code if user couldn't be added
     */
    public function add(user $user): int;

    /**
     * Removes the user provided as argument (name and password must match)
     * 
     * @param user $user The user to remove (identified by it's name)
     * 
     * @return int 0 on success or error code
     */
    public function remove(user $user): int;

    /**
     * Changes the name of the user provided as argument (name and password must match before the name will be changed)
     * 
     * @param user $userOld The user whose name and password should be changed
     * @param user $userNew The user containing the new name and password
     * 
     * @return int 0 on success or error code
     */
    public function edit(user $userOld, user $userNew): int;

    /**
     * Fetches a user by it's name
     * 
     * @param string $name Name of the user
     * 
     * @return ?user The user that was found
     */
    public function getUserByName(string $name): ?user;

    /**
     * Fetches a user by it's name
     * 
     * @param int $id Id of the user
     * 
     * @return ?user The user that was found
     */
    public function getUserById(int $id): ?user;
}
