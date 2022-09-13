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
require_once(dirname(__FILE__) . "/../representatives/db_representatives_result.php");
require_once("db_managers_result_interface.php");

/**
 * A class implementing a result management
 */
class managerResultsOld implements managerResultInterface
{
    /** @var int current competition id */
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

    // explained in the interface
    public function setDisciplineId(int $ID): void
    {
        $this->currentDisciplineID = $ID;
    }

    // explained in the interface
    public function getResult(DateTime $modified_since = null): array
    {
        // search for result with corresponding parameters
        return adaptorResult::search($this->db, null, [$this->currentDisciplineID], $modified_since);
    }

    // explained in the interface
    public function getResultById(int $id): ?result
    {
        // search for result with corresponding parameters
        return adaptorResult::search($this->db, $id)[0];
    }

    // explained in the interface
    public function getResultByCompetition(int $competition_id, DateTime $modified_since = null): array
    {
        // search for result with corresponding parameters
        return adaptorResult::searchByCompetition($this->db, $competition_id, $modified_since);
    }

    // explained in the interface
    public function add(result $result): int|result
    {
        // make result ready for the database
        $result->makeDbReady($this->db);

        // search for similar results in the database
        if (adaptorResult::search(
            $this->db,
            null,
            [$this->currentDisciplineID],
            null,
            $result->{result::KEY_START_NUMBER},
            $result->{result::KEY_NAME}
        ) != null)
            return self::ERROR_ALREADY_EXISTING;

        // update discipline id in result
        $result->updateParentId($this->currentDisciplineID);

        // add result to database
        $result = adaptorResult::add($this->db, [$result])[0];

        // check if result was written successfully
        if ($result == null)
            return self::ERROR_adaptor;

        // return added result
        return $result;
    }

    // explained in the interface
    public function edit(result $result): int
    {
        // make result ready for the database
        $result->makeDbReady($this->db);

        // validate result
        $validation = $this->validate($this->db, $result);

        // check wether validation was successful or not
        if ($validation != 0)
            // return error that happened during validation
            return $validation;

        // edit discipline in database
        $result = adaptorResult::edit($this->db, [$result])[0];

        // check if result was written successfully
        if ($result == null)
            return self::ERROR_adaptor;

        // return added result
        return $result;
    }

    // explained in the interface
    public function remove(result $result): int
    {
        // make result ready for the database
        $result->makeDbReady($this->db);

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
