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
require_once(dirname(__FILE__) . "/../representatives/representative_competition.php");

/**
 * Helps managing competitions
 */
class managerCompetition
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
    const ERROR_ADAPTOR = 32;

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

    /**
     * Searches for a competition with the id provided as argument
     * 
     * Note: no ERROR_NOT_EXISTING will be returned since null is a more elegant behavior.
     * 
     * @param int $id Id of the desired competition
     * 
     * @return ?competition If one found, the competition, else null
     */
    public function getCompetitionById(int $id): ?competition
    {
        // search for competition
        $competitions = adaptorCompetition::search($this->db, true, $id);

        // return first competition (might be null)
        return $competitions[0];
    }

    /**
     * Searches for competitions for several days in the past with a limit
     * 
     * @param ?int $daysSinceToday How many days back from today competitions should be searched for
     * @param ?int $limit The number of competitions that should be returned (there might be a maximal amount implemented)
     * 
     * @return competitions[] Array of found competitions (might be empty)
     */
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
     * 
     * @param int $id Id of the competition
     * 
     * @return int 0 if access is provided, else Error codes
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

    /**
     * Sets the id of the user to currently use, might be null if no user id is required
     * 
     * Note: It is assumed that the id passed is valid! NO FURTHER CHECKS ARE DONE!
     * 
     * @param ?int $id The user id the manager should work with (must be a valid id)
     */
    public function setCurrentUserId(?int $id): void
    {
        $this->currentUserID = $id;
    }

    /**
     * Adds a competition to the database
     * 
     * Note: the user id in the passed competition is irrelevant, the id in set with setCurrentUserId() is used!
     * 
     * @param competition $competition The competition to add to the database, with the currently set user.
     * 
     * @throws Exception If current user id (see setCurrentUserId()) is null
     * 
     * @return int|competition Int in case of an error (use is_int()), or competition with updated id.
     */
    public function add(competition $competition): int|competition
    {
        // first check for exception
        if ($this->currentUserID == null)
            throw new Exception("current user id mustn't be null (set with setCurrentUserId() beforehand)");

        // make fields in the competition ready for the database
        adaptorCompetition::makeRepresentativeDbReady($this->db, $competition);

        // search for similar competition in database to prevent duplicates
        $found_competitions = adaptorCompetition::search(
            $this->db,
            true,
            null,
            $competition->{competition::KEY_DATE},
            $competition->{competition::KEY_NAME},
            $competition->{competition::KEY_LOCATION}
        );

        // check if any competitions were found if so return to prevent duplicates
        if ($found_competitions != null)
            return self::ERROR_ALREADY_EXISTING;

        // update fields in competition object

        // update the user id in the competition
        $competition->updateParentId($this->currentUserID);

        // add competition to database and return it (and make the array a single element on the go)
        $result = (adaptorCompetition::add($this->db, [$competition]))[0];

        // check if competition was written successfully
        if ($result == null)
            return self::ERROR_ADAPTOR;

        // return added competition
        return $result;
    }

    /**
     * Edits a competition in the database
     * 
     * Note: the user id in the passed competition is irrelevant, the id in set with setCurrentUserId() is used!
     * 
     * @param competition $competition The competition to edit in the database, with the currently set user.
     * @param array $fields The fields of the competition to update
     * 
     * @return int Errors that happened during execution
     */
    public function edit(competition $competition, array $fields): int
    {
        // validate competition
        $validation = $this->validate($this->db, $competition);

        // check wether validation was successful or not
        if ($validation != 0)
            // return error that happened during validation
            return $validation;

        // make the competition ready for the database
        adaptorCompetition::makeRepresentativeDbReady($this->db, $competition);

        // Note: we don't need to check fields since adaptorCompetition::edit() is robust against invalid fields

        // everything seems fine write the competition to the database
        $result = adaptorCompetition::edit($this->db, $competition, $fields);

        // check if competition was written successfully
        if ($result == false)
            return self::ERROR_ADAPTOR;

        // return 0 since action was successful (or the error wasn't reported)
        return 0;
    }

    /**
     * Removes an competition from the database
     * 
     * Note: the user id in the passed competition is irrelevant, the id in set with setCurrentUserId() is used!
     * 
     * @param competition $competition The competition to remove from the database, with the currently set user.
     * 
     * @return int Errors that happened during execution
     */
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
     * id, user id (check if current user has access) as well as the existence of the competition in the database are checked
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
