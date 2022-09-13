<?php

/**
 * Interface used to define a competition manager
 * 
 * @package Database\Managers
 */

// global namespace is desired

// define aliases
use db\competition;
use db\user;

// add required database tools
require_once(dirname(__FILE__) . "/../representatives/db_representatives_competition.php");
require_once(dirname(__FILE__) . "/../representatives/db_representatives_user.php");

/**
 * Interface used for describing a competition manager
 */
interface managerCompetitionInterface
{
    // Errors
    /** @var int The id of the user (stored inside the manager setCurrentUserId()) doesn't match the user the competition is assigned to */
    const ERROR_WRONG_USER_ID = 1;
    /** @var int One of the fields of the passed competitions was out of the expected range */
    const ERROR_OUT_OF_RANGE = 2;
    /** @var int The competition one wanted to work with doesn't exist */
    const ERROR_NOT_EXISTING = 4;
    /** @var int The competition one wanted to add to the database already existed */
    const ERROR_ALREADY_EXISTING = 8;
    /** @var int Some information required for the desired task is missing (like a missing id in the competition element) */
    const ERROR_MISSING_INFORMATION = 16;
    /** @var int Error happened at adaptor level */
    const ERROR_adaptor = 32;

    /**
     * Searches for a competition with the id provided as argument
     * 
     * Note: no ERROR_NOT_EXISTING will be returned since null is a more elegant behavior.
     * 
     * @param int $id Id of the desired competition
     * 
     * @return ?competition If one found, the competition, else null
     */
    public function getCompetitionById(int $id): ?competition;

    /**
     * Searches for competitions for several days in the past with a limit
     * 
     * @param ?int $daysSinceToday How many days back from today competitions should be searched for
     * @param ?int $limit The number of competitions that should be returned (there might be a maximal amount implemented)
     * 
     * @return competitions[] Array of found competitions (might be empty)
     */
    public function getCompetitionsGeneric(?int $daysSinceToday, ?int $limit): array;

    /**
     * Checks wether a user has access to the competition or not
     * 
     * @param int $id Id of the competition
     * 
     * @return int 0 if access is provided, else Error codes
     */
    public function userHasAccess(int $id): int;

    /**
     * Sets the id of the user to currently use, might be null if no user id is required
     * 
     * Note: It is assumed that the id passed is valid! NO FURTHER CHECKS ARE DONE!
     * 
     * @param ?int $id The user id the manager should work with (must be a valid id)
     */
    public function setCurrentUserId(?int $id): void;

    /**
     * Adds a competition to the database
     * 
     * Note: the user id in the passed competition is irrelevant, the id in set with setCurrentUserId() is used!
     * 
     * @param competition $competition The competition to add to the database, with the currently set user.
     * 
     * @return int|competition Int in case of an error (use is_int()), or competition with updated id.
     */
    public function add(competition $competition): int|competition;

    /**
     * Edits a competition in the database
     * 
     * Note: the user id in the passed competition is irrelevant, the id in set with setCurrentUserId() is used!
     * 
     * @param competition $competition The competition to edit in the database, with the currently set user.
     * 
     * @return int Errors that happened during execution
     */
    public function edit(competition $competition): int;

    /**
     * Removes an competition from the database
     * 
     * Note: the user id in the passed competition is irrelevant, the id in set with setCurrentUserId() is used!
     * 
     * @param competition $competition The competition to remove from the database, with the currently set user.
     * 
     * @return int Errors that happened during execution
     */
    public function remove(competition $competition): int;
}
