<?php

/**
 * Simple connection test
 * 
 * @package IO
 */

// define aliases
use db\db_info;

// Error logging
// ini_set('display_errors', 1);
// error_reporting(E_ALL);

// import required files
require_once("db/db_info.php");

// set correct content type
header("Content-Type: application/json");

printf(json_encode([
    "api" => db_info::API_VERSION,
    "backend" => db_info::BACKEND_TYPE,
    "backend_version" => db_info::BACKEND_VERSION
]));
