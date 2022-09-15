<?php

/**
 * A manager for users
 * 
 * @package Database\Managers
 */

// global namespace is desired

// define aliases
use db\adaptorGeneric;
use db\adaptorUser;
use db\db_col_prop;
use db\user;

// add required database tools
require_once(dirname(__FILE__) . "/../adaptor/adaptor_user.php");
require_once(dirname(__FILE__) . "/../representatives/representative_user.php");
require_once(dirname(__FILE__) . "/../db_config.php");

/**
 * Layout of a bearer token:
 * $username:$role:base64_encode($binary_token)
 * 
 * tokens stored in the database expire after a certain time (defined as a constant) (this also affects the bearer token)
 */
class managerUser
{
    /** @var int Error at adaptor level */
    public const ERROR_ADAPTOR = 1;
    /** @var int The desired user doesn't exist */
    public const ERROR_NOT_EXISTING = 2;
    /** @var int Error regarding the user password (e. g. it is false) */
    public const ERROR_PASSWORD = 4;
    /** @var int Error regarding the user bearer token (e. g. it was incorrect) */
    public const ERROR_TOKEN = 8;
    /** @var int A user with similar username already exists in the database */
    public const ERROR_ALREADY_EXISTING = 16;
    /** @var int Username or password contained invalid characters */
    public const ERROR_INVALID_CHARACTERS = 32;
    /** @var int The value of the role doesn't match the requirements (>= 0) */
    public const ERROR_ROLE = 64;

    /** @var string The hash algorithm used to hash password with salt */
    private const HASH_ALGORITHM = "sha3-512"; // please note the byte length of hash (configured in cb_config.php)

    /** @var int Time after which a binary token (and therefore a bearer token) expires */
    private const TOKEN_EXPIRATION_TIME = 86400; // 24h

    /** @var mysqli The database to work with */
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        if ($db == null)
            throw new Exception("database mustn't be null", 1);

