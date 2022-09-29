<?php

/**
 * A manager for results
 * 
 * @package Database\Managers
 */

// global namespace is desired

// define aliases
use db\result;
use db\adaptorResult;

// add required database tools
require_once(dirname(__FILE__) . "/../adaptor/adaptor_generic.php");
require_once(dirname(__FILE__) . "/../adaptor/adaptor_result.php");
require_once(dirname(__FILE__) . "/../representatives/representative_result.php");

/**
 * A class implementing a result management
 */
class managerResult
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
    const ERROR_ADAPTOR = 32;

    /** @var int current discipline id */
    protected int $currentDisciplineID;

    /** @var mysqli Database to work with */
    protected mysqli $db;

    /**
     * Constructor
     * 
     * @param mysqli $db The database the results are stored in
     * @param int $discipline_id The id of the discipline to use
     * 
     * @throws Exception if database is null
     */
    public function __construct(mysqli $db, int $discipline_id = null)
    {
        if ($db == null)
            throw new Exception("database mustn't be null", 1);

        $this->currentCompetitionID = $discipline_id;
        $this->db = $db;
    }

    /**
     * Sets the discipline id used
     *
     * @param int $ID The discipline id to use
     */
    public function setDisciplineId(int $ID): void
    {
        $this->currentDisciplineID = $ID;
    }

    /**
     * Searches for all results assigned to the discipline id (see setDisciplineId())
     *
     * @param DateTime $modified_since The time after which modifications should be returned
     * 
     * @return result[] Array of found disciplines (might be empty)
     */
    public function getResult(DateTime $modified_since = null): array
    {
        // search for result with corresponding parameters
        return adaptorResult::search($this->db, null, $this->currentDisciplineID, $modified_since);
    }

    /**
     * Searches for a result with the id provided as argument
     *
     * Note: no ERROR_NOT_EXISTING will be returned since null is a more elegant behavior.
     *
     * @param int $id Id of the desired result
     *
     * @return ?result If one found, the result, else null
     */
    public function getResultById(int $id): ?result
    {
        // search for result with corresponding parameters
        return adaptorResult::search($this->db, $id)[0];
    }

    /**
     * Returns the results of a certain competition modified after a certain time.
     *
     * @param int $competition_id Id of the competition the results are indirectly assigned to
     * @param DateTime $modified_since The time after which modifications should be returned
     *
     * @return result[] Array of found results (might be empty)
     */
    public function getResultByCompetition(int $competition_id, DateTime $modified_since = null): array
    {
        // search for result with corresponding parameters
        return adaptorResult::searchByCompetition($this->db, $competition_id, null, $modified_since);
    }

    /**
     * Adds a result to the database
     *
     * @param result $result The result to add to the database.
     *
     * @return int|result Int in case of an error (use is_int()), or result with updated id.
     */
    public function add(result $result): int|result
    {
        // make result ready for the database
        adaptorResult::makeRepresentativeDbReady($this->db, $result);

        // search for similar results in the database
        $found_results = adaptorResult::search(
            $this->db,
            null,
            $this->currentDisciplineID,
            null,
            $result->{result::KEY_START_NUMBER},
            $result->{result::KEY_NAME}
        );

        if ($found_results != null)
            return $found_results[0];

        // update discipline id in result
        $result->updateParentId($this->currentDisciplineID);

        // add result to database
        $result = adaptorResult::add($this->db, [$result])[0];

        // check if result was written successfully
        if ($result == null)
            return self::ERROR_ADAPTOR;

        // return added result
        return $result;
    }

    /**
     * Edits a result in the database
     *
     * Note: the discipline id passed in the result is irrelevant, the one set by setDisciplineId() is used.
     *
     * @param result $result The result to edit in the database.
     * @param array $fields The fields of the result to update
     *
     * @return int Errors that happened during execution
     */
    public function edit(result $result, array $fields): int
    {
        // validate result
        $validation = $this->validate($this->db, $result);

        // check wether validation was successful or not
        if ($validation != 0)
            // return error that happened during validation
            return $validation;

        // make the result ready for the database
        adaptorResult::makeRepresentativeDbReady($this->db, $result);

        // Note: we don't need to check fields since adaptorResult::edit() is robust against invalid fields

        // now we try to remove the discipline id field to prevent editing of the same
        if (($key = array_search(result::KEY_DISCIPLINE_ID, $fields)) !== false) {
            unset($fields[$key]);
        }

        // edit discipline in database
        $result = adaptorResult::edit($this->db, $result, $fields);

        // check if result was written successfully
        if ($result == false)
            return self::ERROR_ADAPTOR;

        // return added result
        return 0;
    }

    /**
     * Removes an result from the database
     *
     * @param result $result The result to remove from the database (only it's id is relay relevant).
     *
     * @return int Errors that happened during execution
     */
    public function remove(result $result): int
    {
        // validate result
        $validation = $this->validate($this->db, $result);

        // check wether validation was successful or not
        if ($validation != 0)
            // return error that happened during validation
            return $validation;

        // remove result from database
        adaptorResult::remove($this->db, [$result]);

        return 0;
    }

    /**
     * Checks wether a result can be edited (or removed)
     * 
     * id is checked as well as the existence of the result in the database
     * 
     * @param mysqli $db The database to validate against
     * @param discipline $resultToValidate The result to validate
     * 
     * @return int Return 0 if validation was passed, else error as int (>1)
     */
    private function validate(mysqli $db, result $resultToValidate): int
    {
        // check if id is present
        if ($resultToValidate->{result::KEY_ID} == 0)
            return self::ERROR_MISSING_INFORMATION;

        // search for results with desired id
        $found_results = adaptorResult::search($db, $resultToValidate->{result::KEY_ID});

        // check if any results were found
        if ($found_results == null)
            return self::ERROR_NOT_EXISTING;

        // check if discipline ids match
        if ($found_results[0]->{result::KEY_DISCIPLINE_ID} != $this->currentDisciplineID)
            return self::ERROR_WRONG_DISCIPLINE_ID;

        // return 0 if no errors ocurred
        return 0;
    }
}
