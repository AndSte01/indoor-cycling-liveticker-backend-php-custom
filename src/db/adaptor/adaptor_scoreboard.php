<?php

/**
 * adaptor to deal with scoreboards in the database
 * 
 * this file contains a set of methods and constants to work with scoreboards in the database, in an complicated not easy way to use.
 * 
 * FUNCTIONS IN THIS SCRIPT DO NOT CHECK FOR ERRORS OR INVALID ARGUMENTS, USE FUNCTIONS PROVIDED IN THE CORRESPONDING MANAGER!!!
 * 
 * @package Database\Database
 */

// assign namespace
namespace db;

// import required files
require_once("adaptor_interface.php");
require_once(dirname(__FILE__) . "/../representatives/representative_scoreboard.php");
require_once(dirname(__FILE__) . "/../db_config.php");
require_once(dirname(__FILE__) . "/../db_kwd.php");

// define aliases
use DateTime;
use mysqli;

/**
 * Database adaptor for scoreboards
 */
class adaptorScoreboard implements adaptorInterface
{
    /**
     * @param ?int $scoreboard_id Id of the scoreboard to search for
     * @param ?int $external_id Id used by the client to identify the scoreboard (predictable by client and unique in combination with competition id)
     * @param ?int $competition_id Id of the competition whose scoreboards should be returned
     * @param ?DateTime $modified_since Get scoreboards that were modified after the time passed
     * @param ?int $content The content of the scoreboard
     * @param ?string $custom_name The custom text of the scoreboard
     */
    public static function search(
        mysqli $db,
        ?int $scoreboard_internal_id = null,
        ?int $scoreboard_external_id = null,
        ?int $competition_id = null,
        ?DateTime $modified_since = null,
        ?int $content = null,
        ?string $custom_text = null,
    ): array {
        // empty return
        $return = [];

        // Put search filters and corresponding parameters in an array
        $filter = [];
        $parameters = [];

        // check if filters need to be set
        if (($scoreboard_internal_id !== null)) { // also true if empty array
            $filter[] = db_kwd::SCOREBOARD_INTERNAL_ID . "=?";
            $parameters[]  = strval($scoreboard_internal_id);
        }
        if (($scoreboard_external_id !== null)) { // also true if empty array
            $filter[] = db_kwd::SCOREBOARD_EXTERNAL_ID . "=?";
            $parameters[]  = strval($scoreboard_external_id);
        }
        if ($competition_id !== null) {
            $filter[] = db_kwd::SCOREBOARD_COMPETITION . "=?";
            $parameters[] = strval($competition_id);
        }
        if ($modified_since != null) {
            // greater or equal is required so no scoreboards with "bad timing" are missed,
            // with this implementation in the worst case the client gets an scoreboards that wasn't relay updated
            $filter[] = db_kwd::SCOREBOARD_TIMESTAMP . ">=?";
            $parameters[] = $modified_since->format('Y-m-d H:i:s');
        }
        if ($content != null) {
            $filter[] = db_kwd::SCOREBOARD_CONTENT . "=?";
            $parameters[] = $content;
        }
        if ($custom_text != null) {
            $filter[] = db_kwd::SCOREBOARD_CUSTOM_TEXT . "=?";
            $parameters[] = $custom_text;
        }

        // Make $filter (a) string again!
        if ($filter != null)
            $filter = "WHERE " . implode(" AND ", $filter); // "Decode" filter array to useful string
        else
            $filter = "WHERE 1"; // Add behavior to list all scoreboards if no filter is applied

        // Create SQL query
        $statement = $db->prepare("SELECT " . implode(", ", [
            db_kwd::SCOREBOARD_INTERNAL_ID,
            db_kwd::SCOREBOARD_EXTERNAL_ID,
            db_kwd::SCOREBOARD_TIMESTAMP,
            db_kwd::SCOREBOARD_COMPETITION,
            db_kwd::SCOREBOARD_CONTENT,
            db_kwd::SCOREBOARD_CUSTOM_TEXT
        ]) .
            " FROM " . db_kwd::TABLE_SCOREBOARD . " $filter;");

        // execute statement
        $statement->execute($parameters);

        // bind result values to statement
        $statement->bind_result($_1, $_2, $_3, $_4, $_5, $_6);

        // iterate over results
        while ($statement->fetch()) {
            $entry = new scoreboard();
            $entry->parse($_1, $_2, $_3, $_4, $_5, $_6);

            // append to list
            $return[] = $entry;
        }

        // return array of scoreboards
        return $return;
    }

