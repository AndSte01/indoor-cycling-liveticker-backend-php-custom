<?php

/**
 * A manager for users
 * 
 * @package Database\Managers
 */

// global namespace is desired

// define aliases
use db\user;
use db\adaptorUser;

// add required database tools
require_once(dirname(__FILE__) . "/../adaptor/adaptor_user.php");
require_once(dirname(__FILE__) . "/../representatives/db_representatives_user.php");
require_once("db_managers_user_interface.php");

/**
 * A class implementing an user management
 * 
 * @todo if update to php 8 use unions
 */
class managerUserOld implements managerUserInterface
{
    /** @var mysqli Database to work with */
    protected mysqli $db;

    /**
     * Constructor
     * 
     * @param mysqli $db The database the users are stored in
     * 
     * @throws Exception if database is null
     */
    public function __construct(mysqli $db)
    {
        if ($db == null)
            throw new Exception("database mustn't be null", 1);

        $this->db = $db;
    }

    // explained in the userManagerInterface
    public function add(user $user): int
    {
        // make the new user ready for the database
        $errors = $user->makeDbReady($this->db);

        // check if any invalid characters wer in the username (id doesn't matter)
        if ($errors & user::ERROR_NAME || $errors & user::ERROR_PASSWORD)
            return self::ERROR_INVALID_CHARACTERS;

        // check if a user with same name already exist
        // if one was found the return isn't null and the user mustn't be added
        if ($this->getUserByName($user->{user::KEY_NAME}) != null)
            return self::ERROR_ALREADY_EXISTING;

        // no such user exist add it to the database
        $result = adaptorUser::add($this->db, [$user]);

        // if result is an empty array an error happened at adaptor level
        if ($result == null)
            return self::ERROR_adaptor;

        // if no error happened (checked previously) the array will be of size 1 
        return 0;
    }

    // explained in the userManagerInterface
    public function remove(user $user): int
    {
        // make the user ready for the database
        $user->makeDbReady($this->db);

        // search for user by name in the database
        $user_db = $this->getUserByName($user->{user::KEY_NAME});

        // check wether a user was found or not
        if ($user_db == null)
            return self::ERROR_NAME;

        // check if passwords match
        if (strcmp($user->{user::KEY_PASSWORD}, $user_db->{user::KEY_PASSWORD}) != 0)
            return self::ERROR_PASSWORD;

        // remove user from the database
        // $user_db is used since it contains the right id (no need to check that for user passed as argument)
        adaptorUser::remove($this->db, [$user_db]);

        // the remove will always be successful if user existed in the first place
        // in this situation that is the case since getUserByName didn't return null
        return 0;
    }

    // explained in the userManagerInterface
    public function edit(user $userOld, user $userNew): int
    {
        // make the user ready for the database
        $userOld->makeDbReady($this->db);

        // make the new user ready for the database
        $errors = $userNew->makeDbReady($this->db);

        // check if any invalid characters wer in the username (id doesn't matter)
        if ($errors & user::ERROR_NAME || $errors & user::ERROR_PASSWORD)
            return self::ERROR_INVALID_CHARACTERS;

        // search for user by name in the database
        $userDb = $this->getUserByName($userOld->{user::KEY_NAME});

        // check wether a user was found or not
        if ($userDb == null)
            return self::ERROR_NAME;

        // check if passwords match
        if (strcmp($userOld->{user::KEY_PASSWORD}, $userDb->{user::KEY_PASSWORD}) != 0)
            return self::ERROR_PASSWORD;

        // now create update the new user with the id in the database
        $userNew->updateId($userDb->{user::KEY_ID});

        // edit user in the database
        adaptorUser::edit($this->db, [$userNew]);

        // the edit will always be successful if user existed in the first place
        // in this situation that is the case since getUserByName didn't return null
        return 0;
    }

    // explained in the authenticationUserProviderInterface
    public function getUserByName(string $name): ?user
    {
        $users = adaptorUser::search($this->db, null, $name);

        // if no users were found return null
        if (empty($users))
            return null;

        // return first found user
        return $users[0];
    }

    // explained in the authenticationUserProviderInterface
    public function getUserById(int $id): ?user
    {
        $users = adaptorUser::search($this->db, $id);

        // if no users were found return null
        if (empty($users))
            return null;

        // return first found user
        return $users[0];
    }
}
