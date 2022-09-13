<?php

/**
 * A manager for authentications
 * 
 * @package Database\Managers
 */

// global namespace is desired

// define aliases
use db\user;

// add required database tools
require_once(dirname(__FILE__) . "/../representatives/db_representatives_user.php");
require_once(dirname(__FILE__) . "/../../errors.php");

/**
 * Helps managing an authentication
 * 
 * Many of the included procedures work in an iterative manner, with each iteration beginning with a new request from the client.
 * Keep that in mind when working with this class, it helps understanding the functions a lot. 
 */
class managerAuthentication
{
    /** @var string area the authentication is valid in */
    protected string $realm = "default";

    /** @var managerUser User provider used to get users from data source */
    protected managerUser $userManager;

    /** @var bool Wether a user is logged in or not */
    protected bool $isLoggedIn = false;
    /** @var ?user User currently authenticated */
    protected ?user $currentUser = null;
    /** @var ?string The bearer token of the currently logged in user */
    protected ?string $currentBearerToken = null;
    /** @var bool Wether an authentication routine should be initiated even if correct credentials were passed */
    protected bool $forceNewCredentials = false;

    // Errors that might happen during authentication
    /** @var int Error if client didn't provide authentication header */
    public const ERROR_NO_AUTHENTICATION_INFO = 1;
    /** @var int Error if the Username doesn't exists */
    public const ERROR_NO_SUCH_USER = 2;
    /** @var int Error if the password isn't correct */
    public const ERROR_INVALID_PASSWORD = 4;
    /** @var int The request was invalid (due to multiple reasons) */
    public const ERROR_INVALID_REQUEST = 8;
    /** @var int The provided bearer token wasn't correct */
    public const ERROR_INVALID_TOKEN = 16;
    /** @var int The user isn't qualified */
    public const ERROR_NOT_QUALIFIED = 32;
    /** @var int The client used the wrong authentication method */
    public const ERROR_WRONG_AUTHENTICATION_METHOD = 64;
    /** @var int A new authentication was forced by a previous call of logout(), This is not an error but desired behavior */
    public const ERROR_FORCED_AUTHENTICATION = 128;

    /** @var int All Authentication methods can be used to authenticate */
    public const AUTHENTICATION_METHOD_ANY = 0;
    /** @var int Basic authentication method (see https://datatracker.ietf.org/doc/html/rfc7617) */
    public const AUTHENTICATION_METHOD_BASIC = 1;
    /** @var int Bearer token authentication method (see https://datatracker.ietf.org/doc/html/rfc6750) */
    public const AUTHENTICATION_METHOD_BEARER = 2;

    /**
     * Constructor
     * 
     * @param managerUser $userManager The user manager to user
     * @param string $realm The realm the authentication should be valid in
     */
    public function __construct(managerUser $userManager, string $realm)
    {
        if ($userManager == null)
            throw new Exception("user provider mustn't be empty/null", 1);

        $this->realm = $realm ?? "default";
        $this->userManager = $userManager;
    }

    /**
     * Returns the current user
     * 
     * @return user The current user
     */
    public function getCurrentUser(): ?user
    {
        return $this->currentUser;
    }

    /**
     * Gets the bearer token (only not null after successful basic authentication)
     * 
     * @return ?string The current bearer token
     */
    public function getCurrentToken(): ?string
    {
        return $this->currentBearerToken;
    }

    /**
     * Checks wether a user is logged in or not
     * 
     * @return bool Wether a user is logged in or not
     */
    public function isLoggedIn(): bool
    {
        return $this->isLoggedIn;
    }

