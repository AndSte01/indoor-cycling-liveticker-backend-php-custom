<?php

/**
 * Methods used for interaction with database
 * 
 * This script is used for interaction with the database configured in "db_config.php", 
 * please do not invoke the methods directly, instead consider using the tools provided in "db_utils.php"
 * 
 * FUNCTIONS IN THIS SCRIPT DO NOT CHECK FOR ERRORS OR INVALID ARGUMENTS, USE FUNCTIONS PROVIDED IN "db_utils.php"!!!
 * 
 * 
 * users table
 * 
 * | column           | typ             | not null | default | extra                        | content                                   |
 * | ---------------- | --------------- | :------: | ------- | ---------------------------- | ----------------------------------------- |
 * | ID               | `INT`           |    X     |         | `AUTO_INCREMENT PRIMARY KEY` | Id of the user                            |
 * | name             | `text`          |    X     |         |                              | Name of the user                          |
 * | role             | `INT`           |    X     | 0       |                              | Role of the user (e. g. Admin)            |
 * | password_hash    | `VARBINARY(64)` |    X     |         |                              | hashed password of the user               |
 * | password_salt    | `VARBINARY(64)` |    X     |         |                              | salt used for hashing user password       |
 * | binary_timestamp | `TIMESTAMP`     |          |         |                              | time the binary token was generated       |
 * | binary_token     | `VARBINARY(64)` |          |         |                              | binary token used as part of bearer token |
 * 
 * 
 * competitions table
 * 
 * | column      | typ          | not null | default          | extra                                                                      | content                                       |
 * | ----------- | ------------ | :------: | ---------------- | -------------------------------------------------------------------------- | --------------------------------------------- |
 * | ID          | `INT`        |    X     |                  | `PRIMARY_KEY`                                                              | Id of the competition                         |
 * | date        | `date`       |    X     | `CURRENT_DATE()` |                                                                            | Date of the competition                       |
 * | name        | `text`       |          |                  |                                                                            | Name of the competition                       |
 * | location    | `text`       |          |                  |                                                                            | Location where the competition takes place    |
 * | user        | `INT`        |    ~     |                  | `FOREIGN KEY ... REFERENCES user(ID) ON DELETE SET NULL ON UPDATE CASCADE` | Id of the user the competition is assigned to |
 * | areas       | `TINYINT(1)` |    X     | 0                |                                                                            | Number of areas in the competition            |
 * | feature_set | `TINYINT(1)` |    X     | 0                |                                                                            | feature set of the competition                |
 * | live        | `TINYINT(1)` |    X     | 0                |                                                                            | Wether competition is live (1) or not (0)     |
 * 
 * ~: The database allows null, but the adaptor (part of this software) explicitly prevents the value from being written to the database.
 * 
 * 
 * disciplines table
 * 
 * | column        | typ          | not null | default               | extra                                                                            | content                                               |
 * | ------------- | ------------ | :------: | --------------------- | -------------------------------------------------------------------------------- | ----------------------------------------------------- |
 * | ID            | `INT`        |    X     |                       | `AUTO_INCREMENT PRIMARY_KEY`                                                     | The id of the discipline                              |
 * | timestamp     | `TIMESTAMP`  |    X     | `current_timestamp()` | `ON UPDATE current_timestamp()`                                                  | Timestamp for calculating deltas                      |
 * | competition   | `INT`        |    X     |                       | `FOREIGN KEY ... REFERENCES competition(ID) ON DELETE CASCADE ON UPDATE CASCADE` | Id of the competition the discipline is assigned to   |
 * | type          | `TINYINT(1)` |    X     | -1                    |                                                                                  | type of the discipline                                |
 * | fallback_name | `text`       |          |                       |                                                                                  | The name used in case type couldn't be set            |
 * | area          | `TINYINT(1)` |    X     | 1                     |                                                                                  | Area of the competition the discipline takes place on |
 * | round         | `TINYINT(1)` |    X     | 0                     | `UNSIGNED`                                                                       | Round of the competition the discipline is located in |
 * | finished      | `TINYINT(1)` |    X     | 1                     |                                                                                  | Wether the discipline is finished or not              |
 * 
 * explanation of type (used in discipline):
 * | `0`         | `000`      | `0`    | `000` |
 * | ----------- | ---------- | ------ | ----- |
 * | error, sign | Discipline | gender | age   |
 * 
 * |       | Discipline                     |   |     | gender     |    |       | age         |
 * | ----- | ------------------------------ |   | --- | ---------- |    | ----- | ----------- |
 * | `000` | Single artistic cycling        |   | `0` | male, open |    | `000` | reserved    |
 * | `001` | Pair artistic cycling          |   | `1` | female     |    | `001` | Pupils  U11 |
 * | `010` | Artistic Cycling Team 4 (ACT4) |                           | `010` | Pupils  U13 |
 * | `011` | Artistic Cycling Team 6 (ACT6) |                           | `011` | Pupils  U15 |
 * | `110` | Unicycle Team 4                |                           | `100` | Juniors U19 |
 * | `111` | Unicycle Team 6                |                           | `101` | Elite   O18 |
 * 
 * If the client doesn't support discipline by type, type should be set to (10000001 or. -1). Then the fallback_name should be set with a meaningful string.
 * 
 * 
 * results table
 * 
 * | column             | typ          | not null | default               | extra                                                                           | content                                                         |
 * | ------------------ | ------------ | :------: | --------------------- | ------------------------------------------------------------------------------- | --------------------------------------------------------------- |
 * | ID                 | `INT`        |    X     |                       | `AUTO_INCREMENT PRIMARY KEY`                                                    | The id of the result                                            |
 * | timestamp          | `TIMESTAMP`  |    X     | `current_timestamp()` | `ON UPDATE current_timestamp()`                                                 | Timestamp for calculating deltas                                |
 * | discipline         | `INT`        |    X     |                       | `FOREIGN KEY ... REFERENCES discipline(ID) ON DELETE CASCADE ON UPDATE CASCADE` | Id of the discipline the result is assigned to                  |
 * | start_number       | `SMALLINT`   |          |                       | `UNSIGNED`                                                                      | Start number of the competitor                                  |
 * | name               | `text`       |          |                       |                                                                                 | Name of the competitor                                          |
 * | club               | `text`       |          |                       |                                                                                 | Name of the club of the competitor                              |
 * | score_submitted    | `float`      |          |                       |                                                                                 | The score the competitor submitted                              |
 * | score_accomplished | `float`      |          |                       |                                                                                 | The score the competitor accomplished                           |
 * | time               | `SMALLINT`   |          | 0                     | `UNSIGNED`                                                                      | The current of the program (the competitor presents) in seconds |
 * | finished           | `TINYINT(1)` |    X     | 1                     |                                                                                 | Wether the competitor finished or not                           |
 * 
 * 
 * scoreboard table
 * 
 * | column      | typ          | not null | default               | extra                                                                            | content                                                                       |
 * | ----------- | ------------ | :------: | --------------------- | -------------------------------------------------------------------------------- | ----------------------------------------------------------------------------- |
 * | ID          | `INT`        |    X     |                       | `AUTO_INCREMENT PRIMARY KEY`                                                     | The id of the scoreboard                                                      |
 * | external_id | `TINYINT(1)` |    X     |                       |                                                                                  | The id used by the client to access the scoreboard (is predictable by client) |
 * | timestamp   | `TIMESTAMP`  |    X     | `current_timestamp()` | `ON UPDATE current_timestamp()`                                                  | Timestamp for calculating deltas                                              |
 * | competition | `INT`        |    X     |                       | `FOREIGN KEY ... REFERENCES competition(ID) ON DELETE CASCADE ON UPDATE CASCADE` | Id of the competition the scoreboard is assigned to                           |
 * | content     | `INT`        |    X     | 0                     |                                                                                  | integer describing the content of the scoreboard                              |
 * | custom_text | `text`       |          |                       |                                                                                  | custom text used in case `content == -1`                                      |
 * 
 * 
 * @package Database\Database
 * 
 * @todo make ID unsigned (some time in the future)
 * @todo consider renaming live in competition to finished (or not finished)
 */

