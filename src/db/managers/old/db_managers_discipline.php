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
require_once("db_managers_discipline_interface.php");

/**
 * A class implementing a discipline management
 */
class managerDisciplineOld implements managerDisciplineInterface
{
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
     * @throws Exception if database is null
     */
    public function __construct(mysqli $db, int $competition_id)
    {
        if ($db == null)
            throw new Exception("database mustn't be null", 1);

        if ($competition_id == null)
            throw new Exception("competition_id mustn't be null", 1);

        $this->currentCompetitionID = $competition_id;
        $this->db = $db;
    }

    // explained in the interface
    public function setCompetitionId(int $ID): void
    {
        $this->currentCompetitionID = $ID;
    }

    // explained in the interface
    public function getDiscipline(DateTime $modified_since = null): array
    {
        // search for disciplines with corresponding parameters
        return adaptorDiscipline::search($this->db, null, $this->currentCompetitionID, $modified_since);
    }

    // explained in the interface
    public function getDisciplineById(int $id): ?discipline
    {
        // search for disciplines with the id's and return first result
        return adaptorDiscipline::search($this->db, $id)[0];
    }

    // explained in the interface
    public function getDisciplineByName(string $fallback_name): array
    {
        // search for disciplines with corresponding parameters
        return adaptorDiscipline::search($this->db, null, $this->currentCompetitionID, null, $fallback_name);
    }

    // explained in the interface
    public function add(discipline $discipline): int|discipline
    {
        // make discipline ready for the database
        $discipline->makeDbReady($this->db);

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

    // explained in the interface
    public function edit(discipline $discipline): int
    {
        // make discipline ready for the database
        $discipline->makeDbReady($this->db);

        // validate discipline
        $validation = $this->validate($this->db, $discipline);

        // check wether validation was successful or not
        if ($validation != 0)
            // return error that happened during validation
            return $validation;

        // edit discipline in database
        $result = adaptorDiscipline::edit($this->db, [$discipline])[0];

        // check if discipline was written successfully
        if ($result == null)
            return self::ERROR_adaptor;

        // return added discipline
        return $result;
    }

    // explained in the interface
    public function remove(discipline $discipline): int
    {
        // make discipline ready for the database
        $discipline->makeDbReady($this->db);

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