    /**
     * Authenticates a user by the selected method
     * 
     * Possible methods are:
     * - Basic Authentication (AUTHENTICATION_METHOD_BASIC) (see https://datatracker.ietf.org/doc/html/rfc7617)
     * - Bearer Authentication (AUTHENTICATION_METHOD_BEARER) (see https://datatracker.ietf.org/doc/html/rfc6750)
     * - Any of the above mentioned (AUTHENTICATION_METHOD_ANY) (note: you need to provide preferred method as parameter)
     * 
     * No input (other than which method to use) required since all data is provided inside the http header that is analyzed internally.
     * 
     * After receiving an return that is not 0 (meaning an error happened) exit the script as soon as possible,
     * so new authentication data can be requested from the client. This is all done inside the http header, so be careful when modifying it
     * (especially don't  modify the response code or the authentication field).
     * 
     * Please note, when using bearer authentication, errors are also logged inside the authentication header
     * (see https://datatracker.ietf.org/doc/html/rfc6750#section-3.1)
     * 
     * It depends on the method of the authentication wether a current user or a current bearer token can be provided, see the corresponding getters for mor details.
     * 
     * Note: you can authenticate twice (the header gets analyzed all over again)
     * 
     * @param int $method the authentication method to use
     * @param int $minimum_role the minimum role of the user that is required for successful authentication (only used when utilizing bearer authentication)
     * @param int $method_preferred the method the client should use if no authentication data is provided and any method is allowed
     * 
     * @throws OutOfRangeException If the selected method isn't in the above mentioned
     * 
     * @return int The errors that happened during authentication, or if none happened 0.
     */
    public function authenticate(int $method, int $minimum_role = 0, int $method_preferred = self::AUTHENTICATION_METHOD_BASIC): int
    {
        // --- 1. try to get the required information from the header

        // if a special method (meaning method isn't self::AUTHENTICATION_METHOD_ANY) is defined set it as the preferred one
        if ($method != self::AUTHENTICATION_METHOD_ANY) {
            $method_preferred = $method;
        }

        // create placeholder variable for decoded method nad header content
        $method_internally = 0;
        $header_content = [];

        // try to decode header, yeah I don't get it either why I used a separate function for that stuff.
        $header_errors = self::validateDecodeHeader($method_internally, $method, $method_preferred, $header_content);

        // now map the functions for easier use (note: this needs to bee done before handling $header_errors,
        // elsewise the headers can't be set correctly)
        switch ($method_internally) {
            case self::AUTHENTICATION_METHOD_BASIC:
                // send out a new authentication message
                $setAuthHeader = "setBasicAuthenticationHeader";
                // decodes the header payload
                $decodeHeader = "decodeBasicAuthenticationHeader";
                break;

            case self::AUTHENTICATION_METHOD_BEARER:
                $setAuthHeader = "setBearerAuthenticationHeader";
                $decodeHeader = "decodeBearerAuthenticationHeader";
                break;

            default:
                // if none of the supported methods was selected throw error
                throw new OutOfRangeException("The selected authentication method isn't supported");
        }

        // handle errors that happened during decoding of the header
        switch ($header_errors) {
            case self::ERROR_NO_AUTHENTICATION_INFO:
                $this->$setAuthHeader(self::ERROR_INVALID_REQUEST);
                return $header_errors;
                break;

            case self::ERROR_INVALID_REQUEST:
                $this->$setAuthHeader(self::ERROR_INVALID_REQUEST);
                return $header_errors;
                break;

            case self::ERROR_WRONG_AUTHENTICATION_METHOD:
                $this->$setAuthHeader(self::ERROR_INVALID_REQUEST);
                return $header_errors;
                break;
        }

        // now decode the payload
        $payload_decoded = $this->$decodeHeader($header_content[1]);

        // check if decoding was UNsuccessful
        if (is_bool($payload_decoded)) {
            $this->$setAuthHeader(self::ERROR_INVALID_REQUEST);
            return self::ERROR_INVALID_REQUEST;
        }

        // --- 3. we now have valid (authentication) data, now check if it authenticates a user successfully ---

        // handle the different authentication methods
        switch ($method_internally) {
            case self::AUTHENTICATION_METHOD_BASIC:
                // try authentication
                $auth_result = $this->userManager->authenticateWithPassword($payload_decoded["username"], $payload_decoded["password"]);

                // check the yielded results, if it is an int an error occurred
                if (is_int($auth_result)) {
                    switch ($auth_result) {
                        case managerUser::ERROR_NOT_EXISTING:
                            $this->$setAuthHeader();
                            return self::ERROR_NO_SUCH_USER;
                            break;

                        case managerUser::ERROR_PASSWORD:
                            $this->$setAuthHeader();
                            return self::ERROR_INVALID_PASSWORD;
                            break;
                    }
                }

                // exit switch statement
                break;

            case self::AUTHENTICATION_METHOD_BEARER:
                $auth_result = $this->userManager->authenticateWithToken($payload_decoded);

                // check the yielded results, if it is an int an error occurred
                if (is_int($auth_result)) {
                    // set new basic authentication header
                    $this->$setAuthHeader(self::ERROR_INVALID_TOKEN);

                    // for testing only
                    /*switch ($auth_result) {
                        case managerUser::ERROR_NOT_EXISTING:
                            return self::ERROR_NO_SUCH_USER;

                        case managerUser::ERROR_TOKEN:
                            return self::ERROR_INVALID_TOKEN;

                        case managerUser::ERROR_ROLE:
                            return self::ERROR_NOT_QUALIFIED;
                    }*/

                    return self::ERROR_INVALID_TOKEN;
                }

                // now check if the user meets the minimal qualification
                if ($auth_result->{user::KEY_ROLE} < $minimum_role) {
                    $this->$setAuthHeader(self::ERROR_NOT_QUALIFIED);
                    return self::ERROR_NOT_QUALIFIED;
                }

                // exit switch statement
                break;
        }

        // at this point everything worked out fine, the user was correctly validated and is now set to the currently logged in user
        $this->currentUser = $auth_result;
        $this->isLoggedIn = true;

        // and only when using basic authenticating
        if ($method_internally == self::AUTHENTICATION_METHOD_BASIC)
            $this->currentBearerToken = $this->userManager->getBearerToken($this->currentUser);

        // now check if the user want's to logout, first if the user needs to bee logged in to logout,
        // secondly you can only create a new bearer token if you know the user
        // check if authentication message should be forcefully send
        if ($this->forceNewCredentials) {
            // a forced authentication will be sent only once
            $this->forceNewCredentials = false;

            // send out a new authentication message
            $this->$setAuthHeader();

            // reset bearer token
            $this->userManager->resetBearerToken($this->currentUser);

            // return an error stating that a new authentication was forcefully required and therefor the old credentials don't work any longer
            return self::ERROR_FORCED_AUTHENTICATION;
        }

        // at this point everything was ok so return no error (= 0)
        return 0;
    }

