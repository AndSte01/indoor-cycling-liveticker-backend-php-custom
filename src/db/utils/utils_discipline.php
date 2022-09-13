<?php

/**
 * Some functions that might be useful for working with results
 * 
 * @package Database\Utilities
 */

// assign namespace
namespace db\utils;

/**
 * Class used to make working with discipline types a bit more easy and separate the specialized code
 * 
 * Note: adaptor_discipline makes use of those
 */
class discipline_type
{
    // Keys for accessing assoc arrays mor easily
    // disciplines
    const KEY_DISCIPLINE_SINGE_ARTISTIC_CYCLING = "discipline_single_artistic_cycling";
    const KEY_DISCIPLINE_PAIR_ARTISTIC_CYCLING = "discipline_pair_artistic_cycling";
    const KEY_DISCIPLINE_TEAM_4_ARTISTIC_CYCLING = "discipline_team_4_artistic_cycling";
    const KEY_DISCIPLINE_TEAM_6_ARTISTIC_CYCLING = "discipline_team_6_artistic_cycling";
    const KEY_DISCIPLINE_TEAM_4_UNICYCLE = "discipline_team_4_unicycle";
    const KEY_DISCIPLINE_TEAM_6_UNICYCLE = "discipline_team_6_unicycle";

    // genders
    const KEY_GENDER_MALE_OPEN = "gender_male_open";
    const KEY_GENDER_FEMALE = "gender_female";

    // age-groups
    const KEY_AGE_U11 = "age_u11";
    const KEY_AGE_U13 = "age_u13";
    const KEY_AGE_U15 = "age_u15";
    const KEY_AGE_U19 = "age_u19";
    const KEY_AGE_ELITE = "age_elite";

    /** @var array Valid disciplines used to validate a type (be aware the four trailing zeros are removed) */
    public const VALID_DISCIPLINES = [
        self::KEY_DISCIPLINE_SINGE_ARTISTIC_CYCLING  => 0b000, // Single artistic cycling
        self::KEY_DISCIPLINE_PAIR_ARTISTIC_CYCLING   => 0b001, // Pair artistic cycling
        self::KEY_DISCIPLINE_TEAM_4_ARTISTIC_CYCLING => 0b010, // Artistic Cycling Team 4 (ACT4)
        self::KEY_DISCIPLINE_TEAM_6_ARTISTIC_CYCLING => 0b011, // Artistic Cycling Team 6 (ACT6)
        self::KEY_DISCIPLINE_TEAM_4_UNICYCLE         => 0b110, // Unicycle Team 4
        self::KEY_DISCIPLINE_TEAM_6_UNICYCLE         => 0b111  // Unicycle Team 6
    ];

    /** @var array Valid genders used to validate a type (be aware the three trailing zeros were removed) (also might be a bit unnecessary) */
    public const VALID_GENDERS = [
        self::KEY_GENDER_MALE_OPEN => 0b0, // male, open
        self::KEY_GENDER_FEMALE    => 0b1  // female
    ];

    /** @var array Valid ages used to validate a type */
    public const VALID_AGES = [
        self::KEY_AGE_U11   => 0b001, // Pupils U11
        self::KEY_AGE_U13   => 0b010, // Pupils U13
        self::KEY_AGE_U15   => 0b011, // Pupils U15
        self::KEY_AGE_U19   => 0b100, // Juniors U19
        self::KEY_AGE_ELITE => 0b101  // Elite
    ];

    /**
     * Checks wether the Type provided as input is valid
     * 
     * @param int $type the value to check
     * 
     * @return bool Wether $type was valid or not
     */
    public static function validateType(int $type): bool
    {
        // check if type is -1
        if ($type === -1)
            return true;

        /**
         * check if type has bits in it that aren't used and therefore must be 0
         * 
         * move type 7 bits to the right 
         * all relevant bits that might not be 0 (in a valid type) are now shifted off
         * Then check wether value is 0 (right shifts do preserve the sign!)
         */
        if (($type >> 7) != 0)
            return false;

        // at this point we know that only the 7 least significant bits aren't 0
        // now slice the type into it's different meaningful parts
        // create discipline slice
        $discipline = $type >> 4;
        // create gender slice (be aware here we must filter for the right bits because the discipline bits aren't shifted out)
        $gender = ($type & 0b1000) >> 3;
        // create age-group slice
        $age_group = $type & 0b111;

        // check wether discipline is in list of valid disciplines
        if (!in_array($discipline, self::VALID_DISCIPLINES))
            return false;

        // gender is always correct (no check required) (a single bit can either be 0 or 1 :)

        // check wether the age group is in the list of valid age groups
        if (!in_array($age_group, self::VALID_AGES))
            return false;

        // all test were passed (elsewise the return statement would have been called earlier)
        return true;
    }
}
