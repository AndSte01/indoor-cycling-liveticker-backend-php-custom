<?php

/**
 * Constants used for interaction with Database
 * 
 * This file contains all necessary constants to interact with the database
 * 
 * @package Database\Config
 */

// assign namespace
namespace db;

/**
 * Global definitions used to configure database
 */
class db_config
{
    /** @var string Name of the database */
    const NAME     = "liveticker";
    /** @var string Name of a user that has read/write access to the database */
    const USER     = 'liveticker';
    /** @var string The password of the user (see USER) */
    const PASSWORD = 'mysqlliveticker';
    /** @var string Hostname of the Database server */
    const HOST     = "localhost";
}