    /**
     * Function that validates and decodes the header sent to the server.
     * 
     * Decoded information is passed by reference, only errors are passed via return.
     * This function can also check if the client used the authentication scheme desired by the server,
     * and also wether the desired method is supported by this class.
     * 
     * @param int &$method The variable in which the decoded method should bes stored, passed by reference.
     * @param int $desired_method The method that is desired by the server ($method is set to this value in case the client used the wrong auth scheme)
     * @param int $fallback_method The method that should be assumed in case the method used by the client couldn't be detected 
     * @param array &$ header_content The content's of the header that were found during decoding
     * 
     * @throws OutOfRangeException If the selected method isn't in the above mentioned
     * 
     * @return int the errors that happened during validation and decoding (0 in case of success)
     */
    protected function validateDecodeHeader(int &$method, int $desired_method, int $fallback_method, array &$header_content)
    {
        // --- 1. do some basic checks of authentication information

        // check if authentication information is present
        if (empty($_SERVER["HTTP_AUTHORIZATION"])) {
            // set the method to the fallback method
            $method = $fallback_method;
            // return error indication no authentication info was sent by client
            return self::ERROR_NO_AUTHENTICATION_INFO;
        }

        // --- 2. authentication information is present, now decode it and check the validity ---

        // get authentication header
        $auth_header = $_SERVER["HTTP_AUTHORIZATION"];

        // split header in parts
        $header_content = explode(" ", $auth_header);

        // check if enough information is present (assuming e. g. $header_content[0]="Basic" $header_content[1]="MToy")
        if (count($header_content) < 2) {
            // return error indication the request was invalid
            return self::ERROR_INVALID_REQUEST;
        }

        // try to find out which authentication message the client used
        switch ($header_content[0]) {
            case "Basic":
                // set method to basic (note: passed by reference)
                $method = self::AUTHENTICATION_METHOD_BASIC;
                break;

            case "Bearer":
                // set method to bearer (note: passed by reference)
                $method = self::AUTHENTICATION_METHOD_BEARER;
                break;

            default:
                // set method to 0 (in hope the returned error is caught correctly)
                $method = $fallback_method; // set method (passed by reference) to fallback_method
                return self::ERROR_INVALID_REQUEST; // return error
                break;
        }

        // check what authentication method was required and wether the client used it correctly ($method is already checked for being in a valid range)
        switch ($desired_method) {
            case self::AUTHENTICATION_METHOD_ANY:
                // no checks need to be done
                break;

            case self::AUTHENTICATION_METHOD_BASIC:
                // if other auth method is desired by server return error
                if ($method != self::AUTHENTICATION_METHOD_BASIC) {
                    $method = $desired_method;
                    return self::ERROR_WRONG_AUTHENTICATION_METHOD;
                }
                break;

            case self::AUTHENTICATION_METHOD_BEARER:
                // if other auth method is desired by server return error
                if ($method != self::AUTHENTICATION_METHOD_BEARER) {
                    $method = $desired_method;
                    return self::ERROR_WRONG_AUTHENTICATION_METHOD;
                }
                break;

            default:
                // if none of the supported methods was selected throw error
                throw new OutOfRangeException("The selected authentication method isn't supported");
        }

        // return that everything went ok
        return 0;
    }

