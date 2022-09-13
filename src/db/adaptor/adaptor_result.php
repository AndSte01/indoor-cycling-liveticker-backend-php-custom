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
        ?array $discipline_id = null,
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
            $filter[] = db_kwd::RESULT_DISCIPLINE .
                " IN(?" . // if array is not null at least one element is in there (this is it's question mark)
                str_repeat(",?", count($discipline_id) - 1) . // add additional question marks for id's that aren't stored in the first array element
                ")";
            // merge the two arrays
            // array_values is used to make $discipline_id (that might be an assoc array) a list (see array_is_list)
            // array_map is used to convert to strings
            $parameters = array_merge($parameters, array_values(array_map('strval', $discipline_id)));
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
            $entry->parse($_1, $_2, $_3, $_4, $_5, $_6, $_7, $_8, $_9, $_10, $db);

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

        // check if addititonal filter for timestamp should be added
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
            $entry->parse($_1, $_2, $_3, $_4, $_5, $_6, $_7, $_8, $_9, $_10, $db);

            // append to list
            $return[] = $entry;
        }

        // return array of results
        return $return;
    }

    /**
     * Note the timestamp won't be updated on returned results, use search with result_id for that
     */
    public static function add(mysqli $db, array $results): array
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
            . ") VALUES (?, ?, ?, ?, ?, ?, ? ,?);");

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
        foreach ($results as &$result) {
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
    public static function edit(mysqli $db, array $results): void
    {
        // use prepared statement to prevent SQL injections
        $statement = $db->prepare("UPDATE " . db_config::TABLE_RESULT . " SET " .
            implode(", ", [
                db_kwd::RESULT_DISCIPLINE . "=? ",
                db_kwd::RESULT_START_NUMBER . "=? ",
                db_kwd::RESULT_NAME . "=? ",
                db_kwd::RESULT_CLUB . "=? ",
                db_kwd::RESULT_SCORE_SUBMITTED . "=? ",
                db_kwd::RESULT_SCORE_ACCOMPLISHED . "=? ",
                db_kwd::RESULT_TIME . "=? ",
                db_kwd::RESULT_FINISHED . "=? "
            ])
            . " WHERE " . db_kwd::RESULT_ID . "=?");

        // bind parameters to statement
        $statement->bind_param(
            "iissddiii",
            $result_discipline_id,
            $result_start_number,
            $result_name,
            $result_club,
            $result_score_submitted,
            $result_score_accomplished,
            $result_time,
            $result_finished,
            $result_id
        );

        // iterate through array of results and add to database
        foreach ($results as &$result) {
            $result_discipline_id = $result->{result::KEY_DISCIPLINE_ID};
            $result_start_number = $result->{result::KEY_START_NUMBER};
            $result_name = $result->{result::KEY_NAME};
            $result_club = $result->{result::KEY_CLUB};
            $result_score_submitted = $result->{result::KEY_SCORE_SUBMITTED};
            $result_score_accomplished = $result->{result::KEY_SCORE_ACCOMPLISHED};
            $result_time = $result->{result::KEY_TIME};
            $result_finished = (int) $result->{result::KEY_FINISHED};
            $result_id = $result->{result::KEY_ID};

            if (!$statement->execute()) {
                error_log("error while writing result to database");
            }
        }
    }

    // explained in the interface
    public static function remove(mysqli $db, array $results): void
    {
        // prepare statement
        $statement = $db->prepare("DELETE FROM " . db_config::TABLE_RESULT . " WHERE " . db_kwd::RESULT_ID . "=?");
        $statement->bind_param("i", $ID);

        // iterate through array and execute statement for different ids
        foreach ($results as &$result) {
            $ID = $result->{result::KEY_ID};
            $statement->execute();
        }
    }
}
