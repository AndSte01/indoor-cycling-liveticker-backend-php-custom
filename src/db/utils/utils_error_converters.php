<?php

/**
 * Functions that convert the several manager Errors to global errors
 * 
 * @package Database\Utilities
 */

// assign namespace
namespace db\utils;

// define aliases
use errors;
use managerAuthentication;
use managerCompetition;
use managerDiscipline;
use managerResult;
use managerScoreboard;
use managerUser;

// import required filed
require_once(dirname(__FILE__) . "/../managers/manager_authentication.php");
require_once(dirname(__FILE__) . "/../managers/manager_user.php");
require_once(dirname(__FILE__) . "/../managers/manager_competition.php");
require_once(dirname(__FILE__) . "/../managers/manager_discipline.php");
require_once(dirname(__FILE__) . "/../managers/manager_result.php");
require_once(dirname(__FILE__) . "/../managers/manager_scoreboard.php");
require_once(dirname(__FILE__) . "/../../errors.php");

/**
 * Converts authentication errors to a string of errors defined in errors.php
 * 
 * @param int $error the error from managerAuthentication
 * 
 * @return string the errors as a string
 */
function authenticationErrorsToString(int $error): string
{
    // don't use prepareDie, elsewise the authentication headers are overwritten
    switch ($error) {
        case managerAuthentication::ERROR_NO_AUTHENTICATION_INFO:
            return errors::to_error_string([errors::AUTHENTICATION_REQUIRED]);

        case managerAuthentication::ERROR_NO_SUCH_USER:
            return errors::to_error_string([errors::NOT_EXISTING]);

        case managerAuthentication::ERROR_INVALID_PASSWORD:
            return errors::to_error_string([errors::ACCESS_DENIED]);

        case managerAuthentication::ERROR_INVALID_REQUEST:
            return errors::to_error_string([errors::INVALID_REQUEST]);

        case managerAuthentication::ERROR_INVALID_TOKEN:
            return errors::to_error_string([errors::ACCESS_DENIED]);

        case managerAuthentication::ERROR_NOT_QUALIFIED:
            return errors::to_error_string([errors::ACCESS_DENIED]);

        case managerAuthentication::ERROR_WRONG_AUTHENTICATION_METHOD:
            return errors::to_error_string([errors::INVALID_REQUEST]);

        case managerAuthentication::ERROR_FORCED_AUTHENTICATION:
            return errors::to_error_string([errors::AUTHENTICATION_REQUIRED]);

        default:
            return errors::to_error_string([]);
    }
}

/**
 * Converts user errors to a string of errors defined in errors.php
 * 
 * @param int $error the error from managerAuthenticationInterface
 * @param bool $prepareDie Prepares the header for immediate call of die() afterwards
 * 
 * @return string the errors as a string
 */
function userErrorsToString(int $error,  bool $prepareDie = false): string
{
    switch ($error) {
        case managerUser::ERROR_ADAPTOR:
            return errors::to_error_string([errors::INTERNAL_ERROR], $prepareDie);

        case managerUser::ERROR_NOT_EXISTING:
            return errors::to_error_string([errors::NOT_EXISTING], $prepareDie);

        case managerUser::ERROR_PASSWORD:
            return errors::to_error_string([errors::INVALID_INPUT], $prepareDie);

        case managerUser::ERROR_TOKEN:
            return errors::to_error_string([errors::INVALID_INPUT], $prepareDie);

        case managerUser::ERROR_ALREADY_EXISTING:
            return errors::to_error_string([errors::ALREADY_EXISTS], $prepareDie);

        case managerUser::ERROR_INVALID_CHARACTERS:
            return errors::to_error_string([errors::INVALID_CHARACTERS], $prepareDie);

        case managerUser::ERROR_ROLE:
            return errors::to_error_string([errors::ACCESS_DENIED], $prepareDie);

        default:
            return errors::to_error_string([]);
    }
}

/**
 * Converts competition errors to a string of errors defined in errors.php
 * 
 * @param int $error the error from managerCompetition
 * @param bool $prepareDie Prepares the header for immediate call of die() afterwards
 * 
 * @return string the errors as a string
 */