    /**
     * Decodes a basic authentication header
     * 
     * @param string $payload The payload to decode
     * 
     * @return array|bool Either an array containing ["username" => $username, "password" => $password] or false in case of error
     */
    protected function decodeBasicAuthenticationHeader(string $payload = ""): array|bool
    {
        // if no payload is provided return false
        if ($payload == null)
            return false;

        // decode payload to string
        $payload_decoded = base64_decode($payload, true);

        // check if decode has been successful
        if ($payload_decoded === false)
            return false;

        // split provided authentication information in parts
        $payload_content = explode(":", $payload_decoded);

        // check if payload_content has the right size (meaning wether the correct amount of data has been provided)
        if (count($payload_content) != 2)
            return false;

        // write data in assoc array
        return [
            "username" => $payload_content[0],
            "password" => $payload_content[1]
        ];
    }

    /**
     * Decodes a bearer authentication header
     * 
     * @param string $payload The payload to decode
     * 
     * @return string|bool Either the bearer token or false in case of error
     */
    protected function decodeBearerAuthenticationHeader(string $payload = ""): string|bool
    {
        // if no payload is provided return false
        if ($payload == null)
            return false;

        // no errors happened return payload as token
        return $payload;
    }

    /**
     * Sets the header for requesting basic authentication details
     */
    protected function setBasicAuthenticationHeader()
    {
        // set the response code
        header("HTTP/1.1 401 Unauthorized");

        // set the authentication field in the header
        // according to https://datatracker.ietf.org/doc/html/rfc7617
        // WWW-Authenticate: Basic realm="WallyWorld", charset="UTF-8"
        header("WWW-Authenticate: Basic realm=\"" . $this->realm . "\", charset=\"UTF-8\"");;
    }

    /**
     * Sets the header for requesting bearer authentication details
     * 
     * @param int $error Error code to use (valid options are 0, ERROR_INVALID_REQUEST, ERROR_INVALID_TOKEN, ERROR_NOT_QUALIFIED)
     * (see https://datatracker.ietf.org/doc/html/rfc6750#section-3.1)
     */
    protected function setBearerAuthenticationHeader(int $error = 0)
    {
        // set the response code
        header("HTTP/1.1 401 Unauthorized");

        $error_string = "";

        // select what error to add
        switch ($error) {
            case self::ERROR_INVALID_REQUEST:
                $error_string = ", error=\"invalid_request\"";
                break;

            case self::ERROR_INVALID_TOKEN:
                $error_string = ", error=\"invalid_token\"";
                break;

            case self::ERROR_NOT_QUALIFIED:
                $error_string = ", error=\"insufficient_scope\"";
                break;
        }

        // set the authentication filed in the header
        // according to https://datatracker.ietf.org/doc/html/rfc6750
        // WWW-Authenticate: Bearer realm="example",
        //                   error="invalid_token",
        //                   error_description="The access token expired"
        header("WWW-Authenticate: Bearer realm=\"" . $this->realm . "\"" . $error_string);
    }

    /**
     * Logs the current user out.
     * 
     * Resets it's bearer token and forces new login information. Need't to be called before authenticate() is called.
     */
    public function logout(): void
    {
        // force new login
        $this->forceNewCredentials = true;
    }
}
