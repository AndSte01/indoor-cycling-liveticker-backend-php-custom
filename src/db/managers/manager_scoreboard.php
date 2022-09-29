<?php

/**
 * A manager for scoreboards
 * 
 * @package Database\Managers
 */

// global namespace is desired

// define aliases
use db\adaptorResult;
use db\scoreboard;
use db\adaptorScoreboard;

// add required database tools
require_once(dirname(__FILE__) . "/../adaptor/adaptor_generic.php");
require_once(dirname(__FILE__) . "/../adaptor/adaptor_scoreboard.php");
require_once(dirname(__FILE__) . "/../representatives/representative_scoreboard.php");

/**
 * A class providing scoreboard management
 */
class managerScoreboard
{
    // Errors
    /** @var int The id of the competition doesn't match the id the scoreboard is assigned to */
    const ERROR_WRONG_COMPETITION_ID = 1;
    /** @var int One of the fields of the passed scoreboards was out of the expected range */
    const ERROR_OUT_OF_RANGE = 2;
    /** @var int The scoreboard one wanted to work with doesn't exist */
    const ERROR_NOT_EXISTING = 4;
    /** @var int The scoreboard one wanted to add to the database already existed */
    const ERROR_ALREADY_EXISTING = 8;
    /** @var int Some information required for the desired task is missing (like a missing id in the scoreboard element) */
    const ERROR_MISSING_INFORMATION = 16;
    /** @var int Error happened at adaptor level */
    const ERROR_ADAPTOR = 32;

    /** @var int current competition id */
    protected int $currentCompetitionID;

    /** @var mysqli Database to work with */
    protected mysqli $db;

    /**
     * Constructor
     * 
     * @param mysqli $db The database the scoreboards are stored in
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
     * Searches for all scoreboards assigned to the competition id (see setCompetitionId())
     * 
     * @param DateTime $modified_since The time after which modifications should be returned
     * 
     * @return scoreboard[] Array of found scoreboards (might be empty)
     */
    public function getScoreboards(DateTime $modified_since = null): array
    {
        // search for scoreboards with corresponding parameters
        return adaptorScoreboard::search($this->db, null, null, $this->currentCompetitionID, $modified_since);
    }

    /**
     * Searches for a scoreboard with the external_id provided as argument
     * 
     * Note: no ERROR_NOT_EXISTING will be returned since null is a more elegant behavior.
     * 
     * @param int $external_id Id of the desired scoreboard
     * 
     * @return ?scoreboard If one found, the scoreboard, else null
     */
    public function getScoreboardByExternalId(int $external_id): ?scoreboard
    {
        // search for scoreboards with the id's and return first result
        return adaptorScoreboard::search($this->db, null, $external_id, $this->currentCompetitionID)[0];
    }

    /**
     * requests a certain amount of scoreboards with predictable external id's for the set competition
     * 
     * Note this functions handles interactions with the database loosely (with less checks), so don't expose this functionality to the web!
     * 
     * @param int $amount The number of scoreboards that should be added to the database
     * 
     * @return scoreboard[] Scoreboards requested.
     */
    public function requestScoreboards(int $amount): array
    {
        // check how many scoreboards are required (and if some are already existing in the database)
        $existing_scoreboards = $this->getScoreboards();

        // calculate the surplus
        $scoreboard_surplus = count($existing_scoreboards) - $amount;

        // now check whats needs to bee done (adding or removing scoreboards, or use existing ones)
        switch (true) {
            case $scoreboard_surplus == 0: // there are already enough scoreboards in the database so 
                return $existing_scoreboards;

            case $scoreboard_surplus < 0: // there are less scoreboards than required
                // empty scoreboard array
                $scoreboards = [];

                // add additional scoreboards to the database
                for ($i = count($existing_scoreboards) + 1; $i <= $amount; $i++) {
                    $scoreboards[] = new scoreboard( // a scoreboard created like this is already ready for the db!
                        null,
                        $i, // the new external id
                        null,
                        $this->currentCompetitionID
                    );
                }

                // now add the additional scoreboards to the database
                return array_merge($existing_scoreboards, adaptorScoreboard::add($this->db, $scoreboards));

            case $scoreboard_surplus > 0:
                // please note the scoreboard with the lowest internal id also always has the lowest external id,
                // also the scoreboards in $existing_scoreboards are sorted by internal id low -> high

                // empty scoreboard array
                $scoreboards = [];

                // add the existing scoreboards to in inverse order to the array (till the surplus is 0)
                for ($i = count($existing_scoreboards); $i > $amount; $i--) {
                    $scoreboards[] = new scoreboard( // a scoreboard created like this is already ready for the db!
                        $existing_scoreboards[$i - 1]->{scoreboard::KEY_INTERNAL_ID}, //note arrays start counting at 0 not 1
                    );
                }

                // remove scoreboards from the database
                adaptorScoreboard::remove($this->db, $scoreboards);
                return [];
        }
    }

