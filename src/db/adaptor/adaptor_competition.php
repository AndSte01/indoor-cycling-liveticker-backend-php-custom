<?php

/**
 * adaptor to deal with competitions in the database
 * 
 * this file contains a set of methods and constants to work with competitions in the database, in an complicated not easy way to use.
 * please do not invoke the methods directly, instead consider using the tools provided in "db_utils_competition.php"
 * 
 * FUNCTIONS IN THIS SCRIPT DOES NOT CHECK FOR ERRORS OR INVALID ARGUMENTS, USE FUNCTIONS PROVIDED IN "db_utils_competition.php"!!!
 * 
 * @package Database\Database
 */

// assign namespace
namespace db;

// import required files
require_once("adaptor_interface.php");
require_once(dirname(__FILE__) . "/../representatives/representative_competition.php");
require_once(dirname(__FILE__) . "/../db_config.php");
require_once(dirname(__FILE__) . "/../db_kwd.php");

// define aliases
use mysqli;
use DateTime;

/**
 * Database adaptor for competitions
 */
class adaptorCompetition implements adaptorInterface
{
    /**
     * @param bool $useAND Determents wether statement is combined with 'AND' (true) or 'OR' (false)
     * @param ?int $id ID of competition to search for
     * @param ?DateTime $date Date of competition
     * @param ?string $name Name of competition
     * @param ?string $location Location of competition
     * @param ?int $limit The number of results the query should fetch
     * @param ?DateTime $date_end to search for competition between $date_end and $date ($date > $date_end)
     * 
     * @return competition[] array of competitions, might be empty
     */
    public static function search(
        mysqli $db,
        bool $useAND = true,
        ?int $id = null,
        ?DateTime $date = null,
        ?string $name = null,
        ?string $location = null,
        ?int $limit = null,
        ?DateTime $date_end = null
    ): array {
        // empty return
        $return = [];

        // statement to concatenate filters together
        $concat = ($useAND) ? " AND " : " OR ";

        // Put search filters and corresponding parameters in an array
        $filter = [];
        $parameters = [];

        // check if filters need to be set
        if (($id !== null)) { // also true if empty array
            $filter[] = db_kwd::COMPETITION_ID . "=?";
            $parameters[] = strval($id);
        }
        if ($name != null) {
            $filter[] = db_kwd::COMPETITION_NAME . "=?";
            $parameters[] = $name;
        }
        if ($location != null) {
            $filter[] = db_kwd::COMPETITION_LOCATION . "=?";
            $parameters[] = $location;
        }
        if ($date != null) {
            // if competition is searched between two dates a modified query is required
            if ($date_end != null) {
                $filter[] = db_kwd::COMPETITION_DATE . " between ? AND ?";
                $parameters[] = $date_end->format('Y-m-d');
                $parameters[] = $date->format('Y-m-d');
            } else {
                $filter[] = db_kwd::COMPETITION_DATE . "=?";
                $parameters[] = $date->format('Y-m-d');
            }
        }

        // Make $filter (a) string again!
        if ($filter != null)
            $filter = "WHERE " . implode($concat, $filter); // "Decode" filter array to useful string
        else
            $filter = "WHERE 1"; // Add behavior to list all competitions if no filter is applied


        // add the limit parameter (strict comparison required for limit = 0)
        $str_limit = "";
        if ($limit !== null) {
            // clean limit value (no values below 0 are allowed)
            $limit = ($limit < 0) ? 0 : $limit;
            $str_limit = "LIMIT " . strval($limit);
        }

        // Create SQL query
        $statement = $db->prepare("SELECT " . implode(", ", [
            db_kwd::COMPETITION_ID,
            db_kwd::COMPETITION_DATE,
            db_kwd::COMPETITION_NAME,
            db_kwd::COMPETITION_LOCATION,
            db_kwd::COMPETITION_USER,
            db_kwd::COMPETITION_AREAS,
            db_kwd::COMPETITION_FEATURE_SET,
            db_kwd::COMPETITION_LIVE
        ]) .
            " FROM " . db_kwd::TABLE_COMPETITION . " $filter ORDER by date DESC $str_limit;");

        /**
         * awful but gets a beautiful replacement with php >8.1
         * Replacement: remove all bind_param() and replace $statement->execute() with $statement->execute($parameters)
         */
        /*switch (count($parameters)) {
            case 1:
                $statement->bind_param("s", $parameters[0]);
                break;

            case 2:
                $statement->bind_param("ss", $parameters[0], $parameters[1]);
                break;

            case 3:
                $statement->bind_param("sss", $parameters[0], $parameters[1], $parameters[2]);
                break;

            case 4:
                $statement->bind_param("ssss", $parameters[0], $parameters[1], $parameters[2], $parameters[3]);
                break;

            default:
                break;
        }*/

        // execute statement
        $statement->execute($parameters);

        // bind result values to statement
        $statement->bind_result($_1, $_2, $_3, $_4, $_5, $_6, $_7, $_8);

        // iterate over results
        while ($statement->fetch()) {
            $entry = new competition();
            $entry->parse($_1, $_2, $_3, $_4, $_5, $_6, $_7, $_8);

            // append to list
            $return[] = $entry;
        }

        // return array of competitions
        return $return;
    }

