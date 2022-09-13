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
require_once("db_managers_user_interface.php");
require_once("db_managers_authentication_interface.php");
require_once(dirname(__FILE__) . "/../representatives/db_representatives_user.php");
require_once(dirname(__FILE__) . "/../../errors.php");

/**
 * Helps managing an authentication
 */
class managerAuthenticationOld implements managerAuthenticationInterface
{
    /** @var string name of hash algorithm to use (for php hash() function) */
    protected const PHP_HASH_ALGORITHM = "sha3-256";

    /** @var int Length of the nonce (in bytes) used for authentication */
    protected const NONCE_LENGTH = 64;

    /** @var string area the authentication is valid in */
    protected string $realm = "default";
    /** @var string unchanged string returned by client */
    protected string $opaque = "";

    /** @var managerUserInterface User provider used to get users from data source */
    protected managerUserInterface $userProvider;

    /** @var bool Wether a user is logged in or not */
    protected bool $isLoggedIn = false;
    /** @var ?user User currently authenticated */
    protected ?user $currentUser = null;
    /** @var bool Wether an authentication routine should be initiated even if correct credentials were passed */
    protected bool $forceNewCredentials = false;


    /**
     * Constructor
     * 
     * @param managerUserInterface $userProvider The user provider to user
     * @param string $realm The realm the authentication should be valid in
     * @param string $opaque Unchanged string returned by client
     */
    public function __construct(managerUserInterface $userProvider, string $realm, string $opaque = "")
    {
        if ($userProvider == null)
            throw new Exception("user provider mustn't be empty/null", 1);

        $this->realm = $realm ?? "default";
        $this->opaque = $opaque ?? hash(self::PHP_HASH_ALGORITHM, $this->realm);
        $this->userProvider = $userProvider;
    }

    // explained in the interface
    public function getCurrentUser(): ?user
    {
        return $this->currentUser;
    }

    // explained in the interface
    public function isLoggedIn(): bool
    {
        return false;
    }

    // further explanation in the interface
    /**
     * This should be called every time a script is executed, elsewise no meaningful return of isLoggedIn() or getCurrentUser() can be expected.
     * NOTE please exit script as soon as possible after receiving NOT 0 as return. If you don't no new Authentication message will be send.
     * You might want to put the error in the body of the return to the client in an preferred format. To understand this strange behavior read the documentation below.
     * 
     * Information about how the authentication is reached:
     * 
     * 1. You need an authentication so you initiate a login routine.
     * 2. The routine checks for credentials send by the client (if any), and processes them.
     *    In the best case the credentials are valid and 0 will be returned, if an error ocurred an value >0 will be returned, AND a new authentication header will be set.
     * 3. YOU check wether the function returned 0 (you proceed normally),
     *    or if the value was >0, in this case you will call exit() as soon as possible so the authentication headers can reach the client.
     * 
     * To put it in a nutshell, the authentication requires the request to be terminated (your job) so new authentication information can be requested by the client.
     * If you want this function to behave as if it wouldn't require a completely new request make sure that the new request of the client reaches the same point in the code but this time with
     * new (possible correct) authentication information.
     * 
     * The checks internally performed are listed below:
     * 1. The routine checks if an authentication is forced (else skip to step 2), if so set authentication headers, disable forced authentication, return with error,
     *    YOU must call exit() as soon as possible so the authentication headers can bes send to the client.
     * 2. Check if authentication information is provided, if so go to next step (3), else set auth. headers, and return error
     *    YOU must call exit() as soon as possible so the authentication headers can bes send to the client.
     * 3. Check if the authentication header returned by the client is complete, if so next step (4), else set auth. headers, and return error
     *    YOU must call exit() as soon as possible so the authentication headers can bes send to the client.
     * 4. Check if the user send by the client exists (identified by it's name) if so go to next step (5), else set auth. headers, and return error
     *    YOU must call exit() as soon as possible so the authentication headers can bes send to the client.
     * 5. Check if the password matches the user in the database, if so go to next step (6), else set auth. headers, and return error
     *    YOU must call exit() as soon as possible so the authentication headers can bes send to the client.
     * 6. Correct credentials were provided, the user is stored and can be requested with.
     */
    public function initiateLoginRoutine(): int
    {
        // check if authentication message should be forcefully send
        if ($this->forceNewCredentials) {
            // a forced authentication will be sent only once
            $this->forceNewCredentials = false;

            // send out a new authentication message
            $this->sendAuthMessage();

            // return an error stating that a new authentication was forcefully required and therefor the old credentials don't work any longer
            return self::ERROR_FORCED_AUTHENTICATION;
        }

        // check if authentication information is present
        if (empty($_SERVER["PHP_AUTH_DIGEST"])) {
            // if no authentication data is send (request authentication)
            $this->sendAuthMessage();

            // Text output if user is unwilling to authenticate
            // die($this->errorProvider::errorsToString(self::ERROR_DISMISSED_AUTHENTICATION));
            return self::ERROR_DISMISSED_AUTHENTICATION;
        }

        // --- authentication information is present, now decode it and check the validity ---

        // parse digest auth info
        $data = self::http_digest_parse($_SERVER["PHP_AUTH_DIGEST"]);

        // check if parse was successful
        if (!$data) {
            error_log("Incomplete authentication header");

            // return with error code
            header('HTTP/1.1 400 Bad Request');
            // die($this->errorProvider::errorsToString(self::ERROR_INVALID_RESPONSE));
            return self::ERROR_INVALID_RESPONSE;
        }

        // --- authentication information is complete ---

        // search for users with provided name in database
        $user = $this->userProvider->getUserByName($data["username"]);

        // check if username exist in database
        if ($user == null) {
            // send new authentication message
            $this->sendAuthMessage();

            // tell client that username doesn't exist
            // die($this->errorProvider::errorsToString(self::ERROR_NO_SUCH_USER));
            return self::ERROR_NO_SUCH_USER;
        }

        // --- there exists a user with the username provided in the authentication information ---

        // create a possible valid response
        $valid_response = self::generateValidDigestResponse(
            $user->{user::KEY_NAME},
            $user->{user::KEY_PASSWORD},
            $_SERVER["REQUEST_METHOD"],
            $this->realm,
            $data["uri"],
            $data["nonce"],
            $data["nc"],
            $data["cnonce"],
            $data["qop"]
        );

        // compare if response send by client matches the calculated response
        if ($data["response"] != $valid_response) {
            // send new authentication message
            self::sendAuthMessage();

            // tell client that password was incorrect
            // die($this->errorProvider::errorsToString(self::ERROR_INVALID_PASSWORD));
            return self::ERROR_INVALID_PASSWORD;
        }

        // --- responses match and user exists with provided password ---

        $this->isLoggedIn = true;
        $this->currentUser = $user;

        // return that a successful login ocurred
        return 0;
    }