    /**
     * Edits a scoreboard in the database
     * 
     * Note: the competition id passed in the scoreboard is irrelevant, the one set by setCompetitionId() is used.
     * Note: you can't edit the competition id (to prevent vicious behavior)
     * Note: the external_id in combination with the competition_id is used to identify the scoreboard
     * 
     * @param scoreboard $scoreboard The scoreboard to edit in the database.
     * @param array $fields The fields of the scoreboard to update
     * 
     * @return int Errors that happened during execution
     */
    public function edit(scoreboard $scoreboard, array $fields): int
    {
        // validate scoreboard
        $validation = $this->validate($this->db, $this->currentCompetitionID, $scoreboard);

        // check wether validation was successful or not
        if ($validation != 0)
            // return error that happened during validation
            return $validation;

        // make the scoreboard ready for the database
        adaptorScoreboard::makeRepresentativeDbReady($this->db, $scoreboard);

        // Note: we don't need to check fields since adaptorScoreboard::edit() is robust against invalid fields

        // now we try to remove the competition id field to prevent editing of the same
        if (($key = array_search(scoreboard::KEY_COMPETITION_ID, $fields)) !== false) {
            unset($fields[$key]);
        }

        // edit scoreboard in database
        $result = adaptorScoreboard::edit($this->db, $scoreboard, $fields);

        // check if scoreboard was written successfully
        if ($result == false)
            return self::ERROR_ADAPTOR;

        // return 0 since action was successful (or the error wasn't reported)
        return 0;
    }

    /**
     * Removes all scoreboards assigned to a competition
     */
    public function removeAllScoreboards(): void
    {
        // just simply request no scoreboards
        $this->requestScoreboards(0);
    }

    /**
     * Checks wether a scoreboard can be edited (or removed)
     * 
     * id is checked as well as the existence of the scoreboard in the database
     * 
     * @param mysqli $db The database to validate against
     * @param scoreboard $scoreboardToValidate The scoreboard to validate
     * 
     * @return int Return 0 if validation was passed, else error as int (>1)
     */
    private function validate(mysqli $db, int $competition_id, scoreboard $scoreboardToValidate): int
    {
        // check if external_id is present
        if ($scoreboardToValidate->{scoreboard::KEY_EXTERNAL_ID} == 0)
            return self::ERROR_MISSING_INFORMATION;

        // search for scoreboards with desired external_id and competition
        $found_scoreboards = adaptorScoreboard::search($db, null, $scoreboardToValidate->{scoreboard::KEY_EXTERNAL_ID}, $this->currentCompetitionID);

        // check if any scoreboards were found
        if ($found_scoreboards == null)
            return self::ERROR_NOT_EXISTING;

        // in case the scoreboard should present a result check wether the result exists and is from the same competition
        if ($scoreboardToValidate->{scoreboard::KEY_CONTENT} > 0) {
            $found_results = adaptorResult::searchByCompetition($db, $competition_id, $scoreboardToValidate->{scoreboard::KEY_CONTENT});
            if ($found_results == null)
                return self::ERROR_NOT_EXISTING;
        }

        // return 0 if no errors ocurred
        return 0;
    }
}
