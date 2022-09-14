<?php

/**
 * A manager for competitions
 * 
 * @package Database\Managers
 */

// global namespace is desired

// define aliases
use db\competition;
use db\adaptorCompetition;

// add required database tools
require_once(dirname(__FILE__) . "/../adaptor/adaptor_generic.php");
require_once(dirname(__FILE__) . "/../adaptor/adaptor_competition.php");
require_once(dirname(__FILE__) . "/../representatives/db_representatives_competition.php");
require_once("db_managers_competition_interface.php");

/**
 * A class implementing an competition management
 */
class managerCompetitionOld implements managerCompetitionInterface
{
    /** @var ?int current users id */
    protected ?int $currentUserID = null;

    /** @var mysqli Database to work with */
    protected mysqli $db;

    /**
     * Constructor
     * 
     * @param mysqli $db The database the competitions are stored in
     * 
     * @throws Exception if database is null
     */
    public function __construct(mysqli $db)
    {
        if ($db == null)
            throw new Exception("database mustn't be null", 1);

        $this->db = $db;
    }

    // explained in the interface
    public function getCompetitionById(int $id): ?competition
    {
        // search for competition
        $competitions = adaptorCompetition::search($this->db, true, $id);

        // return first competition (might be null)
        return $competitions[0];
    }

    // explained in the interface
    public function getCompetitionsGeneric(?int $daysSinceToday, ?int $limit): array
    {
        // var for competitions
        $competitions = [];

        // check wether to search for a special amount of days back or not
        switch (true) {
            case ($daysSinceToday === null):
                $competitions = adaptorCompetition::search($this->db, true, null, null, null, null, $limit, null);
                break;

            case ($daysSinceToday == 0):
                $competitions = adaptorCompetition::search($this->db, true, null, new DateTime(), null, null, $limit, null);
                break;

            default:
                $date_end = (new DateTime())->modify("-$daysSinceToday day");
                $competitions = adaptorCompetition::search($this->db, true, null, new DateTime(), null, null, $limit, $date_end);
                break;
        }

        // return competitions and errors
        return $competitions;
    }

    /**
     * Checks wether a user has access to the competition or not
     * 
     * Note: the current user id stored internally is not changed!
     */
    public function userHasAccess(int $id): int
    {
        $competition = $this->getCompetitionById($id);

        if ($competition == null)
            return self::ERROR_NOT_EXISTING;

        if ($competition->{competition::KEY_USER} != $this->currentUserID)
            return self::ERROR_WRONG_USER_ID;

        return 0;
    }

    // explained in the interface
    public function setCurrentUserId(?int $id): void
    {
        $this->currentUserID = $id;
    }

    // explained in the interface
    /**
     * @throws Exception If current user id (see setCurrentUserId()) is null
     */
    public function add(competition $competition): int|competition
    {
        // first check for exception
        if ($this->currentUserID == null)
            throw new Exception("current user id mustn't be null (set with setCurrentUserId() beforehand)");

        // make the competition ready for the database (so early because some of strings)
        $competition->makeDbReady($this->db);

        // search for similar competition in database
        $found_competitions = adaptorCompetition::search(
            $this->db,
            true,
            null,
            $competition->{competition::KEY_DATE},
            $competition->{competition::KEY_NAME},
            $competition->{competition::KEY_LOCATION}
        );

        // check if any competitions were found and update the first (and therefor newest) result
        if ($found_competitions != null)
            self::ERROR_ALREADY_EXISTING;

        // update the user id in the competition
        $competition->updateParentId($this->currentUserID);

        // add competition to database and return it (and make the array a single element on the go)
        $result = (adaptorCompetition::add($this->db, [$competition]))[0];

        // check if competition was written successfully
        if ($result == null)
            return self::ERROR_ADAPTOR;

        // added competition
        return $result;
    }

    // explained in the interface
    public function edit(competition $competition): int
    {
        // validate competition
        $validation = $this->validate($this->db, $competition);

        // check wether validation was successful or not
        if ($validation != 0)
            // return error that happened during validation
            return $validation;

        // everything seems fine write the competition to the database
        adaptorCompetition::edit($this->db, [$competition]);

        // return 0 since action was successful
        return 0;
    }

    // explained in the interface
    public function remove(competition $competition): int
    {
        // do checks for removing the competition
        $validation = $this->validate($this->db, $competition);

        // check wether validation was successful or not
        if ($validation != 0)
            // return error that happened during validation
            return $validation;

        // everything seems fine write the competition to the database
        adaptorCompetition::remove($this->db, [$competition]);

        // return empty error array
        return 0;
    }

    /**
     * Checks wether a competition can be edited (or removed)
     * 
     * id is checked as well as the existence of the competition in the database
     * 
     * @param mysqli $db The database to validate against
     * @param competition $competitionToValidate The competition to validate
     * 
     * @return int Return 0 if validation was passed, else ERROR_MISSING_INFORMATION, ERROR_NOT_EXISTING or ERROR_WRONG_USER_ID
     */
    protected function validate(mysqli $db, competition $competitionToValidate): int
    {
        // check if id is present
        if ($competitionToValidate->{competition::KEY_ID} == 0)
            return self::ERROR_MISSING_INFORMATION;

        // search for competitions with desired id
        $found_competitions = adaptorCompetition::search($db, true, $competitionToValidate->{competition::KEY_ID});

        // check if any competitions were found
        if ($found_competitions == null)
            return self::ERROR_NOT_EXISTING;

        // check if credentials match (since id is unique $fund_competitions only contains one entry at this point)
        if ($found_competitions[0]->{competition::KEY_USER} != $this->currentUserID)
            return self::ERROR_WRONG_USER_ID;

        // return 0 if no errors ocurred
        return 0;
    }
}