function competitionErrorsToString(int $error, bool $prepareDie = false): string
{
    switch ($error) {
        case managerCompetition::ERROR_ALREADY_EXISTING:
            return errors::to_error_string([errors::ALREADY_EXISTS], $prepareDie);

        case managerCompetition::ERROR_MISSING_INFORMATION:
            return errors::to_error_string([errors::MISSING_INFORMATION], $prepareDie);

        case managerCompetition::ERROR_NOT_EXISTING:
            return errors::to_error_string([errors::NOT_EXISTING], $prepareDie);

        case managerCompetition::ERROR_OUT_OF_RANGE:
            return errors::to_error_string([errors::PARAM_OUT_OF_RANGE], $prepareDie);

        case managerCompetition::ERROR_WRONG_USER_ID:
            return errors::to_error_string([errors::ACCESS_DENIED], $prepareDie);

        case managerCompetition::ERROR_ADAPTOR:
            return errors::to_error_string([errors::INTERNAL_ERROR], $prepareDie);

        default:
            return errors::to_error_string([errors::SUCCESS]);
    }
}

/**
 * Converts discipline errors to a string of errors defined in errors.php
 * 
 * @param int $error the error from managerDiscipline
 * @param bool $prepareDie Prepares the header for immediate call of die() afterwards
 * 
 * @return string the errors as a string
 */
function disciplineErrorsToString(int $error, bool $prepareDie = false): string
{
    switch ($error) {
        case managerDiscipline::ERROR_WRONG_COMPETITION_ID:
            return errors::to_error_string([errors::INVALID_PARENT], $prepareDie);

        case managerDiscipline::ERROR_OUT_OF_RANGE:
            return errors::to_error_string([errors::PARAM_OUT_OF_RANGE], $prepareDie);

        case managerDiscipline::ERROR_NOT_EXISTING:
            return errors::to_error_string([errors::NOT_EXISTING], $prepareDie);

        case managerDiscipline::ERROR_ALREADY_EXISTING:
            return errors::to_error_string([errors::ALREADY_EXISTS], $prepareDie);

        case managerDiscipline::ERROR_MISSING_INFORMATION:
            return errors::to_error_string([errors::MISSING_INFORMATION], $prepareDie);

        case managerDiscipline::ERROR_ADAPTOR:
            return errors::to_error_string([errors::INTERNAL_ERROR], $prepareDie);

        default:
            return errors::to_error_string([errors::SUCCESS]);
    }
}

/**
 * Converts results errors to a string of errors defined in errors.php
 * 
 * @param int $error the error from managerResult
 * @param bool $prepareDie Prepares the header for immediate call of die() afterwards
 * 
 * @return string the errors as a string
 */
function resultErrorsToString(int $error, bool $prepareDie = false): string
{
    switch ($error) {
        case managerResult::ERROR_WRONG_DISCIPLINE_ID:
            return errors::to_error_string([errors::INVALID_PARENT], $prepareDie);

        case managerResult::ERROR_OUT_OF_RANGE:
            return errors::to_error_string([errors::PARAM_OUT_OF_RANGE], $prepareDie);

        case managerResult::ERROR_NOT_EXISTING:
            return errors::to_error_string([errors::NOT_EXISTING], $prepareDie);

        case managerResult::ERROR_ALREADY_EXISTING:
            return errors::to_error_string([errors::ALREADY_EXISTS], $prepareDie);

        case managerResult::ERROR_MISSING_INFORMATION:
            return errors::to_error_string([errors::MISSING_INFORMATION], $prepareDie);

        case managerResult::ERROR_ADAPTOR:
            return errors::to_error_string([errors::INTERNAL_ERROR], $prepareDie);

        default:
            return errors::to_error_string([errors::SUCCESS]);
    }
}

/**
 * Converts scoreboard errors to a string of errors defined in errors.php
 * 
 * @param int $error the error from managerScoreboard
 * @param bool $prepareDie Prepares the header for immediate call of die() afterwards
 * 
 * @return string the errors as a string
 */
function scoreboardErrorsToString(int $error, bool $prepareDie = false): string
{
    switch ($error) {
        case managerScoreboard::ERROR_WRONG_COMPETITION_ID:
            return errors::to_error_string([errors::INVALID_PARENT], $prepareDie);

        case managerScoreboard::ERROR_OUT_OF_RANGE:
            return errors::to_error_string([errors::PARAM_OUT_OF_RANGE], $prepareDie);

        case managerScoreboard::ERROR_NOT_EXISTING:
            return errors::to_error_string([errors::NOT_EXISTING], $prepareDie);

        case managerScoreboard::ERROR_ALREADY_EXISTING:
            return errors::to_error_string([errors::ALREADY_EXISTS], $prepareDie);

        case managerScoreboard::ERROR_MISSING_INFORMATION:
            return errors::to_error_string([errors::MISSING_INFORMATION], $prepareDie);

        case managerScoreboard::ERROR_ADAPTOR:
            return errors::to_error_string([errors::INTERNAL_ERROR], $prepareDie);

        default:
            return errors::to_error_string([errors::SUCCESS]);
    }
}
