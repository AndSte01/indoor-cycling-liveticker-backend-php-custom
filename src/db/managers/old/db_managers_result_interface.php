<?php

/**
 * Interface used to define a result manager
 *
 * @package Database\Managers
 */

// global namespace is desired

// define aliases
use db\result;

// add required database tools
require_once(dirname(__FILE__) . "/../representatives/db_representatives_result.php");

/**
 * Interface used for describing a result manager
 */
interface managerResultInterface
{
    // Errors
    /** @var int The id of the discipline doesn't match the id the result is assigned to */
    const ERROR_WRONG_DISCIPLINE_ID = 1;
    /** @var int One of the fields of the passed result was out of the expected range */
    const ERROR_OUT_OF_RANGE = 2;
    /** @var int The result one wanted to work with doesn't exist */
    const ERROR_NOT_EXISTING = 4;
    /** @var int The result one wanted to add to the database already existed */
    const ERROR_ALREADY_EXISTING = 8;
    /** @var int Some information required for the desired task is missing (like a missing id in the result element) */
    const ERROR_MISSING_INFORMATION = 16;
    /** @var int Error happened at adaptor level */
    const ERROR_adaptor = 32;

    /**
     * Sets the discipline id used
     *
     * @param int $ID The discipline id to use
     */
    public function setDisciplineId(int $ID): void;

    /**
     * Searches for all results assigned to the discipline id (see setDisciplineId())
     *
     * @param DateTime $modified_since The time after which modifications should be returned
     * 
     * @return result[] Array of found disciplines (might be empty)
     */
    public function getResult(DateTime $modified_since = null): array;

    /**
     * Searches for a result with the id provided as argument
     *
     * Note: no ERROR_NOT_EXISTING will be returned since null is a more elegant behavior.
     *
     * @param int $id Id of the desired result
     *
     * @return ?result If one found, the result, else null
     */
    public function getResultById(int $id): ?result;

    /**
     * Returns the results of a certain competition modified after a certain time.
     *
     * @param int $competition_id Id of the competition the results are indirectly assigned to
     * @param DateTime $modified_since The time after which modifications should be returned
     *
     * @return result[] Array of found results (might be empty)
     */
    public function getResultByCompetition(int $competition_id, DateTime $modified_since = null): array;

    /**
     * Adds a result to the database
     *
     * @param result $result The result to add to the database.
     *
     * @return int|result Int in case of an error (use is_int()), or result with updated id.
     */
    public function add(result $result): int|result;

    /**
     * Edits a result in the database
     *
     * Note: the discipline id passed in the result is irrelevant, the one set by setDisciplineId() is used.
     *
     * @param result $result The result to edit in the database.
     *
     * @return int Errors that happened during execution
     */
    public function edit(result $result): int;

    /**
     * Removes an result from the database
     *
     * @param result $result The result to remove from the database (only it's id is relay relevant).
     *
     * @return int Errors that happened during execution
     */
    public function remove(result $result): int;
}
