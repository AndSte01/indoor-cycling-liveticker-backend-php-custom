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
 * The class used to describe a discipline.
 */
class discipline implements JsonSerializable, RepresentativeChildInterface
{
    // use trait for basic functionality
    use RepresentativeTrait;

    // Keys used in data array
    /** @var string id of the discipline (unique identifier in database) */
    public const KEY_ID = "id";
    /** @var string timestamp of the discipline (timestamp the discipline was last modified in the database) */
    public const KEY_TIMESTAMP = "timestamp";
    /** @var string id of the competition the discipline is assigned to */
    public const KEY_COMPETITION_ID = "competition";
    /** @var string type of the discipline
     * 
     * If you can't set the type (e. g. lack of support in application set it to -1)
     * 
     * | ...         | 000        | 0      | 000   |
     * | ----------- | ---------- | ------ | ----- |
     * | reserved    | Discipline | gender | age   |
     * 
     * |       | Discipline                     |   |     | gender     |    |       | age         |
     * | ----- | ------------------------------ |   | --- | ---------- |    | ----- | ----------- |
     * | `000` | Single artistic cycling        |   | `0` | male, open |    | `000` | reserved    |
     * | `001` | Pair artistic cycling          |   | `1` | female     |    | `001` | Pupils  U11 |
     * | `010` | Artistic Cycling Team 4 (ACT4) |                           | `010` | Pupils  U13 |
     * | `011` | Artistic Cycling Team 6 (ACT6) |                           | `011` | Pupils  U15 |
     * | `110` | Unicycle Team 4                |                           | `100` | Juniors U19 |
     * | `111` | Unicycle Team 6                |                           | `101` | Elite       |
     */
    public const KEY_TYPE = "type";
    /** @var string fallback name of the discipline set by provider if type < 0 */
    public const KEY_FALLBACK_NAME = "fallback_name";
    /** @var string Area of the competition the discipline takes place on */
    public const KEY_AREA = "area";
    /** @var string the round of the competition the discipline is located in */
    public const KEY_ROUND = "round";
    /** @var string wether the discipline is finished or not */
    public const KEY_FINISHED = "finished";

    /** @var array data stored in the discipline */
    protected $data = [
        self::KEY_ID => 0,
        self::KEY_TIMESTAMP => 0,
        self::KEY_COMPETITION_ID => 0,
        self::KEY_TYPE => 0,
        self::KEY_FALLBACK_NAME => "",
        self::KEY_AREA => 0,
        self::KEY_ROUND => 0,
        self::KEY_FINISHED => false
    ];

    // Errors
    /** @var int Error while parsing the id */
    const ERROR_ID = 1;
    /** @var int Error while parsing date */
    const ERROR_TIMESTAMP = 2;
    /** @var int Error while parsing competition id */
    const ERROR_COMPETITION_ID = 4;
    /** @var int Error while parsing type */
    const ERROR_TYPE = 8;
    /** @var int Error while parsing fallback name (or if it contained invalid characters and they were removed) */
    const ERROR_FALLBACK_NAME = 16;
    /** @var int Error while parsing area */
    const ERROR_AREA = 32;
    /** @var int Error while parsing the round */
    const ERROR_ROUND = 64;
    /** @var int Error while setting discipline as finished (or not) */
    const ERROR_FINISHED = 128;

    /**
     * Constructor 
     * 
     * @param int $ID Id of the discipline
     * @param DateTime $timestamp The last time the discipline was modified in the database
     * @param int $competition_id ID of the competition the discipline is assigned to
     * @param int $type Type of the discipline (see documentation of const KEY_TYPE to get more information about the values)
     * @param string $fallback_name Name used in case $type can'T be decoded
     * @param int $area Area of the competition the discipline takes place on
     * @param int $round The round of the competition the discipline is located in
     * @param int $finished Wether the discipline is finished or not
     */
    function __construct(
        int $ID = null,
        DateTime $timestamp = null,
        int $competition_id = null,
        int $type = null,
        string $fallback_name = null,
        int $area = null,
        int $round = null,
        bool $finished = null
    ) {
        // this strange way of setting the defaults is used so one can just null all unused fields during construction
        // not relay performant but makes debugging a bit easier
        $this->data[self::KEY_ID]             = $ID             ?? 0;
        $this->data[self::KEY_TIMESTAMP]      = $timestamp      ?? new DateTime();
        $this->data[self::KEY_COMPETITION_ID] = $competition_id ?? 0;
        $this->data[self::KEY_TYPE]           = $type           ?? 0;
        $this->data[self::KEY_FALLBACK_NAME]  = $fallback_name  ?? "";
        $this->data[self::KEY_AREA]           = $area           ?? 0;
        $this->data[self::KEY_ROUND]          = $round          ?? 0;
        $this->data[self::KEY_FINISHED]       = $finished       ?? false;
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
     * Updates the competition id
     */
    public function updateParentId(int $ID): void
    {
        $this->data[self::KEY_COMPETITION_ID] = $ID;
    }


    /**
     * Parse strings into the discipline.
     * NO CHECKS ARE DONE WETHER THE VALUES ARE USEFUL OR NOT, JUST TYPE-SAFETY.
     * 
     * @param ?string $ID Id of the discipline
     * @param ?string $timestamp the timestamp of the last modification of the discipline in the database
     * @param ?string $competition_id Id of the competition the discipline is assigned to
     * @param ?string $type The type of the discipline (see const KEY_TYPE or documentation od api)
     * @param ?string $fallback_name Used in case $type isn't valid or the fronted doesn't support it
     * @param ?string $area Area of the competition the discipline takes place on
     * @param ?string $round the round of the competition the discipline is located in
     * @param ?string $finished wether a discipline is finished or not
     * 
     * @return int the errors occurred during parsing
     */
    public function parse(
        ?string $ID = "",
        ?string $timestamp = "",
        ?string $competition_id = "",
        ?string $type = "",
        ?string $fallback_name = "",
        ?string $area = "",
        ?string $round = "",
        ?string $finished = "",
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

        // write fallback_name
        $this->data[self::KEY_FALLBACK_NAME] = strval($fallback_name);

        // parsing integers
        $this->data[self::KEY_ID] = intval($ID);
        $this->data[self::KEY_COMPETITION_ID] = intval($competition_id);
        $this->data[self::KEY_TYPE] = intval($type);
        $this->data[self::KEY_AREA] = intval($area);
        $this->data[self::KEY_ROUND] = intval($round);

        // mark discipline as finished or not (ongoing)
        $this->data[self::KEY_FINISHED] = filter_var($finished, FILTER_VALIDATE_BOOLEAN);

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
            self::KEY_COMPETITION_ID => $this->{self::KEY_COMPETITION_ID},
            self::KEY_TYPE => $this->{self::KEY_TYPE},
            self::KEY_FALLBACK_NAME => $this->{self::KEY_FALLBACK_NAME},
            self::KEY_AREA => $this->{self::KEY_AREA},
            self::KEY_ROUND => $this->{self::KEY_ROUND},
            self::KEY_FINISHED => $this->{self::KEY_FINISHED}
        ];
    }
}
