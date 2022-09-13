<?php

/**
 * A manager for disciplines
 * 
 * @package Database\Managers
 */

// global namespace is desired

// define aliases
use db\discipline;
use db\adaptorDiscipline;

// add required database tools
require_once(dirname(__FILE__) . "/../adaptor/adaptor_generic.php");
require_once(dirname(__FILE__) . "/../adaptor/adaptor_discipline.php");
require_once(dirname(__FILE__) . "/../representatives/db_representatives_discipline.php");

/**
 * A class implementing a discipline management
 */
class managerDiscipline
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

    /** @var int current competition id */
    protected int $currentCompetitionID;

    /** @var mysqli Database to work with */
    protected mysqli $db;

    /**
     * Constructor
     * 
     * @param mysqli $db The database the disciplines are stored in
     * @param int $competition_id The id of the competition to use
     * 
     * @throws Exception if database is null or competition id is null
     */
    public function __construct(mysqli $db, int $competition_id)
    {
        if ($db == null)
            throw new Exception("database mustn't be null", 1);

        if ($competition_id === null) // type safe comparison is required
            throw new Exception("competition_id mustn't be null", 1);

        $this->currentCompetitionID = $competition_id;
        $this->db = $db;
    }

    /**
     * Sets the competition id used
     * 
     * @param int $ID The competition id to use
     */
    public function setCompetitionId(int $ID): void
    {
        $this->currentCompetitionID = $ID;
    }

    /**
     * Searches for all disciplines assigned to the competition id (see setCompetitionId())
     * 
     * @param DateTime $modified_since The time after which modifications should be returned
     * 
     * @return discipline[] Array of found disciplines (might be empty)
     */
    public function getDiscipline(DateTime $modified_since = null): array
    {
        // search for disciplines with corresponding parameters
        return adaptorDiscipline::search($this->db, null, $this->currentCompetitionID, $modified_since);
    }

    /**
     * Searches for a discipline with the id provided as argument
     * 
     * Note: no ERROR_NOT_EXISTING will be returned since null is a more elegant behavior.
     * 
     * @param int $id Id of the desired discipline
     * 
     * @return ?discipline If one found, the discipline, else null
     */
    public function getDisciplineById(int $id): ?discipline
    {
        // search for disciplines with the id's and return first result
        return adaptorDiscipline::search($this->db, $id)[0];
    }

    /**
     * Searches for disciplines by it's fallback_name and competition id (see setCompetitionId())
     * 
     * @param string $fallback_name The (fallback) name of the discipline
     * 
     * @return discipline[] Array of found disciplines (might be empty)
     */
    public function getDisciplineByName(string $fallback_name): array
    {
        // search for disciplines with corresponding parameters
        return adaptorDiscipline::search($this->db, null, $this->currentCompetitionID, null, $fallback_name);
    }

    /**
     * Adds a discipline to the database
     * 
     * @param discipline $discipline The discipline to add to the database.
     * 
     * @return int|discipline Int in case of an error (use is_int()), or discipline with updated id.
     */
    public function add(discipline $discipline): int|discipline
    {
        // make discipline ready for the database
        adaptorDiscipline::makeRepresentativeDbReady($this->db, $discipline);

        // search for similar disciplines in database
        if (adaptorDiscipline::search(
            $this->db,
            null,
            $this->currentCompetitionID,
            null,
            $discipline->{discipline::KEY_FALLBACK_NAME},
            $discipline->{discipline::KEY_TYPE},
            $discipline->{discipline::KEY_ROUND}
        ) != null)
            return self::ERROR_ALREADY_EXISTING;

        // update competition id in discipline
        $discipline->updateParentId($this->currentCompetitionID);

        // add discipline to database
        $result = adaptorDiscipline::add($this->db, [$discipline])[0];

        // check if discipline was written successfully
        if ($result == null)
            return self::ERROR_adaptor;

        // return added discipline
        return $result;
    }

    /**
     * Edits a discipline in the database
     * 
     * Note: the competition id passed in the discipline is irrelevant, the one set by setCompetitionId() is used.
     * Note: you can't edit the competition id (to prevent vicious behavior)
     * 
     * @param discipline $discipline The discipline to edit in the database.
     * @param array $fields The fields of the discipline to update
     * 
     * @return int Errors that happened during execution
     */
    public function edit(discipline $discipline, array $fields): int
    {
        // validate discipline
        $validation = $this->validate($this->db, $discipline);

        // check wether validation was successful or not
        if ($validation != 0)
            // return error that happened during validation
            return $validation;

        // make the competition ready for the database
        adaptorDiscipline::makeRepresentativeDbReady($this->db, $discipline);

        // Note: we don't need to check fields since adaptorDiscipline::edit() is robust against invalid fields

        // now we try to remove the competition id field to prevent editing of the same
        if (($key = array_search(discipline::KEY_COMPETITION_ID, $fields)) !== false) {
            unset($fields[$key]);
        }

        // edit discipline in database
        $result = adaptorDiscipline::edit($this->db, $discipline, $fields);

        // check if discipline was written successfully
        if ($result == false)
            return self::ERROR_adaptor;

        // return 0 since action was successful (or the error wasn't reported)
        return 0;
    }

    /**
     * Removes an discipline from the database
     * 
     * @param discipline $discipline The discipline to remove from the database (only it's id is relay relevant).
     * 
     * @return int Errors that happened during execution
     */
    public function remove(discipline $discipline): int
    {
        // validate discipline
        $validation = $this->validate($this->db, $discipline);

        // check wether validation was successful or not
        if ($validation != 0)
            // return error that happened during validation
            return $validation;

        // remove discipline from database
        adaptorDiscipline::remove($this->db, [$discipline]);

        return 0;
    }

    /**
     * Checks wether a discipline can be edited (or removed)
     * 
     * id is checked as well as the existence of the discipline in the database
     * 
     * @param mysqli $db The database to validate against
     * @param discipline $disciplineToValidate The discipline to validate
     * 
     * @return int Return 0 if validation was passed, else error as int (>1)
     */
    private function validate(mysqli $db, discipline $disciplineToValidate): int
    {
        // check if id is present
        if ($disciplineToValidate->{discipline::KEY_ID} == 0)
            return self::ERROR_MISSING_INFORMATION;

        // search for disciplines with desired id
        $found_disciplines = adaptorDiscipline::search($db, $disciplineToValidate->{discipline::KEY_ID});

        // check if any disciplines were found
        if ($found_disciplines == null)
            return self::ERROR_NOT_EXISTING;

        // check if competition ids match
        if ($found_disciplines[0]->{discipline::KEY_COMPETITION_ID} != $this->currentCompetitionID)
            return self::ERROR_WRONG_COMPETITION_ID;

        // return 0 if no errors ocurred
        return 0;
    }
}