    // explained in the interface
    /**
     * Note: you can't add a competition where user_id is null,
     * it gets prevented even though the database would alow for it.
     */
    public static function add(mysqli $db, array $representatives): array
    {
        // empty return array
        $return = [];

        // use prepared statement to prevent SQL injections
        $statement = $db->prepare("INSERT INTO " . db_kwd::TABLE_COMPETITION . " (" .
            implode(", ", [
                db_kwd::COMPETITION_DATE,
                db_kwd::COMPETITION_NAME,
                db_kwd::COMPETITION_LOCATION,
                db_kwd::COMPETITION_USER,
                db_kwd::COMPETITION_AREAS,
                db_kwd::COMPETITION_FEATURE_SET,
                db_kwd::COMPETITION_LIVE
            ])
            . ") VALUES (?, ?, ?, ?, ?, ?, ?);");

        // bind parameters to statement
        $statement->bind_param(
            "sssiiii",
            $comp_date,
            $comp_name,
            $comp_location,
            $comp_user,
            $comp_areas,
            $comp_feature_set,
            $comp_live
        );

        // iterate through array of competitions and add to database
        foreach ($representatives as &$competition) {
            $comp_date = $competition->{competition::KEY_DATE}->format("Y-m-d");
            $comp_name = $competition->{competition::KEY_NAME};
            $comp_location = $competition->{competition::KEY_LOCATION};
            $comp_user = $competition->{competition::KEY_USER};
            $comp_areas = $competition->{competition::KEY_AREAS};
            $comp_feature_set = $competition->{competition::KEY_FEATURE_SET};
            $comp_live = $competition->{competition::KEY_LIVE};

            // prevent writing of competitions with no user (it happens quietly!!!)
            if ($comp_user == null)
                continue;

            if (!$statement->execute()) {
                error_log("error while writing competition to database");

                // prevent rest of the loop from being executed
                continue;
            }

            // update id in competition and add it to the return statement
            $return[] = $competition->updateId($db->insert_id);
        }

        return $return;
    }

