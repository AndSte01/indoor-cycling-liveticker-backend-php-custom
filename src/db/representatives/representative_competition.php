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
 * The class used to describe a competition.
 */
class competition implements JsonSerializable, RepresentativeChildInterface
{
    // use trait for basic functionality
    use RepresentativeTrait;

    // Keys used in data array
    /** @var string Key to get competition id (unique id in database) */
    public const KEY_ID = "id";
    /** @var string Key to get competition date */
    public const KEY_DATE = "date";
    /** @var string Key to get competition name */
    public const KEY_NAME = "name";
    /** @var string Key to get competition location */
    public const KEY_LOCATION = "location";
    /** @var string Key to get competition credential */
    public const KEY_USER = "user";
    /** @var string Key to get competition areas */
    public const KEY_AREAS = "areas";
    /** @var string Key to get competition feature_set */
    public const KEY_FEATURE_SET = "feature_set";
    /** @var string Key to get wether competition is live or not */
    public const KEY_LIVE = "live";

    /** @var array data stored in the competition */
    protected $data = [
        self::KEY_ID => 0,
        self::KEY_DATE => null,
        self::KEY_NAME => "",
        self::KEY_LOCATION => "",
        self::KEY_USER => 0,
        self::KEY_AREAS => 0,
        self::KEY_FEATURE_SET => 0,
        self::KEY_LIVE => 0
    ];

    // Errors
    /** @var int Error while parsing the id */
    const ERROR_ID = 1;
    /** @var int Error while parsing date */
    const ERROR_DATE = 2;
    /** @var int Error while parsing name */
    const ERROR_NAME = 4;
    /** @var int Error while parsing location */
    const ERROR_LOCATION = 8;
    /** @var int Error while parsing credentials */
    const ERROR_USER = 16;
    /** @var int Error while parsing areas */
    const ERROR_AREAS = 32;
    /** @var int Error while parsing feature set */
    const ERROR_FEATURE_SET = 64;
    /** @var int Error while parsing live */
    const ERROR_LIVE = 128;

    /**
     * Constructor 
     * 
     * @param int $ID ID of the competition
     * @param DateTime $date Date of the competition
     * @param string $name Name of the competition
     * @param string $location Location of the competition
     * @param int $user ID of the user assigned to the competition
     * @param int $areas Number of areas of the competition
     * @param int $feature_set Feature set of the competition
     * @param int $live Wether competition is live or not
     */
    function __construct(
        int $ID = null,
        DateTime $date = null,
        string $name = null,
        string $location = null,
        int $user = null,
        int $areas = null,
        int $feature_set = null,
        int $live = null
    ) {
        // this strange way of setting the defaults is used so one can just null all unused fields during construction
        // not relay performant but makes debugging a bit easier
        $this->data[self::KEY_ID]          = $ID          ?? 0;
        $this->data[self::KEY_USER]        = $user        ?? 0;
        $this->data[self::KEY_AREAS]       = $areas       ?? 0;
        $this->data[self::KEY_FEATURE_SET] = $feature_set ?? 0;
        $this->data[self::KEY_LIVE]        = $live        ?? 0;
        $this->data[self::KEY_DATE]        = $date        ?? new DateTime();
        $this->data[self::KEY_NAME]        = $name        ?? "";
        $this->data[self::KEY_LOCATION]    = $location    ?? "";
    }

    // explained in RepresentativeInterface
    public function updateId(int $ID): self
    {
        $this->data[self::KEY_ID] = $ID;
        return $this;
    }

    /**
     * Changes the user id the competition got assigned
     */
    public function updateParentId(int $ID): void
    {
        $this->data[self::KEY_USER] = $ID;
    }

    /**
     * Parse strings into the competition
     * NO CHECKS ARE DONE WETHER THE VALUES ARE USEFUL OR NOT, JUST TYPE-SAFETY.
     * 
     * @param ?string $ID ID of the competition
     * @param ?string $date Date of the competition
     * @param ?string $name Name of the competition
     * @param ?string $location Location of the competition
     * @param ?string $user ID of the user the competition is assigned to
     * @param ?string $areas Number of areas of the competition
     * @param ?string $feature_set Feature set of the competition
     * @param ?string $live Wether competition is live or not
     * 
     * @return int the errors occurred during parsing
     */
    public function parse(
        ?string $ID = "",
        ?string $date = "",
        ?string $name = "",
        ?string $location = "",
        ?string $user = "",
        ?string $areas = "",
        ?string $feature_set = "",
        ?string $live = "",
    ): int {
        // variable for error
        $error = 0;

        // try to generate date from string, if it fails, log error and set date to current date
        try {
            $this->data[self::KEY_DATE] = new DateTime($date);
        } catch (\Exception $e) {
            error_log($e);
            $this->data[self::KEY_DATE] = new DateTime();
            $error |= self::ERROR_DATE;
        }

        // write name, location and credential
        $this->data[self::KEY_NAME] = strval($name);
        $this->data[self::KEY_LOCATION] = strval($location);

        // parsing id, areas, feature_set and live
        $this->data[self::KEY_ID] = intval($ID);
        $this->data[self::KEY_USER] = intval($user);
        $this->data[self::KEY_AREAS] = intval($areas);
        $this->data[self::KEY_FEATURE_SET] = intval($feature_set);
        $this->data[self::KEY_LIVE] = intval($live);

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
            self::KEY_DATE => $this->{self::KEY_DATE}->format("Y-m-d"),
            self::KEY_NAME => $this->{self::KEY_NAME},
            self::KEY_LOCATION => $this->{self::KEY_LOCATION},
            self::KEY_AREAS => $this->{self::KEY_AREAS},
            self::KEY_FEATURE_SET => $this->{self::KEY_FEATURE_SET},
            self::KEY_LIVE => boolval($this->{self::KEY_LIVE})
        ];
    }
}
