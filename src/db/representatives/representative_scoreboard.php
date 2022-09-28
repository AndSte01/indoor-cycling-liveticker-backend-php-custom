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
class scoreboard implements JsonSerializable, RepresentativeChildInterface
{
    // use trait for basic functionality
    use RepresentativeTrait;

    // Keys used in data array
    /** @var string id of the scoreboard (unique identifier in database) */
    public const KEY_INTERNAL_ID = "internal_id";
    /** @var string id of the scoreboard used by the client (predictable by client and unique in combination with competition id) */
    public const KEY_EXTERNAL_ID = "id";
    /** @var string timestamp of the scoreboard (timestamp the scoreboard was last modified in the database) */
    public const KEY_TIMESTAMP = "timestamp";
    /** @var string id of the competition the scoreboard is assigned to */
    public const KEY_COMPETITION_ID = "competition";
    /** @var string content of the scoreboard
     * 
     * | value | meaning                                                         |
     * | :---: | --------------------------------------------------------------- |
     * | `-3`  | Training                                                        |
     * | `-2`  | Break                                                           |
     * | `-1`  | Tells the frontend to use `custom_text` field in the scoreboard |
     * |  `0`  | marks scoreboard as disabled                                    |
     * | `>0`  | the content filed is interpreted as an id of a result           |
     */
    public const KEY_CONTENT = "content";
    /** @var string custom text used in case content is set to -1 */
    public const KEY_CUSTOM_TEXT = "custom_text";

    /** @var array data stored in the discipline */
    protected $data = [
        self::KEY_INTERNAL_ID => 0,
        self::KEY_EXTERNAL_ID => 0,
        self::KEY_TIMESTAMP => 0,
        self::KEY_COMPETITION_ID => 0,
        self::KEY_CONTENT => 0,
        self::KEY_CUSTOM_TEXT => ""
    ];

    // Errors
    /** @var int Error while parsing the internal id */
    const ERROR_INTERNAL_ID = 1;
    /** @var int Error while parsing the external id */
    const ERROR_EXTERNAL_ID = 2;
    /** @var int Error while parsing date */
    const ERROR_TIMESTAMP = 4;
    /** @var int Error while parsing competition id */
    const ERROR_COMPETITION_ID = 8;
    /** @var int Error while parsing content */
    const ERROR_CONTENT = 16;
    /** @var int Error while parsing custom text (or if it contained invalid characters and they were removed) */
    const ERROR_CUSTOM_TEXT = 32;

    /**
     * Constructor 
     * 
     * @param int $ID Id of the scoreboard
     * @param DateTime $timestamp The last time the scoreboard was modified in the database
     * @param int $competition_id ID of the competition the scoreboard is assigned to
     * @param int $content Content of the scoreboard (see documentation of const KEY_CONTENT to get more information about the values)
     * @param string $custom_text Custom text used in case `content == -1`
     */
    function __construct(
        int $internal_ID = null,
        int $external_ID = null,
        DateTime $timestamp = null,
        int $competition_id = null,
        int $content = null,
        string $custom_text = null
    ) {
        // this strange way of setting the defaults is used so one can just null all unused fields during construction
        // not relay performant but makes debugging a bit easier
        $this->data[self::KEY_INTERNAL_ID]    = $internal_ID    ?? 0;
        $this->data[self::KEY_EXTERNAL_ID]    = $external_ID    ?? 0;
        $this->data[self::KEY_TIMESTAMP]      = $timestamp      ?? new DateTime();
        $this->data[self::KEY_COMPETITION_ID] = $competition_id ?? 0;
        $this->data[self::KEY_CONTENT]        = $content        ?? 0;
        $this->data[self::KEY_CUSTOM_TEXT]    = $custom_text    ?? "";
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
        $this->data[self::KEY_INTERNAL_ID] = $ID;
        return $this;
    }

    // explained in RepresentativeChildInterface
    public function updateParentId(int $ID): void
    {
        $this->data[self::KEY_COMPETITION_ID] = $ID;
    }

    /**
     * Parse strings into the scoreboard.
     * NO CHECKS ARE DONE WETHER THE VALUES ARE USEFUL OR NOT, JUST TYPE-SAFETY.
     * 
     * @param ?string $ID Id of the scoreboard
     * @param ?string $timestamp the timestamp of the last modification of the scoreboard in the database
     * @param ?string $competition_id Id of the competition the scoreboard is assigned to
     * @param ?string $content Content of the scoreboard (see documentation of const KEY_CONTENT to get more information about the values)
     * @param ?string $custom_text Custom text used in case `content == -1`
     * 
     * @return int the errors occurred during parsing
     */
    public function parse(
        ?string $internal_ID = "",
        ?string $external_ID = "",
        ?string $timestamp = "",
        ?string $competition_id = "",
        ?string $content = "",
        ?string $custom_text = "",
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
        $this->data[self::KEY_CUSTOM_TEXT] = strval($custom_text);

        // parsing integers
        $this->data[self::KEY_INTERNAL_ID] = intval($internal_ID);
        $this->data[self::KEY_EXTERNAL_ID] = intval($external_ID);
        $this->data[self::KEY_COMPETITION_ID] = intval($competition_id);
        $this->data[self::KEY_CONTENT] = intval($content);

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
            // self::KEY_INTERNAL_ID => $this->{self::KEY_INTERNAL_ID},
            self::KEY_EXTERNAL_ID => $this->{self::KEY_EXTERNAL_ID},
            // self::KEY_TIMESTAMP => $this->{self::KEY_TIMESTAMP}->getTimestamp(),
            self::KEY_COMPETITION_ID => $this->{self::KEY_COMPETITION_ID},
            self::KEY_CONTENT => $this->{self::KEY_CONTENT},
            self::KEY_CUSTOM_TEXT => $this->{self::KEY_CUSTOM_TEXT}
        ];
    }
}