// assign namespace
namespace db;

// define aliases
use DateTime;
use mysqli;

// Global database definitions
require_once(dirname(__FILE__) . "/../db_config.php");
require_once(dirname(__FILE__) . "/../db_kwd.php");

/**
 * A collection of generic functions used to interact with the database 
 */
class adaptorGeneric
{

    /**
     * Connect to database configured in db_config.php
     * 
     * @return mysqli database the function connected to
     */
    public static function connect(): mysqli
    {
        $db = mysqli_connect(db_config::HOST, db_config::USER, db_config::PASSWORD, db_config::NAME) or die(mysqli_connect_errno());
        return $db;
    }

    /**
     * Disconnect form database passed as parameter
     * 
     * @param mysqli $db database to disconnect
     * 
     * @deprecated use $db->close() directly
     */
    public static function disconnect(mysqli $db): void
    {
        $db->close();

        error_log("don't use disconnect() use \$db->close() instead;");
    }


    /**
     * Sets up the tables of the database
     * 
     * @param mysqli $db Database in which tables are created
     * 
     * @return ?string error message on error or null on success
     */
    public static function createTables(mysqli $db): ?string
    {
        // --- User Table ---

        // make query for TABLE_USER
        $query = "create table IF NOT EXISTS " . db_kwd::TABLE_USER . " ( " .
            db_kwd::USER_ID .                          " INT NOT NULL AUTO_INCREMENT, " .                                                // Id of the user
            db_kwd::USER_NAME .                        " text NOT NULL, " .                                                              // Username
            db_kwd::USER_ROLE .                        " INT NOT NULL DEFAULT 0, " .                                                     // Role
            db_kwd::USER_PASSWORD_HASH .               " VARBINARY(" . strval(db_col_prop::USER_PASSWORD_HASH_LENGTH) . ") NOT NULL, " . // Password hash
            db_kwd::USER_PASSWORD_SALT .               " VARBINARY(" . strval(db_col_prop::USER_PASSWORD_SALT_LENGTH) . ") NOT NULL, " . // salt used for hashing password
            db_kwd::USER_BINARY_TIMESTAMP .            " TIMESTAMP, " .                                                                  // Timestamp for the generated bearer token
            db_kwd::USER_BINARY_TOKEN                . " VARBINARY(" . strval(db_col_prop::USER_BINARY_TOKEN_LENGTH) . "), " .           // bearer token
            "PRIMARY KEY (" . db_kwd::USER_ID . ")" .
            ");";


        // execute query and do error handling
        if ($db->query($query) != true) {
            return "couldn't create table '" . db_kwd::TABLE_USER . "': " . $db->error;
        }


        // --- Competition Table ---

        // make query for TABLE_COMPETITION
        $query = "create table IF NOT EXISTS " . db_kwd::TABLE_COMPETITION . " ( " .
            db_kwd::COMPETITION_ID .          " INT NOT NULL AUTO_INCREMENT, " .            // Id of Competition
            db_kwd::COMPETITION_DATE .        " date NOT NULL DEFAULT (CURRENT_DATE()), " . // Date of competition  // WARN requires MySQL >8.0.13
            db_kwd::COMPETITION_NAME .        " text, " .
            db_kwd::COMPETITION_LOCATION .    " text, " .
            db_kwd::COMPETITION_USER .        " INT, " .
            db_kwd::COMPETITION_AREAS .       " TINYINT(1) NOT NULL DEFAULT 0, " .
            db_kwd::COMPETITION_FEATURE_SET . " TINYINT(1) NOT NULL DEFAULT 0, " .
            db_kwd::COMPETITION_LIVE .        " TINYINT(1) NOT NULL DEFAULT 0, " .          // 0 isn't Live, 1 is Live
            "PRIMARY KEY (" . db_kwd::COMPETITION_ID . "), " .
            "FOREIGN KEY (" . db_kwd::COMPETITION_USER . ") REFERENCES " . db_kwd::TABLE_USER . "(" . db_kwd::USER_ID . ") ON DELETE SET NULL ON UPDATE CASCADE" .
            ");";

        // execute query and do error handling
        if ($db->query($query) != true) {
            return "couldn't create table '" . db_kwd::TABLE_COMPETITION . "': " . $db->error;
        }


        // --- Discipline Table ---

        // make query for TABLE_DISCIPLINE
        $query = "create table  IF NOT EXISTS " . db_kwd::TABLE_DISCIPLINE . " ( " .
            db_kwd::DISCIPLINE_ID .            " INT NOT NULL AUTO_INCREMENT, " .                                                    // Id of discipline
            db_kwd::DISCIPLINE_TIMESTAMP .     " TIMESTAMP NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(), " .   // timestamp for calculating deltas
            db_kwd::DISCIPLINE_COMPETITION .   " INT NOT NULL, " .                                                                   // competition id
            db_kwd::DISCIPLINE_TYPE .          " TINYINT(1) NOT NULL DEFAULT -1, " .                                                 // type of the category 
            db_kwd::DISCIPLINE_FALLBACK_NAME . " text, " .                                                                           // fallback name, used in case of negative type
            db_kwd::DISCIPLINE_AREA .          " TINYINT(1) NOT NULL DEFAULT 1, " .                                                  // Area of the competition the discipline takes place on
            db_kwd::DISCIPLINE_ROUND .         " TINYINT(1) UNSIGNED NOT NULL DEFAULT 0, " .                                         // round of the discipline inside of the competition (e.g. preliminary and final round)
            db_kwd::DISCIPLINE_FINISHED .      " TINYINT(1) NOT NULL DEFAULT 1, " .                                                  // 0 ongoing, 1 done
            "PRIMARY KEY (" . db_kwd::DISCIPLINE_ID . "), " .
            "FOREIGN KEY (" . db_kwd::DISCIPLINE_COMPETITION . ") REFERENCES " . db_kwd::TABLE_COMPETITION . "(" . db_kwd::COMPETITION_ID . ") ON DELETE CASCADE ON UPDATE CASCADE" .
            ");";
        // execute query and do error handling
        if ($db->query($query) != true) {
            return "couldn't create table '" . db_kwd::TABLE_DISCIPLINE . "': " . $db->error;
        }


        // --- Results Table ---

        // make query for TABLE_RESULT
        $query = "create table IF NOT EXISTS " . db_kwd::TABLE_RESULT . " ( " .
            db_kwd::RESULT_ID .                   " INT NOT NULL AUTO_INCREMENT, " .                                                     // Id of result (INT is enough)
            db_kwd::RESULT_TIMESTAMP .            " TIMESTAMP NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(), " .    // timestamp for calculating deltas
            db_kwd::RESULT_DISCIPLINE .           " INT NOT NULL, " .                                                                    // id of the discipline
            db_kwd::RESULT_START_NUMBER .         " SMALLINT UNSIGNED, " .                                                               // 0 in case of Twitter
            db_kwd::RESULT_NAME .                 " text, " .
            db_kwd::RESULT_CLUB .                 " text, " .                                                                            // empty in case of Twitter
            db_kwd::RESULT_SCORE_SUBMITTED .      " float, " .                                                                           // -1 in case of Twitter
            db_kwd::RESULT_SCORE_ACCOMPLISHED .   " float, " .
            db_kwd::RESULT_FINISHED .             " TINYINT(1) NOT NULL DEFAULT 1, " .                                                   // 0 ongoing, 1 done
            "PRIMARY KEY (" . db_kwd::RESULT_ID . "), " .
            "FOREIGN KEY (" . db_kwd::RESULT_DISCIPLINE . ") REFERENCES " . db_kwd::TABLE_DISCIPLINE . "(" . db_kwd::DISCIPLINE_ID . ") ON DELETE CASCADE ON UPDATE CASCADE" .
            ");";

        // execute query and do error handling
        if ($db->query($query) != true) {
            return "couldn't create table '" . db_kwd::TABLE_RESULT . "': " . $db->error;
        }


        // --- Scoreboard Table ---

        // make query for TABLE_SCOREBOARD
        $query = "create table IF NOT EXISTS " . db_kwd::TABLE_SCOREBOARD . " ( " .
            db_kwd::SCOREBOARD_INTERNAL_ID .      " INT NOT NULL AUTO_INCREMENT, " .                                                  // Id of result (INT is enough)
            db_kwd::SCOREBOARD_EXTERNAL_ID .      " TINYINT(1) NOT NULL, " .
            db_kwd::SCOREBOARD_TIMESTAMP .        " TIMESTAMP NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(), " . // timestamp for calculating deltas
            db_kwd::SCOREBOARD_COMPETITION .      " INT NOT NULL, " .                                                                 // id of the competition
            db_kwd::SCOREBOARD_CONTENT .          " INT NOT NULL DEFAULT 0, " .                                                       // content of the scoreboard
            db_kwd::SCOREBOARD_CUSTOM_TEXT .      " text, " .                                                                         // custom text of the scoreboard
            "PRIMARY KEY (" . db_kwd::SCOREBOARD_INTERNAL_ID . "), " .
            "FOREIGN KEY (" . db_kwd::SCOREBOARD_COMPETITION . ") REFERENCES " . db_kwd::TABLE_COMPETITION . "(" . db_kwd::COMPETITION_ID . ") ON DELETE CASCADE ON UPDATE CASCADE" .
            ");";

        // execute query and do error handling
        if ($db->query($query) != true) {
            return "couldn't create table '" . db_kwd::TABLE_RESULT . "': " . $db->error;
        }

        return null;
    }

