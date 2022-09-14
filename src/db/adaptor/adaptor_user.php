<?php

/**
 * adaptor to deal with users in the database
 * 
 * this file contains a set of methods and constants to work with users in the database, in an complicated not easy way to use.
 * please do not invoke the methods directly, instead consider using the tools provided in "db_utils_user.php"
 * 
 * FUNCTIONS IN THIS SCRIPT DOES NOT CHECK FOR ERRORS OR INVALID ARGUMENTS, USE FUNCTIONS PROVIDED IN "db_utils_user.php"!!!
 * 
 * @package Database\Database
 */

// assign namespace
namespace db;

// import required files
require_once("adaptor_interface.php");
require_once(dirname(__FILE__) . "/../representatives/db_representatives_user.php");

// define aliases
use DateTime;
use mysqli;

/**
 * Database adaptor for users
 */
class adaptorUser implements adaptorInterface
{
    /**
     * Note: the ability to search users by passwords is used for legacy reasons (don't use it in normal circumstances)
     * 
     * @param ?int $user_id Id of the users to search for
     * @param ?string $name Name of the user to search for
     * @param ?string $password Password of the user
     */
    public static function search(mysqli $db, ?int $user_id = null, ?string $name = null): array
    {
        // empty return
        $return = [];

        // Put search filters and corresponding parameters in an array
        $filter = [];
        $parameters = [];

        // check if filters need to be set
        if (($user_id != null)) { // also true if empty array
            $filter[] = db_kwd::USER_ID . "=?";
            $parameters[]  = strval($user_id);
        }
        if ($name != null) {
            $filter[] = db_kwd::USER_NAME . "=?";
            $parameters[] = $name;
        }

        // Make $filter (a) string again!
        if ($filter != null)
            $filter = "WHERE " . implode(" AND ", $filter); // "Decode" filter array to useful string
        else
            $filter = "WHERE 1"; // Add behavior to list all entries if no filter is applied

        // Create SQL query
        $statement = $db->prepare("SELECT " . implode(", ", [
            db_kwd::USER_ID,
            db_kwd::USER_NAME,
            db_kwd::USER_ROLE,
            db_kwd::USER_PASSWORD_HASH,
            db_kwd::USER_PASSWORD_SALT,
            db_kwd::USER_BINARY_TIMESTAMP,
            db_kwd::USER_BINARY_TOKEN
        ]) .
            " FROM " . db_config::TABLE_USER . " $filter;");

        // execute statement
        $statement->execute($parameters);

        // bind result values to statement
        $statement->bind_result($_1, $_2, $_3, $_4, $_5, $_6, $_7);

        // iterate over results
        while ($statement->fetch()) {
            $entry = new user();
            $entry->parse($_1, $_2, $_3, $_4, $_5, $_6, $_7);

            // append to list
            $return[] = $entry;
        }

        // return array of results
        return $return;
    }

    // explained in the interface
    public static function add(mysqli $db, array $representatives): array
    {
        // empty return array
        $return = [];

        // use prepared statement to prevent SQL injections
        $statement = $db->prepare("INSERT INTO " . db_config::TABLE_USER . " (" .
            implode(", ", [
                db_kwd::USER_NAME,
                db_kwd::USER_ROLE,
                db_kwd::USER_PASSWORD_HASH,
                db_kwd::USER_PASSWORD_SALT,
                db_kwd::USER_BINARY_TIMESTAMP,
                db_kwd::USER_BINARY_TOKEN
            ])
            . ") VALUES (?, ?, ?, ?, ?, ?);");

        // iterate through array of users and add to database
        foreach ($representatives as &$user) {
            if (!$statement->execute([
                $user->{user::KEY_NAME},
                $user->{user::KEY_ROLE},
                $user->{user::KEY_PASSWORD_HASH},
                $user->{user::KEY_PASSWORD_SALT},
                $user->{user::KEY_BINARY_TIMESTAMP}->format("Y-m-d H:i:s"),
                $user->{user::KEY_BINARY_TOKEN}
            ])) {
                error_log("error while writing user to database");

                // prevent rest of the loop from being executed
                continue;
            }

            // update id in user and add it to the return statement
            $return[] = $user->updateId($db->insert_id);
        }

        return $return;
    }

