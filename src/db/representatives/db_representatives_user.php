<?php

/**
 * @package Database\Representatives
 */

// assign namespace
namespace db;

// import trait and interface
require_once("db_representatives_interface_trait.php");

// define aliases
use DateTime;
use JsonSerializable;
use mysqli;

/** 
 * The class used to describe a user.
 */
class user implements JsonSerializable, RepresentativeInterface
{
    // use trait for basic functionality
    use RepresentativeTrait;

    // Keys used in data array
    /** @var string id of the user (unique identifier in database) */
    public const KEY_ID = "id";
    /** @var string name of the user */
    public const KEY_NAME = "name";
    /** @var string role of the user */
    public const KEY_ROLE = "role";
    /** @var string password hash generated using user input and random salt **/
    public const KEY_PASSWORD_HASH = "password_hash";
    /** @var string salt used for password hash generation, randomly generated **/
    public const KEY_PASSWORD_SALT = "password_salt";
    /** @var string timestamp of token generation (used to limit temporal validity of bearer token) **/
    public const KEY_BINARY_TIMESTAMP = "binary_timestamp";
    /** @var string token used to authenticate **/
    public const KEY_BINARY_TOKEN = "binary_token";

    /** @var array data stored in the user */
    protected $data = [
        self::KEY_ID => 0,
        self::KEY_NAME => "",
        self::KEY_ROLE => 0,
        self::KEY_PASSWORD_HASH => b"", // is a string since it can store variable length binary data
        self::KEY_PASSWORD_SALT => b"", // see above
        self::KEY_BINARY_TIMESTAMP => null,
        self::KEY_BINARY_TIMESTAMP => b""   // see above
    ];

    // Errors
    /** @var int Error while parsing the id */
    const ERROR_ID = 1;
    /** @var int Error while parsing the username */
    const ERROR_NAME = 2;
    /** @var int Error while parsing the role */
    const ERROR_ROLE = 4;
    /** @var int Error while parsing the password hash */
    const ERROR_PASSWORD_HASH = 8;
    /** @var int Error while parsing the password salt */
    const ERROR_PASSWORD_SALT = 16;
    /** @var int Error while parsing the bearer timestamp */
    const ERROR_BINARY_TIMESTAMP = 32;
    /** @var int Error while parsing the bearer token */
    const ERROR_BINARY_TOKEN = 64;

    /**
     * Constructor 
     * 
     * @param ?int $ID Id of the user
     * @param ?string $name The username
     * @param ?int $role The role of the user
     * @param ?string $password_hash The BINARY representation of the password hash
     * @param ?string $password_salt The BINARY representation of the password salt
     * @param ?DateTime $bearer_timestamp The timestamp of the generated bearer token
     * @param ?string $bearer_token The BINARY representation of the bearer token
     */
    function __construct(
        int $ID = null,
        string $name = null,
        int $role = null,
        string $password_hash = null,
        string $password_salt = null,
        DateTime $bearer_timestamp = null,
        string $bearer_token = null,
    ) {
        // this strange way of setting the defaults is used so one can just null all unused fields during construction
        // not relay performant but makes debugging a bit easier
        $this->data[self::KEY_ID]               = $ID               ?? 0;
        $this->data[self::KEY_NAME]             = $name             ?? "";
        $this->data[self::KEY_ROLE]             = $role             ?? 0;
        $this->data[self::KEY_PASSWORD_HASH]    = $password_hash    ?? b"";
        $this->data[self::KEY_PASSWORD_SALT]    = $password_salt    ?? b"";
        $this->data[self::KEY_BINARY_TIMESTAMP] = $bearer_timestamp ?? new DateTime();
        $this->data[self::KEY_BINARY_TOKEN]     = $bearer_token     ?? b"";
    }

    // explained in RepresentativeInterface
    public function updateId(int $ID): self
    {
        $this->data[self::KEY_ID] = $ID;
        return $this;
    }

