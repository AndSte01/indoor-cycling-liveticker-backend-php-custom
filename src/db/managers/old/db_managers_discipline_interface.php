<?php

/**
 * Interface used to define a discipline manager
 * 
 * @package Database\Managers
 */

// global namespace is desired

// define aliases
use db\discipline;

// add required database tools
require_once(dirname(__FILE__) . "/../representatives/db_representatives_discipline.php");

/**
 * Interface used for describing a discipline manager
 */
interface managerDisciplineInterface
{
    // Errors
    /** @var int The id of the competition doesn't match the id the disciplines is assigned to */
    const ERROR_WRONG_COMPETITION_ID = 1;
    /** @var int One of the fields of the passed disciplines was out of the expected range */
    const ERROR_OUT_OF_RANGE = 2;
    /** @var int The discipline one wanted to work with doesn't exist */
    const ERROR_NOT_EXISTING = 4;
    /** @var int The discipline one wanted to add to the database already existed */
    const ERROR_ALREADY_EXISTING = 8;
    /** @var int Some information required for the desired task is missing (like a missing id in the discipline element) */
    const ERROR_MISSING_INFORMATION = 16;
    /** @var int Error happened at adaptor level */
    const ERROR_adaptor = 32;

    /**
     * Sets the competition id used
     * 
     * @param int $ID The competition id to use
     */
    public function setCompetitionId(int $ID): void;

    /**
     * Searches for all disciplines assigned to the competition id (see setCompetitionId())
     * 
     * @param DateTime $modified_since The time after which modifications should be returned
     * 
     * @return discipline[] Array of found disciplines (might be empty)
     */
    public function getDiscipline(DateTime $modified_since = null): array;

    /**
     * Searches for a discipline with the id provided as argument
     * 
     * Note: no ERROR_NOT_EXISTING will be returned since null is a more elegant behavior.
     * 
     * @param int $id Id of the desired discipline
     * 
     * @return ?discipline If one found, the discipline, else null
     */
    public function getDisciplineById(int $id): ?discipline;

    /**
     * Searches for disciplines by it's fallback_name and competition id (see setCompetitionId())
     * 
     * @param string $fallback_name The (fallback) name of the discipline
     * 
     * @return discipline[] Array of found disciplines (might be empty)
     */
    public function getDisciplineByName(string $fallback_name): array;

    /**
     * Adds a discipline to the database
     * 
     * @param discipline $discipline The discipline to add to the database.
     * 
     * @return int|discipline Int in case of an error (use is_int()), or discipline with updated id.
     */
    public function add(discipline $discipline): int|discipline;

    /**
     * Edits a discipline in the database
     * 
     * Note: the competition id passed in the discipline is irrelevant, the one set by setCompetitionId() is used.
     * 
     * @param discipline $discipline The discipline to edit in the database.
     * 
     * @return int Errors that happened during execution
     */
    public function edit(discipline $discipline): int;

    /**
     * Removes an discipline from the database
     * 
     * @param discipline $discipline The discipline to remove from the database (only it's id is relay relevant).
     * 
     * @return int Errors that happened during execution
     */
    public function remove(discipline $discipline): int;
}