    // explained in the interface
    public static function edit(mysqli $db, RepresentativeInterface $representative, array $keys): bool
    {
        // convert the names of representative fields to database fields

        // map names together (id and user are  since you can't change them anyways)
        $key_map = [
            competition::KEY_DATE => db_kwd::COMPETITION_DATE,
            competition::KEY_NAME => db_kwd::COMPETITION_NAME,
            competition::KEY_LOCATION => db_kwd::COMPETITION_LOCATION,
            competition::KEY_AREAS => db_kwd::COMPETITION_AREAS,
            competition::KEY_FEATURE_SET => db_kwd::COMPETITION_FEATURE_SET,
            competition::KEY_LIVE => db_kwd::COMPETITION_LIVE
        ];

        // empty arrays to hold fields that should be updated
        $fields = []; // field names in database containing an additional =? for sql query
        $params = []; // values to insert in database

        // treat DateTime object separately
        $array_key_date = array_search(competition::KEY_DATE, $keys);
        if (false !== $array_key_date) {
            // add to fields
            $fields[] = $key_map[competition::KEY_DATE] . "=? ";
            // convert to string and add to params
            $params[] = $representative->{competition::KEY_DATE}->format("Y-m-d");
            // remove key from array to prevent multiple addition
            unset($keys[$array_key_date]);
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
        $params[] = $representative->{competition::KEY_ID};

        // use prepared statement to prevent SQL injections
        $statement = $db->prepare("UPDATE " . db_kwd::TABLE_COMPETITION . " SET " .
            implode(", ", $fields)
            . " WHERE " . db_kwd::COMPETITION_ID . "=?");


        // execute statement with prepared values
        return $statement->execute($params);
    }

    // explained in the interface
    public static function remove(mysqli $db, array $representatives): void
    {
        // prepare statement
        $statement = $db->prepare("DELETE FROM " . db_kwd::TABLE_COMPETITION . " WHERE " . db_kwd::COMPETITION_ID . " = ?");
        $statement->bind_param("i", $ID);

        // iterate through array and execute statement for different ids
        foreach ($representatives as &$competition) {
            $ID = $competition->{competition::KEY_ID};
            $statement->execute();
        }
    }

    // explained int the interface
    public static function makeRepresentativeDbReady(mysqli $db, RepresentativeInterface &$representative): int
    {
        // variable for storing errors
        $error = 0;

        // get values from old representative
        $old_id = $representative->{competition::KEY_ID};
        $new_date = $representative->{competition::KEY_DATE};
        $new_name = $representative->{competition::KEY_NAME};
        $new_location = $representative->{competition::KEY_LOCATION};
        $new_user = $representative->{competition::KEY_USER};
        $new_areas = $representative->{competition::KEY_AREAS};
        $new_feature_set = $representative->{competition::KEY_FEATURE_SET};
        $new_live = $representative->{competition::KEY_LIVE};

        // check date
        if ($new_date == null) {
            $new_date = new DateTime();
            $error |= competition::ERROR_DATE;
        }

        // check if invalid characters are present in string, if so remove them and add error
        if (strcmp($new_name, $db->real_escape_string($new_name)) != 0) {
            $new_name = stripslashes($db->real_escape_string($new_name));
            $error |= competition::ERROR_NAME;
        }

        if (strcmp($new_location, $db->real_escape_string($new_location)) != 0) {
            $new_location = stripslashes($db->real_escape_string($new_location));
            $error |= competition::ERROR_LOCATION;
        }

        // check if integers are within their correct range, if not make them 0 and add error
        // won't check id, it isn't used when writing to db and if reading from db and id is out of range nothing happens
        // user id can't be smaller than 1 (max. value is due to db limitations)
        if ($new_user < 1 || $new_user > 2147483647) {
            $new_user = 0; // marks competition as user less in database
            $error |= competition::ERROR_USER;
        }

        // areas are >= 0 by definition
        if ($new_areas < 0) {
            $new_areas = 0;
            $error |= competition::ERROR_AREAS;
        }
        if ($new_areas > 127) {
            $new_areas = 127;
            $error |= competition::ERROR_AREAS;
        }

        // feature_set is >= 0 by design (values >127 are also invalid so downgrade to 0 happens)
        if ($new_feature_set < 0 || $new_feature_set > 127) {
            $new_feature_set = 0;
            $error |= competition::ERROR_FEATURE_SET;
        }

        // live is seen as a boolean (so make it one)
        // 0 for everything < 0
        if ($new_live < 0) {
            $new_live = 0;
            $error |= competition::ERROR_LIVE;
        }
        // 1 for everything > 1
        if ($new_live > 1) {
            $new_live = 1;
            $error |= competition::ERROR_LIVE;
        }

        // overwrite competition wih new one
        $representative = new competition(
            $old_id,
            $new_date,
            $new_name,
            $new_location,
            $new_user,
            $new_areas,
            $new_feature_set,
            $new_live
        );

        // return possible errors
        return $error;
    }

    /**
     * Cleans the database from competitions whose user got deleted (and therefore the user_id is set to null)
     * 
     * @param mysqli $db The database to work with
     * 
     * @return bool|int bool (probably false) if the query didn't succeed, else int with the affected rows (number of competitions removed)
     */
    public static function clean(mysqli $db): bool|int
    {
        // function is still required since a competition gets user NULL assigned if user got deleted (used to prevent data loss)

        // no prepared statement is needed
        // remove all competitions where the user_id is null
        $result = $db->query("DELETE FROM "  . db_kwd::TABLE_COMPETITION . " WHERE " . db_kwd::COMPETITION_USER . " = NULL");

        // if $result is boolean return it's value (value is probably false)
        if (is_bool($result))
            return $result;

        // return the number of affected rows
        return $result->num_rows;
    }
}
