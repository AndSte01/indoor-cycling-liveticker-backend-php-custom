<?php

/**
 * @package Database\Representatives
 */

// assign namespace
namespace db;

// import trait and interface
require_once("representative_interface_trait.php");

// define aliases
use DateTime;
use JsonSerializable;

/** 
 * The class used to describe a result.
 */
class result implements JsonSerializable, RepresentativeChildInterface
{
    // use trait for basic functionality
    use RepresentativeTrait;

    // Keys used in data array
    /** @var string id of the result (unique identifier in database)*/
    public const KEY_ID = "id";
    /** @var string timestamp of the result (timestamp the result was last modified in the database) */
    public const KEY_TIMESTAMP = "timestamp";
    /** @var string id of the discipline the result is assigned to */
    public const KEY_DISCIPLINE_ID = "discipline";
    /** @var string start number of the result */
    public const KEY_START_NUMBER = "start_number";
    /** @var string name of the competitor */
    public const KEY_NAME = "name";
    /** @var string club of the competitor */
    public const KEY_CLUB = "club";
    /** @var string submitted score of the competitor */
    public const KEY_SCORE_SUBMITTED = "score_submitted";
    /** @var string accomplished score of the competitor */
    public const KEY_SCORE_ACCOMPLISHED = "score_accomplished";
    /** @var string current time of the competitor */
    public const KEY_TIME = "time";
    /** @var string wether the competitor is done or not */
    public const KEY_FINISHED = "finished";

    /** @var array data stored in the result */
    protected $data = [
        self::KEY_ID => 0,
        self::KEY_TIMESTAMP => 0,
        self::KEY_DISCIPLINE_ID => 0,
        self::KEY_START_NUMBER => 0,
        self::KEY_NAME => "",
        self::KEY_CLUB => "",
        self::KEY_SCORE_SUBMITTED => 0.0,
        self::KEY_SCORE_ACCOMPLISHED => 0.0,
        self::KEY_TIME => 0,
        self::KEY_FINISHED => false
    ];

    // Errors
    /** @var int Error while parsing the id */
    const ERROR_ID = 1;
    /** @var int Error while parsing date */
    const ERROR_TIMESTAMP = 2;
    /** @var int Error while parsing discipline_id */
    const ERROR_DISCIPLINE_ID = 4;
    /** @var int Error while parsing start_number (int > 0) */
    const ERROR_START_NUMBER = 8;
    /** @var int Error while parsing name (or if it contained invalid characters and they were removed) */
    const ERROR_NAME = 16;
    /** @var int Error while parsing club (or if it contained invalid characters and they were removed) */
    const ERROR_CLUB = 32;
    /** @var int Error while parsing score_submitted */
    const ERROR_SCORE_SUBMITTED = 64;
    /** @var int Error while parsing score_accomplished */
    const ERROR_SCORE_ACCOMPLISHED = 128;
    /** @var int Error while parsing time */
    const ERROR_TIME = 256;
    /** @var int Error while setting result as finished (or not) */
    const ERROR_FINISHED = 512;

    /**
     * Constructor 
     * 
     * @param int $ID Id of the result
     * @param DateTime $timestamp The last time the result was modified in the database
     * @param int $discipline_id ID of the discipline the result is assigned to
     * @param int $start_number The start number of the competitor
     * @param string $name The name of the competitor
     * @param string $club The club of the competitor
     * @param float $score_submitted The submitted score of the competitor
     * @param float $score_accomplished The accomplished score of the competitor
     * @param int $time Current time of the competitor
     * @param bool $finished Wether the competitor is done or not
     */
    function __construct(
        int $ID = null,
        DateTime $timestamp = null,
        int $discipline_id = null,
        int $start_number = null,
        string $name = null,
        string $club = null,
        float $score_submitted = null,
        float $score_accomplished = null,
        int $time = null,
        bool $finished = null
    ) {
        // this strange way of setting the defaults is used so one can just null all unused fields during construction
        // not relay performant but makes debugging a bit easier
        $this->data[self::KEY_ID]                 = $ID                 ?? 0;
        $this->data[self::KEY_TIMESTAMP]          = $timestamp          ?? new DateTime();
        $this->data[self::KEY_DISCIPLINE_ID]      = $discipline_id      ?? 0;
        $this->data[self::KEY_START_NUMBER]       = $start_number       ?? 0;
        $this->data[self::KEY_NAME]               = $name               ?? "";
        $this->data[self::KEY_CLUB]               = $club               ?? "";
        $this->data[self::KEY_SCORE_SUBMITTED]    = $score_submitted    ?? 0.0;
        $this->data[self::KEY_SCORE_ACCOMPLISHED] = $score_accomplished ?? 0.0;
        $this->data[self::KEY_TIME]               = $time               ?? 0;
        $this->data[self::KEY_FINISHED]           = $finished           ?? false;
    }

