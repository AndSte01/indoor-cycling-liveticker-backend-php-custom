<?php

/**
 * Representatives of the data stored in the database
 * 
 * This document contains the classes used by several scripts in context of managing the database
 * and providing information to the client
 * 
 * @todo explain how representatives are build
 * 
 * @package Database\Representatives
 */

// assign namespace
namespace db;

// define aliases
use mysqli;

/**
 * The basic interface for defining representatives
 */
interface RepresentativeInterface
{
    /**
     * Parse strings into the competition
     * NO CHECKS ARE DONE WETHER THE VALUES ARE USEFUL OR NOT, JUST TYPE-SAFETY.
     * 
     * @return int the errors occurred during parsing
     */
    public function parse();

    /**
     * Updates the id of the representative
     * 
     * @param int $ID new id of the representative
     */
    public function updateId(int $ID): self;
}


/**
 * Extension of Representative Interface with additional support for dealing with parent entities
 */
interface RepresentativeChildInterface extends RepresentativeInterface
{
    /**
     * Updates the parent id the representative got assigned
     * 
     * @param int $ID new id of the parent
     */
    public function updateParentId(int $ID): void;
}


/**
 * The basic trait for a representative
 * 
 * @method __get() gets values of representative
 * @method __set() sets value of representative
 * @method bool isDbReady() Wether representative is ready for the database or not
 * 
 * @var bool $isDbReady store value returned by isDbReady()
 */
trait RepresentativeTrait
{
    /**
     * Enables a smooth but read only experience for dealing with variables stored inside the representative
     * 
     * @param $key Key to return value of (use constants to make sure it exists)
     * @return mixed Value of key, null if key doesn't exist
     */
    public function __get($key)
    {
        if (isset($this->data[$key])) {
            return $this->data[$key];
        } else {
            return null;
        }
    }

    /**
     * Overwriting default setter to prevent writing of variables without using the correct functions
     * 
     * @param mixed $name NOT USED
     * @param mixed $value NOT USED
     * 
     * @throws Exception Variables in objects implementing representative are read only
     */
    public function __set($name, $value)
    {
        throw new \Exception("variables in objects implementing representative are read only");
    }
}