    // explained in the interface
    public static function edit(mysqli $db, RepresentativeInterface $representative, array $keys): bool
    {
        // convert the names of representative fields to database fields

        // map names together (id is skipped since you can't change it anyways)
        $key_map = [
            user::KEY_NAME => db_kwd::USER_NAME,
            user::KEY_ROLE => db_kwd::USER_ROLE,
            user::KEY_PASSWORD_HASH => db_kwd::USER_PASSWORD_HASH,
            user::KEY_PASSWORD_SALT => db_kwd::USER_PASSWORD_SALT,
            user::KEY_BINARY_TIMESTAMP => db_kwd::USER_BINARY_TIMESTAMP,
            user::KEY_BINARY_TOKEN => db_kwd::USER_BINARY_TOKEN
        ];

        // empty arrays to hold fields that should be updated
        $fields = []; // field names in database containing an additional =? for sql query
        $params = []; // values to insert in database

        // treat DateTime object separately
        $array_key_token_timestamp = array_search(user::KEY_BINARY_TIMESTAMP, $keys);
        if (false !== $array_key_token_timestamp) {
            // add to fields
            $fields[] = $key_map[user::KEY_BINARY_TIMESTAMP] . "=? ";
            // convert to string and add to params
            $params[] = $representative->{user::KEY_BINARY_TIMESTAMP}->format("Y-m-d H:i:s");
            // remove key from array to prevent multiple addition
            unset($keys[$array_key_token_timestamp]);
        }

        foreach ($keys as $key) {
            // get mapped key (might be null if fields contained unsupported key)
            $field = $key_map[$key];

            // add field to update list
            if ($field != null) {
                // add string for prepare statement
                $fields[] = $field . "=? ";

                // add value to array (Note: use correct key)
                $params[] = $representative->{$key};
            }
        }

        // if no fields should be changed, skip sql statement and return directly
        if (count($fields) == 0)
            return true; // act as if the statement was successful

        // add id as last value to params
        $params[] = $representative->{user::KEY_ID};

        // use prepared statement to prevent SQL injections
        $statement = $db->prepare("UPDATE " . db_config::TABLE_USER . " SET " .
            implode(", ", $fields)
            . " WHERE " . db_kwd::USER_ID . "=?");


        // execute statement with prepared values
        return $statement->execute($params);
    }

    // explained in the interface
    public static function remove(mysqli $db, array $representatives): void
    {
        // prepare statement
        $statement = $db->prepare("DELETE FROM " . db_config::TABLE_USER . " WHERE " . db_kwd::USER_ID . "=?");
        $statement->bind_param("i", $ID);

        // iterate through array and execute statement for different ids
        foreach ($representatives as &$user) {
            $ID = $user->{user::KEY_ID};
            $statement->execute();
        }
    }

    // explained int the interface
    public static function makeRepresentativeDbReady(mysqli $db, RepresentativeInterface &$representative): int
    {
        // variable for storing errors
        $error = 0;

        // get values from old representative
        $old_id = $representative->{user::KEY_ID};
        $new_name = $representative->{user::KEY_NAME};
        $new_role = $representative->{user::KEY_ROLE};
        $new_password_hash = $representative->{user::KEY_PASSWORD_HASH};
        $new_password_salt = $representative->{user::KEY_PASSWORD_SALT};
        $new_bearer_timestamp = $representative->{user::KEY_BINARY_TIMESTAMP};
        $new_bearer_token = $representative->{user::KEY_BINARY_TOKEN};

        // check timestamp
        if ($new_bearer_timestamp == null) {
            $new_bearer_timestamp = (new DateTime())->setTimestamp(43201);
            $error |= user::ERROR_BINARY_TIMESTAMP;
        }

        // check if invalid characters are present in string, if so remove them and add error
        if (strcmp($new_name, $db->real_escape_string($new_name)) != 0) {
            $new_name = $db->real_escape_string($new_name);
            $error |= user::ERROR_NAME;
        }

        // won't check id, it isn't used when writing to db and if reading from db and id is out of range nothing happens

        // role is >= 0 by design
        if ($new_role < 0) {
            $new_role = 0;
            $error |= user::ERROR_ROLE;
        }

        // check binary stings for length
        if (!self::makeStringCorrectLength($new_password_hash, db_col_prop::USER_PASSWORD_HASH_LENGTH))
            $error |= user::ERROR_PASSWORD_HASH;

        if (!self::makeStringCorrectLength($new_password_salt, db_col_prop::USER_PASSWORD_SALT_LENGTH))
            $error |= user::ERROR_PASSWORD_SALT;

        if (!self::makeStringCorrectLength($new_bearer_token, db_col_prop::USER_BINARY_TOKEN_LENGTH))
            $error |= user::ERROR_BINARY_TOKEN;

        // overwrite user with new one containing the newly created variables
        $representative = new user(
            $old_id,
            $new_name,
            $new_role,
            $new_password_hash,
            $new_password_salt,
            $new_bearer_timestamp,
            $new_bearer_token
        );

        // return possible errors
        return $error;
    }

    /**
     * Sets a string to the correct length.
     * - If it is too short leading zeroes are applied.
     * - If it is to long it is cropped
     * 
     * @param string &$str Reference to the string to work with
     * @param int $length Desired length of the string
     * 
     * @return boolean Wether the string had to be modified or not
     */
    private static function makeStringCorrectLength(string &$str, int $length): bool
    {
        // check if string is too long and correct length
        if (strlen($str) > $length) {
            $str = substr($str, 0, $length - 1);
            return false;
        }

        // check if string is too short and correct length
        if (strlen($str) < $length) {
            //   is 0x00
            $str = str_repeat(" ", $length - strlen($str)) . $str;
            return false;
        }

        // default return
        return true;
    }
}
