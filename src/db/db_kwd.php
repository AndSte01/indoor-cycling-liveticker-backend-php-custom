<?php

/**
 * Constants used for interaction with Database
 * 
 * This file contains all the definitions for the database tables (keywords)
 * 
 * @package Database\Config
 */

// assign namespace
namespace db;

/**
 * Definition of constants used to work with the tables in the database
 */
class db_kwd
{

    // name of the tables
    const TABLE_USER        = "dev_users_liveticker";
    const TABLE_COMPETITION = "dev_competitions_liveticker";
    const TABLE_DISCIPLINE  = "dev_disciplines_liveticker";
    const TABLE_RESULT      = "dev_results_liveticker";
    const TABLE_SCOREBOARD  = "dev_scoreboards_liveticker";

    // for const TABLE_USER
    const USER_ID               = "ID";
    const USER_NAME             = "name";
    const USER_ROLE             = "role";
    const USER_PASSWORD_HASH    = "password_hash";
    const USER_PASSWORD_SALT    = "password_salt";
    const USER_BINARY_TIMESTAMP = "binary_timestamp";
    const USER_BINARY_TOKEN     = "binary_token";

    // for const TABLE_COMPETITION
    const COMPETITION_ID          = "ID";
    const COMPETITION_DATE        = "date";
    const COMPETITION_NAME        = "name";
    const COMPETITION_LOCATION    = "location";
    const COMPETITION_USER        = "user";
    const COMPETITION_AREAS       = "areas";
    const COMPETITION_FEATURE_SET = "feature_set";
    const COMPETITION_LIVE        = "live";

    // for const TABLE_DISCIPLINE
    const DISCIPLINE_ID            = "ID";
    const DISCIPLINE_TIMESTAMP     = "timestamp";
    const DISCIPLINE_COMPETITION   = "competition";
    const DISCIPLINE_TYPE          = "type";
    const DISCIPLINE_FALLBACK_NAME = "fallback_name";
    const DISCIPLINE_AREA          = "area";
    const DISCIPLINE_ROUND         = "round";
    const DISCIPLINE_FINISHED      = "finished";

    // for const TABLE_RESULT
    const RESULT_ID                 = "ID";
    const RESULT_TIMESTAMP          = "timestamp";
    const RESULT_DISCIPLINE         = "discipline";
    const RESULT_START_NUMBER       = "start_number";
    const RESULT_NAME               = "name";
    const RESULT_CLUB               = "club";
    const RESULT_SCORE_SUBMITTED    = "score_submitted";
    const RESULT_SCORE_ACCOMPLISHED = "score_accomplished";
    const RESULT_FINISHED           = "finished";

    // for const TABLE_SCOREBOARD
    const SCOREBOARD_INTERNAL_ID = "ID";
    const SCOREBOARD_EXTERNAL_ID = "external_id";
    const SCOREBOARD_TIMESTAMP   = "timestamp";
    const SCOREBOARD_COMPETITION = "competition";
    const SCOREBOARD_CONTENT     = "content";
    const SCOREBOARD_CUSTOM_TEXT = "custom_text";
    const SCOREBOARD_TIMER_STATE = "timer_state";
    const SCOREBOARD_TIMER_VALUE = "timer_value";
}

/**
 * Definition of constants describing properties for the different columns of the database
 */
class db_col_prop
{
    // for const table user
    const USER_PASSWORD_HASH_LENGTH = 64; // length in BYTES
    const USER_PASSWORD_SALT_LENGTH = 64; // length in BYTES
    const USER_BINARY_TOKEN_LENGTH  = 64; // length in BYTES
}