        $this->db = $db;
    }

    /**
     * Authenticates user with a bearer token
     * 
     * Note: no username required since it is encoded in the token
     * 
     * @param string $token the token that should be used for authentication
     * 
     * @return int|user In case the token was valid the user the token authenticates, else int with error.
     *                  Possible errors are ERROR_TOKEN, ERROR_NOT_EXISTING, ERROR_ROLE
     */
    public function authenticateWithToken(string $bearer_token): int|user
    {
        // split provided bearer token into separate parts
        $token_contents = explode(":", $bearer_token);

        // check if token contents made any sense (check if the token contained the right amount of information)
        if (count($token_contents) != 3)
            return self::ERROR_TOKEN;

        // decode username from base 64
        $token_contents[0] = base64_decode($token_contents[0], true);

        // check if decode has been successful
        if ($token_contents[0] === false)
            return false;

        // search for a user with the name provided in the token
        $user = $this->getUserByName($token_contents[0]);

        // if no user has been found return null
        if (null == $user)
            return self::ERROR_NOT_EXISTING;

        // check if role of user matches the one of the token, else return null
        if ($user->{user::KEY_ROLE} != $token_contents[1])
            return self::ERROR_ROLE;

        // validate binary token, and if validation was successful return the user
        if ($this->validateBinaryToken($user, $token_contents[2]))
            return $user;

        // default return
        return self::ERROR_TOKEN;
    }

    /**
     * Checks wether the binary token is valid for the provided user
     * 
     * Note: no database query's are made (regarding users) so make sure the provided user is valid inside of the database
     */
    private function validateBinaryToken(user $user, string $token): bool
    {
        // decode token to binary
        $binary_token = base64_decode($token, true);

        // check if decode has been successful
        if ($binary_token === false)
            return false;

        // check if token match
        if ($binary_token != $user->{user::KEY_BINARY_TOKEN})
            return false;

        // check time between now (database server) and time of generation
        $delta_t = adaptorGeneric::getCurrentTime($this->db)->getTimestamp() - $user->{user::KEY_BINARY_TIMESTAMP}->getTimestamp();

        // if binary_tokens are generated in the future (that mustn't happen) something is very wrong so return false
        if ($delta_t > self::TOKEN_EXPIRATION_TIME || $delta_t < 0)
            return false;

        // all test were passed return true
        return true;
    }

    /**
     * Authenticates a user by the provided password
     * 
     * @param string $username The username
     * @param string $password The password
     * 
     * @return int|user In case the token was valid the user the token authenticates, else int with error.
     *                  Possible errors are ERROR_NOT_EXISTING, ERROR_PASSWORD
     */
    public function authenticateWithPassword(string $username, string $password): int|user
    {
        // search for user by username
        $user = $this->getUserByName($username);

        //check if such user exists, if not return error
        if ($user == null)
            return self::ERROR_NOT_EXISTING;

        // generate password_hash with salt to later compare it to the one stored in the database
        $provided_password_hash = self::generatePasswordHash($password, $user->{user::KEY_PASSWORD_SALT});

        // check if password hashes match if not return error
        if (strcmp($provided_password_hash, $user->{user::KEY_PASSWORD_HASH}) !== 0)
            return self::ERROR_PASSWORD;

        // at this point we know username and password are correct

        // return the user
        return $user;

        // bearer token moved to getBearerToken()
    }

    /**
     * Generates a password hash for the provided password with the provided salt (random bytes)
     * 
     * @param string $password The password to hash
     * @param string $salt The salt used to hash the password
     * 
     * @return string Binary representation of the generated hash
     */
    private static function generatePasswordHash(string $password, string $salt): string
    {
        // hash is generated from string with layout $password:$salt
        return hash(self::HASH_ALGORITHM, $password . ":" . $salt, true);
    }

    /**
     * Get's the binary token of the provided user. ALL ALREADY EXISTING TOKENS BECOME INVALID!
     * 
     * @param user $user The user whose bearer token should be provided. Make sure the user is a valid one
     *             (e. g. fresh out of the database) elsewise errors might happen.
     * 
     * @return string The bearer token of the user
     */
    public function getBearerToken(user $user): string
    {
        // get a new binary token that later is used for the bearer token
        // DON'T USE THE TOKEN STORED IN THE USER, some additional checks are performed by the below used function
        $binary_token = $this->getBinaryToken($user);

        // generate bearer token to return
        return self::generateBearerToken($user->{user::KEY_NAME}, $user->{user::KEY_ROLE}, $binary_token);
    }

    /**
     * Generates (and therefore gets) a new binary token for the provided user, if a token already exist it is overwritten
     * 
     * @param user $user The user that should receive a new binary token
     * 
     * @param string The newly generated binary token
     */
    private function getBinaryToken(user $user): string
    {
        // generate new binary token
        $new_binary_token = random_bytes(db_col_prop::USER_BINARY_TOKEN_LENGTH);

        // store new data in dummy user
        $user_for_db = new user(
            $user->{user::KEY_ID},
            null,
            null,
            null,
            null,
            adaptorGeneric::getCurrentTime($this->db), // set time for token generation to current database time
            $new_binary_token
        );

        // edit user in the database
        adaptorUser::edit($this->db, $user_for_db, [user::KEY_BINARY_TIMESTAMP, user::KEY_BINARY_TOKEN]);

        // return the generated token
        return $new_binary_token;
    }

    /**
     * Generates a bearer token
     */
    private static function generateBearerToken(string $username, int $role, string $binary_token): string
    {
        return base64_encode($username) . ":" . strval($role) . ":" . base64_encode($binary_token);
    }

    /**
     * Searches for a user by it's name
     * 
     * @param string $name The name of the user
     * 
     * @return ?user The first user with the desired name that was found in the database
     */
    public function getUserByName(string $name): ?user
    {
        // only return the first user that was found
        $result = adaptorUser::search($this->db, null, $name);

        // if array is empty return null
        if ($result == null)
            return null;

        // if array isn't empty return first element
        return $result[0];
    }

    /**
     * Add a new user to the database
     * 
     * @param string $username The username of the new user
     * @param string $password The password of the new user
     * @param int $role The role of the new user
     * 
     * @return int any errors that might have occurred, else 0
     */
    public function add(string $username, string $password, int $role): int
    {
        // sanitize strings and return errors if it doesn't work
        if (
            self::sanitizeString($username) ||
            self::sanitizeString($password)
        )
            return self::ERROR_INVALID_CHARACTERS;

        // check if similar users already exist in database
        if ($this->getUserByName($username) != null)
            return self::ERROR_ALREADY_EXISTING;

        // check if role is >= 0
        if ($role < 0)
            return self::ERROR_ROLE;

        // generate new random salt
        $salt = random_bytes(db_col_prop::USER_PASSWORD_SALT_LENGTH);

        // generate password hash
        $password_hash = self::generatePasswordHash($password, $salt);

        // create a new user
        $user = new user(
            null,
            $username,
            $role,
            $password_hash,
            $salt,
            new DateTime("@43201"), // bearer timestamp (don't use null to prevent timezone issues)
            random_bytes(db_col_prop::USER_BINARY_TOKEN_LENGTH) // new random bearer token
        );

        // make the newly generated user ready for the database
        adaptorUser::makeRepresentativeDbReady($this->db, $user);

        // add user to the database, and check for success
        if (adaptorUser::add($this->db, [$user]) == null)
            return self::ERROR_ADAPTOR;

        // seems as if no errors occurred so return 0
        return 0;
    }

    /**
     * Edits a user password in the database, also a new password_hash and salt is generated (and binary token get's deleted)
     * 
     * @param string $username The username of the user
     * @param string $password The new password of the user
     * 
     * @return int any errors that might have occurred, else 0
     */
    public function editPassword(string $username, string $password): int
    {
        // search for user in the database
        $user = $this->getUserByName($username);

        // check if any users where found
        if ($user == null)
            return self::ERROR_NOT_EXISTING;

        // sanitize new password
        if (self::sanitizeString($password))
            return self::ERROR_INVALID_CHARACTERS;

        // generate new random salt
        $salt = random_bytes(db_col_prop::USER_PASSWORD_SALT_LENGTH);

        // generate password hash
        $password_hash = self::generatePasswordHash($password, $salt);

        // create new user with updated fields
        $new_user = new user(
            $user->{user::KEY_ID},
            null,
            null,
            $password_hash,
            $salt,
            new DateTime("@43201"), // bearer timestamp (don't use null to prevent timezone issues)
            random_bytes(db_col_prop::USER_BINARY_TOKEN_LENGTH) // new random bearer token
        );

        // make the newly generated user ready for the database
        adaptorUser::makeRepresentativeDbReady($this->db, $user);

        // edit user in the database
        $result = adaptorUser::edit($this->db, $new_user, [
            user::KEY_PASSWORD_HASH,
            user::KEY_PASSWORD_SALT,
            user::KEY_BINARY_TIMESTAMP,
            user::KEY_BINARY_TOKEN
        ]);

        // check if password was written successfully
        if ($result == false)
            return self::ERROR_ADAPTOR;

        // seems as if no errors occurred so return 0
        return 0;
    }

    /**
     * Edits a user role in the database, whenever the role is changed the binary token get's deleted
     * 
     * Please note, by changing a users role, all bearer tokens using the old role become invalid
     * 
     * @param string $username The username of the user
     * @param int $role The new role of the user
     * 
     * @return int any errors that might have occurred, else 0
     */
    public function editRole(string $username, int $role): int
    {

        // search for user in the database
        $user = $this->getUserByName($username);

        // check if any users where found
        if ($user == null)
            return self::ERROR_NOT_EXISTING;

        // check if role is >= 0
        if ($role < 0)
            return self::ERROR_ROLE;

        // create new user with updated fields
        $new_user = new user(
            $user->{user::KEY_ID},
            null,
            $role,
            null,
            null,
            new DateTime("@43201"), // bearer timestamp (don't use null to prevent timezone issues)
            random_bytes(db_col_prop::USER_BINARY_TOKEN_LENGTH) // new random bearer token
        );

        // make the newly generated user ready for the database
        adaptorUser::makeRepresentativeDbReady($this->db, $user);

        // edit user in the database
        $result = adaptorUser::edit($this->db, $new_user, [
            user::KEY_ROLE,
            user::KEY_BINARY_TIMESTAMP,
            user::KEY_BINARY_TOKEN
        ]);

        // check if role was changed successfully
        if ($result == false)
            return self::ERROR_ADAPTOR;

        // seems as if no errors occurred so return 0
        return 0;
    }

    /**
     * Deletes the user assigned to the provided id
     * 
     * @param int $id The id of the user that should be deleted
     */
    public function remove(int $id): void
    {
        adaptorUser::remove($this->db, [new user($id)]);
    }

    /**
     * Resets the bearer token of the provided user (like a logout)
     * 
     * @param user $user The user whose binary token should be reseted
     */
    public function resetBearerToken(user $user): void
    {
        $this->getBinaryToken($user); // getting a new one is equal to resetting it
    }

    /**
     * Checks for any bad characters in string and removes them
     * 
     * Characters are: ": \n"
     * 
     * @param string &$str Reference to the string to sanitize
     * 
     * @return bool Wether any characters wer CHANGED TRUE or not (FALSE)
     */
    private static function sanitizeString(string &$str): bool
    {
        // sanitize string
        $sanitized = preg_replace("/[\:\\n]+/", "", $str);

        // check if any modification were made (if so update $str and return true)
        if (strcmp($sanitized, $str) != 0) {
            $str = $sanitized;
            return true;
        }

        // no modification were made and return false
        // $str = $sanitized;
        return false;
    }
}