    /**
     * Parse strings into the user.
     * NO CHECKS ARE DONE WETHER THE VALUES ARE USEFUL OR NOT, JUST TYPE-SAFETY.
     * 
     * @param ?string $ID Id of the user
     * @param ?string $name The name of the user
     * @param ?string $password_hash The BINARY representation of the password hash
     * @param ?string $password_salt The BINARY representation of the password salt
     * @param ?string $bearer_timestamp The timestamp of the generated bearer token
     * @param ?string $bearer_token The BINARY representation of the bearer token
     * 
     * @return int the errors occurred during parsing
     */
    public function parse(
        ?string $ID = "",
        ?string $name = "",
        ?string $role = "",
        ?string $password_hash = "",
        ?string $password_salt = "",
        ?string $bearer_timestamp = "",
        ?string $bearer_token = ""
    ): int {
        // variable for error
        $error = 0;

        // date time from null is deprecated so prevent that
        if ($bearer_timestamp !== null) {
            // try to generate date from string, if it fails, log error and set date to current date
            try {
                $this->data[self::KEY_BINARY_TIMESTAMP] = new DateTime($bearer_timestamp);
            } catch (\Exception $e) {
                error_log($e);
                $this->data[self::KEY_BINARY_TIMESTAMP] = new DateTime("@43201"); // set it close to unix 0 (but not exactly to prevent timezone issues)
                $error |= self::ERROR_BINARY_TIMESTAMP;
            }
        } else {
            $this->data[self::KEY_BINARY_TIMESTAMP] = new DateTime("@43201"); // set it close to unix 0 (but not exactly to prevent timezone issues)
            $error |= self::ERROR_BINARY_TIMESTAMP;
        }

        // write string
        $this->data[self::KEY_NAME] = strval($name);

        // parsing integers
        $this->data[self::KEY_ID] = intval($ID);
        $this->data[self::KEY_ROLE] = intval($role);

        // write binary strings
        $this->data[self::KEY_PASSWORD_HASH] = $password_hash;
        $this->data[self::KEY_PASSWORD_SALT] = $password_salt;
        $this->data[self::KEY_BINARY_TOKEN] = $bearer_token;

        // return errors
        return $error;
    }

    /**
     * Returns values to serialize
     * 
     * @return AssociatedArray Array to serialize
     */
    public function jsonSerialize(): array
    {
        return [
            self::KEY_ID => $this->{self::KEY_ID},
            self::KEY_NAME => $this->{self::KEY_NAME},
            self::KEY_ROLE => $this->{self::KEY_ROLE}
        ];
    }

    /**
     * Checks wether the user passed as an argument is equal to this user
     * for more details see static usercmp();
     * 
     * @param self $user the user to check against
     * 
     * @return int 0 if they don't match, 1 if nam and password match, 2 if name, password and id match.
     * 
     * @see self::usercmp
     */
    /*public function isEqual(self $user): int
    {
        return self::usercmp($user, $this);
    }*/

    /**
     * Compares two users and decides wether they are identical or not.
     * If they aren't identic 0 will be returned.
     * If they match in name and password 1 will be returned.
     * If they match in every element 2 will be returned.
     * 
     * @param self $user1 The first user
     * @param self $user2 The second user
     * 
     * @return int 0 if they don't match, 1 if nam and password match, 2 if name, password and id match.
     */
    /*public static function usercmp(self $user1, self $user2): int
    {
        // check if name and password_hash match (if they don't the user isn't equal and 0 is returned)
        if (
            $user1->{self::KEY_NAME} != $user2->{self::KEY_NAME} ||
            $user1->{self::KEY_PASSWORD_HASH} != $user2->{self::KEY_PASSWORD_HASH}
        )
            return 0;

        // now check if id does match (if it does return 2 else proceed and return 1)
        if ($user1->{self::KEY_ID} == $user2->{self::KEY_ID})
            return 2;

        // at this point name and password match but id doesn't so return 1
        return 1;
    }*/
}
