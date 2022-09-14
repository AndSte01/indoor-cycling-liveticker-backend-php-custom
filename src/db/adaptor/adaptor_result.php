<?php

/**
 * adaptor to deal with results in the database
 * 
 * this file contains a set of methods and constants to work with results in the database, in an complicated not easy way to use.
 * please do not invoke the methods directly, instead consider using the tools provided in "db_utils_result.php"
 * 
 * FUNCTIONS IN THIS SCRIPT DOES NOT CHECK FOR ERRORS OR INVALID ARGUMENTS, USE FUNCTIONS PROVIDED IN "db_utils_result.php"!!!
 * 
 * @package Database\Database
 */

// assign namespace
namespace db;

// import required files
require_once("adaptor_interface.php");
require_once(dirname(__FILE__) . "/../representatives/db_representatives_result.php");

// define aliases
use DateTime;
use mysqli;

/**
 * Database adaptor for results
 */
class adaptorResult implements adaptorInterface
{
    /**
     * @param ?int $results Id of the result to search for
     * @param ?array $discipline_id Id of the disciplines whose results should be returned
     * @param ?DateTime $modifiedSince Get results that were modified after the time passed
     * @param ?int $start_number The start number of the competitor
     * @param ?string $name The name of the competitor
     * @param ?string $club The club of the competitor
     */
    public static function search(
        mysqli $db,
        ?int $results_id = null,
        ?int $discipline_id = null,
        ?DateTime $modified_since = null,
        ?int $start_number = null,
        ?string $name = null,
        ?string $club = null
    ): array {
        // empty return
        $return = [];

        // Put search filters and corresponding parameters in an array
        $filter = [];
        $parameters = [];

        // check if filters need to be set
        if ($results_id != null) { // also true if empty array
            $filter[] = db_kwd::RESULT_ID . "=?";
            $parameters[] = strval($results_id);
        }
        if (($discipline_id != null)) {
            $filter[] = db_kwd::RESULT_DISCIPLINE . "=?";
            $parameters[] = strval($discipline_id);
        }
        if ($modified_since != null) {
            // greater or equal is required so no disciplines with "bad timing" are missed,
            // with this implementation in the worst case the client gets an discipline that wasn't relay updated
            $filter[] = db_kwd::RESULT_TIMESTAMP . ">=?";
            $parameters[] = $modified_since->format('Y-m-d H:i:s');
        }
        if ($start_number != null) {
            $filter[] = db_kwd::RESULT_START_NUMBER . "=?";
            $parameters[] = $start_number;
        }
        if ($name != null) {
            $filter[] = db_kwd::RESULT_NAME . "=?";
            $parameters[] = $name;
        }
        if ($club != null) {
            $filter[] = db_kwd::RESULT_CLUB . "=?";
            $parameters[] = $club;
        }

        // Make $filter (a) string again!
        if ($filter != null)
            $filter = "WHERE " . implode(" AND ", $filter); // "Decode" filter array to useful string
        else
            $filter = "WHERE 1"; // Add behavior to list all results if no filter is applied

        // Create SQL query
        $statement = $db->prepare("SELECT " . implode(", ", [
            db_kwd::RESULT_ID,
            db_kwd::RESULT_TIMESTAMP,
            db_kwd::RESULT_DISCIPLINE,
            db_kwd::RESULT_START_NUMBER,
            db_kwd::RESULT_NAME,
            db_kwd::RESULT_CLUB,
            db_kwd::RESULT_SCORE_SUBMITTED,
            db_kwd::RESULT_SCORE_ACCOMPLISHED,
            db_kwd::RESULT_TIME,
            db_kwd::RESULT_FINISHED
        ]) .
            " FROM " . db_config::TABLE_RESULT . " $filter;");

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

            default:
                break;
        }*/

        // execute statement
        $statement->execute($parameters);

        // bind result values to statement
        $statement->bind_result($_1, $_2, $_3, $_4, $_5, $_6, $_7, $_8, $_9, $_10);

        // iterate over results
        while ($statement->fetch()) {
            $entry = new result();
            $entry->parse($_1, $_2, $_3, $_4, $_5, $_6, $_7, $_8, $_9, $_10);

            // append to list
            $return[] = $entry;
        }