    /** @var bool Stores if timestamp was parsed successfully (might be required for cases in which an accurate timestamp of modifications in the database is mandatory) */
    protected bool $successfullyParsedTimestamp = false;

    /**
     * Returns wether the timestamp was parsed successfully or not
     * 
     * @return bool wether the timestamp was parsed successfully or not
     */
    public function successfullyParsedTimestamp(): bool
    {
        return $this->successfullyParsedTimestamp;
    }

    // explained in RepresentativeInterface
    public function updateId(int $ID): self
    {
        $this->data[self::KEY_ID] = $ID;
        return $this;
    }

    /**
     * Updates the discipline id
     */
    public function updateParentId(int $ID): void
    {
        $this->data[self::ERROR_DISCIPLINE_ID] = $ID;
    }

    /**
     * Parse strings into the result
     * 
     * @param ?string $ID Id of the result
     * @param ?string $timestamp the timestamp of the last modification of the result in the database
     * @param ?string $discipline_id ID of the discipline the result is assigned to
     * @param ?string $start_number The start number of the competitor
     * @param ?string $name The name of the competitor
     * @param ?string $club The club of the competitor
     * @param ?string $score_submitted The submitted score of the competitor (NOTE: use '.' as decimal separator)
     * @param ?string $score_accomplished The accomplished score of the competitor (NOTE: use '.' as decimal separator)
     * @param ?string $time Current time of the competitor
     * @param ?string $finished Wether the competitor is done or not
     * 
     * @return int the errors occurred during parsing
     */
    public function parse(
        ?string $ID = "",
        ?string $timestamp = "",
        ?string $discipline_id = "",
        ?string $start_number = "",
        ?string $name = "",
        ?string $club = "",
        ?string $score_submitted = "",
        ?string $score_accomplished = "",
        ?string $time = "",
        ?string $finished = ""
    ): int {
        // variable for error
        $error = 0;

        // try to generate DateTime from string, if it fails, log error and set date to current date
        try {
            $this->data[self::KEY_TIMESTAMP] = new DateTime($timestamp);
            $this->successfullyParsedTimestamp = true; // mark timestamp as successfully parsed
        } catch (\Exception $e) {
            error_log($e);
            $this->data[self::KEY_TIMESTAMP] = new DateTime(); // used for fallback reasons (sets timestamp to current time)
            $this->successfullyParsedTimestamp = false; // mark timestamp as NOT successfully parsed
            $error |= self::ERROR_TIMESTAMP;
        }

        // write strings
        $this->data[self::KEY_NAME] = strval($name);
        $this->data[self::KEY_CLUB] = strval($club);

        // parsing integers
        $this->data[self::KEY_ID] = intval($ID);
        $this->data[self::KEY_DISCIPLINE_ID] = intval($discipline_id);
        $this->data[self::KEY_START_NUMBER] = intval($start_number);
        $this->data[self::KEY_TIME] = intval($time);

        // parsing floats
        $this->data[self::KEY_SCORE_SUBMITTED] = floatval($score_submitted);
        $this->data[self::KEY_SCORE_ACCOMPLISHED] = floatval($score_accomplished);

        // mark result as finished or not (ongoing)
        $this->data[self::KEY_FINISHED] = filter_var($finished, FILTER_VALIDATE_BOOLEAN);;

        // return errors
        return $error;
    }

    /**
     * Returns values to serialize
     * 
     * @return AssociatedArray Array to serialize
     */
    public function jsonSerialize(): array
    {
        return [
            self::KEY_ID => $this->{self::KEY_ID},
            // self::KEY_TIMESTAMP => $this->{self::KEY_TIMESTAMP}->getTimestamp(),
            self::KEY_DISCIPLINE_ID => $this->{self::KEY_DISCIPLINE_ID},
            self::KEY_START_NUMBER => $this->{self::KEY_START_NUMBER},
            self::KEY_NAME => $this->{self::KEY_NAME},
            self::KEY_CLUB => $this->{self::KEY_CLUB},
            self::KEY_SCORE_SUBMITTED => $this->{self::KEY_SCORE_SUBMITTED},
            self::KEY_SCORE_ACCOMPLISHED => $this->{self::KEY_SCORE_ACCOMPLISHED},
            self::KEY_TIME => $this->{self::KEY_TIME},
            self::KEY_FINISHED => $this->{self::KEY_FINISHED}
        ];
    }
}
