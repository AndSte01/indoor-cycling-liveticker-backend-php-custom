<?php

/**
 * Constants containing core information about the app
 * 
 * This file contains constant helping with versioning of the app
 * 
 * @package Database/info
 */

// assign namespace
namespace db;

/**
 * Global definitions used to configure database
 */
class db_info
{
    /** @var string Version of the api */
    const API_VERSION = "1.0"; // major.minor.bugfix
    /** @var string Version of the software */
    const BACKEND_VERSION = "beta"; // major.minor.bugfix
    /** @var string Type of the backend */
    const BACKEND_TYPE = "git: AndSte01/indoor-cycling-liveticker-backend-php";
}