        // return array of results
        return $return;
    }

    /**
     * Searches for results in the database by a competition id (timestamp for deltas is supported)
     * 
     * This function joins results and discipline table (at discipline's id) and then searches for competition id. 
     * This is all done with a single MySQL statement:
     * `SELECT results.* FROM results LEFT JOIN disciplines ON results.discipline = disciplines.ID WHERE disciplines.competition = ? [AND timestamp >=?];`
     * 
     * @param mysqli $db The database to work with
     * @param int $competition_id The id of the competition the desired results are indirectly (via disciplines) assigned to.
     * @param ?DateTime $modified_since Get results that were modified after the time passed
     * 
     * @return results[] The results that were found
     */
    public static function searchByCompetition(
        mysqli $db,
        int $competition_id,
        ?DateTime $modified_since = null,
    ): array {
        // empty return
        $return = [];

        // empty timestamp filter
        $timestamp =  "";

        // parameters
        $parameters = [$competition_id];

        // check if additional filter for timestamp should be added
        if ($modified_since != null) {
            $timestamp = " AND " . db_config::TABLE_RESULT . "." . db_kwd::RESULT_TIMESTAMP . ">=?";
            $parameters[] = $modified_since->format('Y-m-d H:i:s');
        }

        // small function is applied to all array elements and adds name of the table in front of it
        // accessing the function by name seems to be the fastest way https://stackoverflow.com/questions/18144782
        function addTableName($field)
        {
            return db_config::TABLE_RESULT . "." . strval($field);
        };

        // SELECT dev_results_liveticker.* FROM dev_results_liveticker LEFT JOIN dev_disciplines_liveticker ON dev_results_liveticker.discipline = dev_disciplines_liveticker.ID WHERE dev_disciplines_liveticker.competition = ? AND timestamp>=?;
        // Create SQL query
        $statement = $db->prepare("SELECT " .
            // select fields    
            implode(
                ", ",
                // add name of the table in front of the field names
                array_map(
                    'db\addTableName',
                    [
                        db_kwd::RESULT_ID,
                        db_kwd::RESULT_TIMESTAMP,
                        db_kwd::RESULT_DISCIPLINE,
                        db_kwd::RESULT_START_NUMBER,
                        db_kwd::RESULT_NAME,
                        db_kwd::RESULT_CLUB,
                        db_kwd::RESULT_SCORE_SUBMITTED,
                        db_kwd::RESULT_SCORE_ACCOMPLISHED,
                        db_kwd::RESULT_TIME,
                        db_kwd::RESULT_FINISHED
                    ]
                )
            ) .
            " FROM " . db_config::TABLE_RESULT .
            " LEFT JOIN " . db_config::TABLE_DISCIPLINE .
            " ON " . db_config::TABLE_RESULT . "." . db_kwd::RESULT_DISCIPLINE . " = " . db_config::TABLE_DISCIPLINE . "." . db_kwd::DISCIPLINE_ID .
            " WHERE " . db_kwd::DISCIPLINE_COMPETITION . "=?" .
            $timestamp .
            ";");

        // execute statement
        $statement->execute($parameters);

        // bind result values to statement
        $statement->bind_result($_1, $_2, $_3, $_4, $_5, $_6, $_7, $_8, $_9, $_10);

        // iterate over results
        while ($statement->fetch()) {
            $entry = new result();
            $entry->parse($_1, $_2, $_3, $_4, $_5, $_6, $_7, $_8, $_9, $_10);

            // append to list
            $return[] = $entry;
        }

        // return array of results
        return $return;
    }

    /**
     * Note the timestamp won't be updated on returned results, use search with result_id for that
     */
    public static function add(mysqli $db, array $representatives): array
    {
        // empty return array
        $return = [];

        // use prepared statement to prevent SQL injections
        $statement = $db->prepare("INSERT INTO " . db_config::TABLE_RESULT . " (" .
            implode(", ", [
                db_kwd::RESULT_DISCIPLINE,
                db_kwd::RESULT_START_NUMBER,
                db_kwd::RESULT_NAME,
                db_kwd::RESULT_CLUB,
                db_kwd::RESULT_SCORE_SUBMITTED,
                db_kwd::RESULT_SCORE_ACCOMPLISHED,
                db_kwd::RESULT_TIME,
                db_kwd::RESULT_FINISHED
            ])
            . ") VALUES (?, ?, ?, ?, ?, ?, ?, ?);");

        // bind parameters to statement
        $statement->bind_param(
            "iissddii",
            $result_discipline_id,
            $result_start_number,
            $result_name,
            $result_club,
            $result_score_submitted,
            $result_score_accomplished,
            $result_time,
            $result_finished
        );

        // iterate through array of results and add to database
        foreach ($representatives as &$result) {
            $result_discipline_id = $result->{result::KEY_DISCIPLINE_ID};
            $result_start_number = $result->{result::KEY_START_NUMBER};
            $result_name = $result->{result::KEY_NAME};
            $result_club = $result->{result::KEY_CLUB};
            $result_score_submitted = $result->{result::KEY_SCORE_SUBMITTED};
            $result_score_accomplished = $result->{result::KEY_SCORE_ACCOMPLISHED};
            $result_time = $result->{result::KEY_TIME};
            $result_finished = (int) $result->{result::KEY_FINISHED};

            if (!$statement->execute()) {
                error_log("error while writing result to database");

                // prevent rest of the loop from being executed
                continue;
            }

            // update id in result and add it to the return statement
            $return[] = $result->updateId($db->insert_id);
        }

        return $return;
    }

    // explained in the interface
    public static function edit(mysqli $db, RepresentativeInterface $representative, array $keys): bool
    {
        // convert the names from representative fields to database fields

        // map names together (id is skipped since you cant change it anyways, timestamp is because it's auto generated)
        $key_map = [
            result::KEY_DISCIPLINE_ID => db_kwd::RESULT_DISCIPLINE,
            result::KEY_START_NUMBER => db_kwd::RESULT_START_NUMBER,
            result::KEY_NAME => db_kwd::RESULT_NAME,
            result::KEY_CLUB => db_kwd::RESULT_CLUB,
            result::KEY_SCORE_SUBMITTED => db_kwd::RESULT_SCORE_SUBMITTED,
            result::KEY_SCORE_ACCOMPLISHED => db_kwd::RESULT_SCORE_ACCOMPLISHED,
            result::KEY_TIME => db_kwd::RESULT_TIME,
            result::KEY_FINISHED => db_kwd::RESULT_FINISHED
        ];

        // empty arrays to hold fields that should be updated
        $fields = []; // field names in database containing an additional =? for sql query
        $params = []; // values to insert in database

        // treat finished object differently
        $array_key_finished = array_search(result::KEY_FINISHED, $keys);
        if (false !== $array_key_finished) {
            // add to fields
            $fields[] = $key_map[result::KEY_FINISHED] . "=? ";
            // convert to int and add to params
            $params[] = intval($representative->{result::KEY_FINISHED});
            // remove key from array to prevent multiple addition
            unset($keys[$array_key_finished]);
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
        $params[] = $representative->{discipline::KEY_ID};

        // use prepared statement to prevent SQL injections
        $statement = $db->prepare("UPDATE " . db_config::TABLE_RESULT . " SET " .
            implode(", ", $fields)
            . " WHERE " . db_kwd::RESULT_ID . "=?");

        // execute statement with prepared values
        return $statement->execute($params);
    }

    // explained in the interface
    public static function remove(mysqli $db, array $representatives): void
    {
        // prepare statement
        $statement = $db->prepare("DELETE FROM " . db_config::TABLE_RESULT . " WHERE " . db_kwd::RESULT_ID . "=?");
        $statement->bind_param("i", $ID);

        // iterate through array and execute statement for different ids
        foreach ($representatives as &$result) {
            $ID = $result->{result::KEY_ID};
            $statement->execute();
        }
    }

    // explained int the interface
    public static function makeRepresentativeDbReady(mysqli $db, RepresentativeInterface &$representative): int
    {
        // variable for error messages
        $error = 0;

        // get values form old representative
        $old_id = $representative->{result::KEY_ID};
        $old_timestamp = $representative->{result::KEY_TIMESTAMP};
        $old_discipline = $representative->{result::KEY_DISCIPLINE_ID};
        $new_start_number = $representative->{result::KEY_START_NUMBER};
        $new_name = $representative->{result::KEY_NAME};
        $new_club = $representative->{result::KEY_CLUB};
        $new_score_submitted = $representative->{result::KEY_SCORE_SUBMITTED};
        $new_score_accomplished = $representative->{result::KEY_SCORE_ACCOMPLISHED};
        $new_time = $representative->{result::KEY_TIME};
        $new_finished = $representative->{result::KEY_FINISHED};

        // timestamp won't be checked because it's never written to database (only relevant when getting a result form it)

        // check if invalid characters are present in string, if so remove them and add error
        if (strcmp($new_name, $db->real_escape_string($new_name)) != 0) {
            $new_name = $db->real_escape_string($new_name);
            $error |= result::ERROR_NAME;
        }

        if (strcmp($new_club, $db->real_escape_string($new_club)) != 0) {
            $new_club = $db->real_escape_string($new_club);
            $error |= result::ERROR_CLUB;
        }

        // check if integers are within their correct range, if not make them 0 and add error

        // start number is greater or equal 0 by definition (max. value determined by database)
        if ($new_start_number < 0) {
            $new_start_number = 0;
            $error |= result::ERROR_START_NUMBER;
        }
        if ($new_start_number > 65535) {
            $new_start_number = 65535;
            $error |= result::ERROR_START_NUMBER;
        }

        // time is greater or equal 0 by definition (max. value determined by database)
        if ($new_time < 0) {
            $new_time = 0;
            $error |= result::ERROR_TIME;
        }
        if ($new_time > 65535) {
            $new_time = 65535;
            $error |= result::ERROR_TIME;
        }

        // check if floats are in their correct range
        if ($new_score_submitted < 0) {
            $new_score_submitted = 0.0;
            $error |= result::ERROR_SCORE_SUBMITTED;
        }
        if ($new_score_accomplished < 0) {
            $new_score_accomplished = 0.0;
            $error |= result::ERROR_SCORE_ACCOMPLISHED;
        }

        // finished is seen as a boolean (so make it one)
        // 0 for everything < 0
        if ($new_finished < 0) {
            $new_finished = 0;
            $error |= result::ERROR_FINISHED;
        }
        // 1 for everything > 1
        if ($new_finished > 1) {
            $new_finished = 1;
            $error |= result::ERROR_FINISHED;
        }

        // overwrite result with new one containing the newly created variables
        $representative = new result(
            $old_id,
            $old_timestamp,
            $old_discipline,
            $new_start_number,
            $new_name,
            $new_club,
            $new_score_submitted,
            $new_score_accomplished,
            $new_time,
            $new_finished
        );

        // return errors
        return $error;
    }
}