    /**
     * Function dropping (deleting) all tables.
     * WARNING: This action might result in irrecoverable data loss
     * 
     * @param mysqli $db the database to work with
     * 
     * @return ?string error message on failure or null on success
     */
    public static function dropTables(mysqli $db): ?string
    {
        // create array with table names, the order matters (order by foreign keys)
        $table_names = [db_kwd::TABLE_RESULT, db_kwd::TABLE_DISCIPLINE, db_kwd::TABLE_SCOREBOARD, db_kwd::TABLE_COMPETITION, db_kwd::TABLE_USER];

        // iterate over table names
        foreach ($table_names as $table_name) {
            // check if truncating was successful
            if ($db->query("DROP TABLE IF EXISTS " . $table_name) != true)
                // report error
                return "couldn't truncate table '" . $table_name . "': " . $db->error;
        }

        // default return
        return null;
    }

    /**
     * Returns the current time of the database (might be different if MySQL server and php server aren't the same device).
     * The way an unsuccessful query is handled might be irritating (returning the php servers timer) but makes sense because, it helps code relying
     * on this functions not to break, furthermore if the MySQL server can't return it's current time it probably has an error preventing it from handling all
     * query relying on an accurate timestamp.
     * 
     * @param mysqli $db Database in which tables are created
     * @return DateTime time of MySQL server (in case of error the time of the php server is returned)
     */
    public static function getCurrentTime(mysqli $db): DateTime
    {
        // prepare statement to request current timestamp
        $statement = $db->prepare("SELECT UNIX_TIMESTAMP(NOW())");

        // execute statement and check if it was executed successfully
        if ($statement->execute() == false) {
            // return the current time of the server
            return new DateTime();
        }

        // bind variable to result
        $statement->bind_result($timestamp);

        // no while required because only one result will be sent
        $statement->fetch();

        // try to generate DateTime from result, if it fails, log error and set time to current server time
        try {
            $time = new DateTime("@" . $timestamp); // set timestamp from database
        } catch (\Exception $e) {
            error_log($e);
            return new DateTime();
        }

        // return the mysql server time as DateTime object
        return $time;
    }

    /**
     * Cleans database by searching for elements that lack a parent and removing it.
     * 
     * Warning, this operation might be database intense so only run it if necessary
     * 
     * @param mysqli $db The database to clean
     */
    public static function optimize(mysqli $db): void
    {
        // OPTIMIZE TABLE users; 

        // optimize users
        $db->query("OPTIMIZE TABLE " . db_kwd::TABLE_USER);

        // optimize competitions
        $db->query("OPTIMIZE TABLE " . db_kwd::TABLE_COMPETITION);

        // optimize disciplines
        $db->query("OPTIMIZE TABLE " . db_kwd::TABLE_DISCIPLINE);

        // optimize results
        $db->query("OPTIMIZE TABLE " . db_kwd::TABLE_RESULT);

        // optimize scoreboards
        $db->query("OPTIMIZE TABLE " . db_kwd::TABLE_SCOREBOARD);
    }
}