    // further explanation in the interface
    /**
     * You ALWAYS need to call initiateLoginRoutine() after running logout(),
     * elsewise no new authentication header will be send and the client will reuse the old credentials (and also isn't notified
     * that new credentials are desired). Read in the documentation of initiateLoginRoutine() to get a better understanding of the problem.
     */
    public function logout(): void
    {
        // this might be dangerous since it might suggest an unreal behavior (see initiateLoginRoutine() for real behavior)
        $this->currentUser = null;
        $this->isLoggedIn = false;

        $this->forceNewCredentials = true;
    }

    /**
     * Sets new opaque
     * 
     * @param string $opaque New opaque
     */
    public function setOpaque(string $opaque): void
    {
        if ($opaque != null)
            $this->opaque = $opaque;
    }

    /**
     * function to send authentication message to client
     */
    protected function sendAuthMessage(): void
    {
        header("HTTP/1.1 401 Unauthorized");

        // generate random nonce
        // WARN requires php >7
        $nonce = base64_encode(random_bytes(self::NONCE_LENGTH));

        // send authentication request
        // NOTICE sha-256 is requested
        header("WWW-Authenticate: Digest algorithm=" . self::BROWSER_HASH_ALGORITHM . ",realm=\"" . $this->realm . "\",qop=\"auth\",nonce=\"" . $nonce . "\",opaque=\"" . $this->opaque . "\"");
    }

    /**
     * generates a string representing a valid response.
     * This string than can be compared to what the client send and if the don't math the entered password was invalid
     * 
     * @param string $username The username
     * @param string $password The password
     * @param string $request_method The method of the request (is provided by the server $_SERVER["REQUEST_METHOD"])
     * @param string $realm The realm used for authentication (is returned in the authentication header or can be calculated by oneself)
     * @param string $uri The uri of the response (is returned in the authentication header or can be calculated by oneself)
     * @param string $nonce The nonce used for authentication (is returned in the authentication header or can be calculated by oneself)
     * @param string $nc The nc used for authentication (is returned in the authentication header or can be calculated by oneself)
     * @param string $cnonce The cnonce used for authentication (is returned in the authentication header or can be calculated by oneself)
     * @param string $qop The qop used for authentication (is returned in the authentication header or can be calculated by oneself)
     * 
     * @return string Valid response (what the response should look like if username and password were correct)
     */
    protected static function generateValidDigestResponse(
        string $username,
        string $password,
        string $request_method,
        string $realm,
        string $uri,
        string $nonce,
        string $nc,
        string $cnonce,
        string $qop
    ): string {
        // create a possible valid response
        $A1 = hash("sha256", $username . ":" . $realm . ":" . $password);
        $A2 = hash("sha256", $request_method . ":" . $uri);
        $valid_response = hash("sha256", $A1 . ":" . $nonce . ":" . $nc . ':' . $cnonce . ":" . $qop . ":" . $A2);

        return $valid_response;
    }

    /**
     * function to parse the http auth header
     * 
     * @see https://www.php.net/manual/en/features.http-auth.php
     */
    protected static function http_digest_parse($txt)
    {
        // protect against missing data
        $needed_parts = array(
            "nonce" => 1,
            "nc" => 1,
            "cnonce" => 1,
            "qop" => 1,
            "username" => 1,
            "uri" => 1,
            "response" => 1
        );
        $data = array();
        $keys = implode("|", array_keys($needed_parts));

        preg_match_all('@(' . $keys . ')=(?:([\'"])([^\2]+?)\2|([^\s,]+))@', $txt, $matches, PREG_SET_ORDER);

        foreach ($matches as $m) {
            $data[$m[1]] = $m[3] ? $m[3] : $m[4];
            unset($needed_parts[$m[1]]);
        }

        return $needed_parts ? false : $data;
    }
}