    /**
     * Note the timestamp won't be updated on returned scoreboard, use search with scoreboard_id for that
     */
    public static function add(mysqli $db, array $representatives): array
    {
        // empty return array
        $return = [];

        // use prepared statement to prevent SQL injections
        $statement = $db->prepare("INSERT INTO " . db_kwd::TABLE_SCOREBOARD . " (" .
            implode(", ", [
                db_kwd::SCOREBOARD_EXTERNAL_ID,
                db_kwd::SCOREBOARD_COMPETITION,
                db_kwd::SCOREBOARD_CONTENT,
                db_kwd::SCOREBOARD_CUSTOM_TEXT
            ])
            . ") VALUES (?, ?, ?, ?);");

        // bind parameters to statement
        $statement->bind_param(
            "iiis",
            $scoreboard_external_id,
            $scoreboard_competition_id,
            $scoreboard_content,
            $scoreboard_custom_text
        );

        // iterate through array of scoreboards and add to database
        foreach ($representatives as &$scoreboard) {
            $scoreboard_external_id = $scoreboard->{scoreboard::KEY_EXTERNAL_ID};
            $scoreboard_competition_id = $scoreboard->{scoreboard::KEY_COMPETITION_ID};
            $scoreboard_content = $scoreboard->{scoreboard::KEY_CONTENT};
            $scoreboard_custom_text = $scoreboard->{scoreboard::KEY_CUSTOM_TEXT};

            if (!$statement->execute()) {
                error_log("error while writing scoreboard to database");

                // prevent rest of the loop from being executed
                continue;
            }

            // update id in scoreboard and add it to the return statement
            $return[] = $scoreboard->updateId($db->insert_id);
        }

        return $return;
    }

    // explained in the interface
    public static function edit(mysqli $db, RepresentativeInterface $representative, array $keys): bool
    {
        // convert the names of representative fields to database fields

        // map names together (id is skipped since you can't change it anyways, timestamp is because it's auto generated)
        $key_map = [
            scoreboard::KEY_EXTERNAL_ID => db_kwd::SCOREBOARD_EXTERNAL_ID,
            scoreboard::KEY_COMPETITION_ID => db_kwd::SCOREBOARD_COMPETITION,
            scoreboard::KEY_CONTENT => db_kwd::SCOREBOARD_CONTENT,
            scoreboard::KEY_CUSTOM_TEXT => db_kwd::SCOREBOARD_CUSTOM_TEXT
        ];

        // empty arrays to hold fields that should be updated
        $fields = []; // field names in database containing an additional =? for sql query
        $params = []; // values to insert in database

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
        $params[] = $representative->{scoreboard::KEY_INTERNAL_ID};

        // use prepared statement to prevent SQL injections
        $statement = $db->prepare("UPDATE " . db_kwd::TABLE_SCOREBOARD . " SET " .
            implode(", ", $fields)
            . " WHERE " . db_kwd::SCOREBOARD_INTERNAL_ID . "=?");

        // execute statement with prepared values
        return $statement->execute($params);
    }

    // explained in the interface
    public static function remove(mysqli $db, array $representatives): void
    {
        // prepare statement
        $statement = $db->prepare("DELETE FROM " . db_kwd::TABLE_SCOREBOARD . " WHERE " . db_kwd::SCOREBOARD_INTERNAL_ID . " = ?");
        $statement->bind_param("i", $ID);

        // iterate through array and execute statement for different ids
        foreach ($representatives as &$scoreboard) {
            $ID = $scoreboard->{scoreboard::KEY_INTERNAL_ID};
            $statement->execute();
        }
    }

    // explained int the interface
    public static function makeRepresentativeDbReady(mysqli $db, RepresentativeInterface &$representative): int
    {
        // variable for storing errors
        $error = 0;

        // get values from old representative
        $old_id = $representative->{scoreboard::KEY_INTERNAL_ID};
        $new_external_id = $representative->{scoreboard::KEY_EXTERNAL_ID};
        $old_timestamp = $representative->{scoreboard::KEY_TIMESTAMP};
        $old_competition_id = $representative->{scoreboard::KEY_COMPETITION_ID};
        $new_content = $representative->{scoreboard::KEY_CONTENT};
        $new_custom_text = $representative->{scoreboard::KEY_CUSTOM_TEXT};

        // check if invalid characters are present in string, if so remove them and add error
        if (strcmp($new_custom_text, $db->real_escape_string($new_custom_text)) != 0) {
            $new_custom_text = $db->real_escape_string($new_custom_text);
            $error |= scoreboard::ERROR_CUSTOM_TEXT;
        }

        // validate external id
        if ($new_external_id < 1) {
            $new_external_id = 0; // mark scoreboard as defect in database
        }

        // validate scoreboard content and in case of error set to 0
        if ($new_content < -3) {
            $new_content = 0;
            $error |= scoreboard::ERROR_CONTENT;
        }

        // overwrite scoreboard with new one containing the newly created variables
        $representative = new scoreboard(
            $old_id,
            $new_external_id,
            $old_timestamp,
            $old_competition_id,
            $new_content,
            $new_custom_text
        );

        // return possible errors
        return $error;
    }
}
