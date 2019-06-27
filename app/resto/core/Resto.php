<?php
/*
 * Copyright 2018 Jérôme Gasperi
 *
 * Licensed under the Apache License, version 2.0 (the "License");
 * You may not use this file except in compliance with the License.
 * You may obtain a copy of the License at:
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
 * License for the specific language governing permissions and limitations
 * under the License.
 */

/**
 *  resto
 *
 *  This class should be instantiate with
 *
 *      $resto = new Resto();
 *
 * Access to resource
 * ==================
 *
 * General url template
 * --------------------
 *
 *      http(s)://host/resto/collections/{collection}.json?key1=value1&key2=value2&...
 *      \__________________/\_______________________/\____/\___________________________/
 *            baseUrl                   path         format          query
 *
 *      Where :
 *
 *          {collection} is the name of the collection (e.g. 'Charter', 'SPIRIT', etc.)
 *
 * Query
 * -----
 *
 *   Query parameters are described within OpenSearch Description file
 *
 *   Special query parameters can be used to modify the query. These parameters are not specified
 *   within the OpenSearch Description file. Below is the list of Special query parameters
 *
 *
 *    | Query parameter    |      Type      | Description
 *    |______________________________________________________________________________________________
 *    | _pretty            |     boolean    | (For JSON output only) true to return pretty print JSON
 *    
 *
 * Activities code
 * ---------------
 *
 *    actionid = (100) + action_code + 'target_code'
 *    (100) is added for symetry (i.e. 110 is "WAS ADDED")
 *
 *    action_code:
 *
 *       10 => ADD
 *       20 => REMOVE
 *       30 => LIKE
 *       40 => UNLIKE
 *       50 => FOLLOW
 *       60 => UNFOLLOW
 *       70 => SCHEDULE
 *
 *    target_code:
 *
 *       1 => FEATURE
 *       2 => USER
 *       3 => COMMENT
 *       4 => ANNOTATION
 *
 *
 * Returned error
 * --------------
 *
 *   - HTTP 400 'Bad Request' for invalid request
 *   - HTTP 403 'Forbiden' when accessing protected resource/service with invalid credentials
 *   - HTTP 404 'Not Found' when accessing non existing resource/service
 *   - HTTP 405 'Method Not Allowed' when accessing existing resource/service with unallowed HTTP method
 *   - HTTP 412 'Precondition failed' when existing but non activated user try to connect
 *   - HTTP 500 'Internal Server Error' for technical errors (i.e. database connection error, etc.)
 *
 * Open API
 * ========
 *
 * @OA\OpenApi(
 *   @OA\Info(
 *       title=API_INFO_TITLE,
 *       description=API_INFO_DESCRIPTION,
 *       version=API_VERSION,
 *       @OA\Contact(
 *           email=API_INFO_CONTACT_EMAIL
 *       )
 *   ),
 *   @OA\Server(
 *       description=API_HOST_DESCRIPTION,
 *       url=API_HOST_URL
 *   )
 *  )
 */

class Resto
{

    /*
     * Default routes
     */
    private $defaultRoutes = array(

        // Hello
        array('GET', '/', false, 'ServicesAPI::hello'),                                                    // List users profiles

        // API for users
        array('GET', '/users', true, 'UsersAPI::getUsersProfiles'),                                       // List users profiles
        array('GET', '/users/{userid}', true, 'UsersAPI::getUserProfile'),                                 // Show user profile
        array('GET', '/users/{userid}/groups', true, 'UsersAPI::getUserGroups'),                           // Show user groups
        array('GET', '/users/{userid}/logs', true, 'UsersAPI::getUserLogs'),                               // Show user logs
        array('GET', '/users/{userid}/rights', true, 'UsersAPI::getUserRights'),                           // Show user rights
        array('GET', '/users/{userid}/rights/{collectionName}', true, 'UsersAPI::getUserRights'),           // Show user rights for :collectionName
        array('GET', '/users/{userid}/rights/{collectionName}/{featureId}', true, 'UsersAPI::getUserRights'), // Show user rights for :featureId
        array('GET', '/users/{userid}/signatures', true, 'UsersAPI::getUserSignatures'),                   // Show user signatures
        array('POST', '/users', false, 'UsersAPI::createUser'),                                            // Create user
        array('PUT', '/users/{userid}', true, 'UsersAPI::updateUserProfile'),                              // Update :userid profile

        // API for groups
        array('GET', '/groups', true, 'GroupsAPI::getGroups'),                                            // List users profiles
        array('GET', '/groups/{id}', true, 'GroupsAPI::getGroup'),                                        // Get group
        array('POST', '/groups', true, 'GroupsAPI::createGroup'),                                        // Create group
        array('DELETE', '/groups/{id}', true, 'GroupsAPI::deleteGroup'),                                    // Delete group

        // API for collections
        array('GET', '/collections', false, 'CollectionsAPI::getCollections'),                            // List all collections
        array('GET', '/collections/{collectionName}', false, 'CollectionsAPI::getCollection'),             // Get :collectionName description
        array('GET', '/collections/{collectionName}/features', false, 'FeaturesAPI::getFeaturesInCollection'),              // Search features in :collectionName
        array('POST', '/collections', true, 'CollectionsAPI::createCollection'),                           // Create collection
        array('POST', '/collections/{collectionName}', true, 'CollectionsAPI::insertFeature'),              // Insert feature
        array('PUT', '/collections/{collectionName}', true, 'CollectionsAPI::updateCollection'),           // Update :collectionName
        array('DELETE', '/collections/{collectionName}', true, 'CollectionsAPI::deleteCollection'),           // Delete :collectionName

        // API for features
        array('GET', '/features', false, 'FeaturesAPI::getFeatures'),                           // Get feature :featureId
        array('GET', '/features/{featureId}', false, 'FeaturesAPI::getFeature'),                         // Get feature :featureId
        array('GET', '/features/{featureId}/download', false, 'FeaturesAPI::downloadFeature'),             // Download feature :featureId
        array('GET', '/features/{featureId}/view', false, 'FeaturesAPI::viewFeature'),                     // View service for feature :featureId (i.e. wms/wmts/etc.)
        array('PUT', '/features/{featureId}', true, 'FeaturesAPI::updateFeature'),                         // Update feature :featureId
        array('PUT', '/features/{featureId}/{property}', true, 'FeaturesAPI::updateFeatureProperty'),
        array('DELETE', '/features/{featureId}', true, 'FeaturesAPI::deleteFeature'),                         // Delete :featureId

        // API for licenses
        array('GET', '/licenses', true, 'LicensesAPI::getLicenses'),                                      // List all licenses
        array('GET', '/licenses/{licenseId}', true, 'LicensesAPI::getLicenses'),                           // Get :licenseId license description
        array('POST', '/licenses/{licenseId}/sign', true, 'LicensesAPI::signLicense'),                      // Sign :licenseId

        // API for authentication (token based)
        array('GET', '/auth', true, 'AuthAPI::getToken'),                                                 // Return a valid auth token
        array('GET', '/auth/check/{token}', false, 'AuthAPI::checkToken'),                                 // Check auth token validity
        array('DELETE', '/auth/revoke/{token}', true, 'AuthAPI::revokeToken'),                                // Revoke auth token
        array('PUT', '/auth/activate/{token}', false, 'AuthAPI::activateUser'),                            // Activate owner of the token

        // API for services
        array('GET', '/services/osdd', false, 'ServicesAPI::getOSDD'),                                    // Opensearch service description at collections level
        array('GET', '/services/osdd/{collectionName}', false, 'ServicesAPI::getOSDDForCollection'),                    // Opensearch service description for products on {collection}
        array('POST', '/services/activation/send', false, 'ServicesAPI::sendActivationLink'),              // Send activation link
        array('POST', '/services/password/forgot', false, 'ServicesAPI::forgotPassword'),                  // Send reset password link
        array('POST', '/services/password/reset', false, 'ServicesAPI::resetPassword'),                    // Reset password
    );

    /* ============================================================
     *              NEVER EVER TOUCH THESE VALUES
     * ============================================================*/

    // resto version
    const VERSION = '5.0.0';

    // PostgreSQL max value for integer
    const INT_MAX_VALUE = 2147483647;

    // Group identifier for administrator group
    const GROUP_ADMIN_ID = 0;

    // Group identifier for default group (every user is in default group)
    const GROUP_DEFAULT_ID = 100;

    // Separator for hashtags identifiers - should be the same as iTag
    const TAG_SEPARATOR = ':';

    // Separator for paths in RestoModel->inputMapping()
    const MAPPING_PATH_SEPARATOR = '.';

    /* ============================================================ */

    /*
     * RestoContext
     */
    public $context;

    /*
     * RestoUser
     */
    public $user;

    /*
     * Time measurement
     */
    private $startTime;

    /*
     * CORS white list
     */
    private $corsWhiteList = array();

    /**
     * Constructor
     *
     * @param array $config
     *
     */
    public function __construct($config = array())
    {

        // Initialize start of processing
        $this->startTime = microtime(true);
        
        try {

            /*
             * Set global debug mode
             */
            if (isset($config['debug'])) {
                RestoLogUtil::$debug = $config['debug'];
            }

            /*
             * Set white list for CORS
             */
            if (isset($config['corsWhiteList'])) {
                $this->corsWhiteList = $config['corsWhiteList'];
            }

            /*
             * Context
             */
            $this->context = new RestoContext($config);

            /*
             * Authenticate user
             */
            $this->authenticate();

            /*
             * Initialize router
             */
            $this->router = new RestoRouter($this->context, $this->user);

            /*
             * Add default routes
             */
            $this->router->addRoutes(isset($config['defaultRoutes']) && count($config['defaultRoutes']) > 0 ? $config['defaultRoutes'] : $this->defaultRoutes);

            /*
             * Add add-ons routes
             */
            foreach (array_keys($this->context->addons) as $addonName) {
                if (isset($this->context->addons[$addonName]['routes'])) {
                    $this->router->addRoutes($this->context->addons[$addonName]['routes']);
                }
            }
            
            /*
             * Process route
             */
            $response = $this->getResponse();
            
        } catch (Exception $e) {

            /*
             * Output in error - format output as JSON in the following
             */
            $this->context->outputFormat = 'json';

            /*
             * Code under 500 is an HTTP code - otherwise it is a resto error code
             * All resto error codes lead to HTTP 200 error code
             */
            $responseStatus = $e->getCode();
            $response = json_encode(array('ErrorMessage' => $e->getMessage(), 'ErrorCode' => $e->getCode()), JSON_UNESCAPED_SLASHES);
        }

        $this->answer($response ?? null, $responseStatus ?? 200);
    }

    /**
     * Initialize route from HTTP method and get response from server
     */
    private function getResponse()
    {
        switch ($this->context->method) {
            case 'GET':
            case 'POST':
            case 'PUT':
            case 'DELETE':
                $method = $this->context->method;
                break;

            case 'HEAD':
                $method = 'GET';
                break;

            case 'OPTIONS':
                return $this->setCORSHeaders();

            default:
                return RestoLogUtil::httpError(404);
        }

        $response = $this->router->process($method, $this->context->path, $this->context->query);

        return isset($response) ? $this->format($response) : null;
    }

    /**
     * Stream HTTP result and exit
     */
    private function answer($response, $responseStatus)
    {
        if (isset($response)) {

            /*
             * HTTP 1.1 headers
             */
            header('HTTP/1.1 ' . $responseStatus . ' ' . (RestoLogUtil::$codes[$responseStatus] ?? RestoLogUtil::$codes[200]));
            header('Pragma: no-cache');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Expires: Fri, 1 Jan 2010 00:00:00 GMT');
            header('Server-processing-time: ' . (microtime(true) - $this->startTime));
            header('Content-Type: ' . RestoUtil::$contentTypes[$this->context->outputFormat]);

            /*
             * Set headers including cross-origin resource sharing (CORS)
             * http://en.wikipedia.org/wiki/Cross-origin_resource_sharing
             */
            $this->setCORSHeaders();

            /*
             * Stream data unless HTTP HEAD is requested
             */
            if ($this->context == null || $this->context->method !== 'HEAD') {
                echo $response;
            }

            /*
             * Store query
             */
            try {
                $this->storeQuery();
            } catch (Exception $e) { }

            /*
             * Close database handler
             *
             * [DEPRECATED] This is unecessary. Code kept in comment for discussion
             * (see https://www.postgresql.org/message-id/20633C46-A536-11D9-8FA8-000A95B03262%40pgedit.com)
             *
            if (isset($this->context) && isset($this->context->dbDriver)) {
                $this->context->dbDriver->closeDbh();
            }
            */
        }
    }

    /**
     * Authenticate and set user accordingly
     *
     * Various authentication method
     *
     *   - HTTP user:password (i.e. http authorization mechanism)
     *   - Single Sign On request with oAuth2
     *
     *
     *  @OA\SecurityScheme(
     *      type="http",
     *      in="header",
     *      name="bearer",
     *      scheme="bearer",
     *      bearerFormat="JWT",
     *      securityScheme="bearerAuth",
     *      description="Access token in HTTP header as JWT or rJWT (_resto JWT_) - this is the default"
     *  )
     *
     *  @OA\SecurityScheme(
     *      type="http",
     *      in="header",
     *      name="basic",
     *      scheme="basic",
     *      securityScheme="basicAuth",
     *      description="Basic authentication in HTTP header - should be used first to get a valid rJWT token"
     *  )
     *
     *  @OA\SecurityScheme(
     *      type="apiKey",
     *      in="query",
     *      name="_bearer",
     *      securityScheme="queryAuth",
     *      description="Access token in query as preseance over token in HTTP header"
     *  )
     *
     */
    private function authenticate()
    {
        $authRequested = false;

        /*
         * Authentication through token in url
         */
        if (isset($this->context->query['_bearer'])) {
            $authRequested = true;
            $this->authenticateBearer($this->context->query['_bearer']);
            unset($this->context->query['_bearer']);
        }
        /*
         * ...or from headers
         */ else {
            $authRequested = $this->headersAuthenticate();
        }

        /*
         * If we land here - set an unregistered user
         */
        if (!isset($this->user)) {
            $this->user = new RestoUser(null, $this->context, false);
        }

        /*
         * Authentication headers were present but authentication leades to unauthentified user => security error
         */
        if ($authRequested && !isset($this->user->profile['id'])) {
            return RestoLogUtil::httpError(401);
        }

        return true;
    }

    /**
     * Get authentication info from http headers
     */
    private function headersAuthenticate()
    {
        $httpAuth = filter_input(INPUT_SERVER, 'HTTP_AUTHORIZATION', FILTER_SANITIZE_STRING);
        $rhttpAuth = filter_input(INPUT_SERVER, 'REDIRECT_HTTP_AUTHORIZATION', FILTER_SANITIZE_STRING);
        $authorization = !empty($httpAuth) ? $httpAuth : (!empty($rhttpAuth) ? $rhttpAuth : null);
        if (isset($authorization)) {
            list($method, $token) = explode(' ', $authorization, 2);
            switch ($method) {
                case 'Basic':
                    $this->authenticateBasic($token);
                    break;
                case 'Bearer':
                    $this->authenticateBearer($token);
                    break;
                default:
                    break;
            }
            return true;
        }
        return false;
    }

    /**
     * Authenticate user from Basic authentication
     * (i.e. HTTP user:password)
     *
     * @param string $token
     */
    private function authenticateBasic($token)
    {
        list($username, $password) = explode(':', base64_decode($token), 2);
        if (!empty($username) && !empty($password) && (bool)preg_match('//u', $username) && (bool)preg_match('//u', $password) && strpos($username, '\'') === false) {
            $this->user = new RestoUser(array(
                'email' => strtolower($username),
                'password' => $password
            ), $this->context, true);
        }
    }

    /**
     * Authenticate user from Bearer authentication
     * (i.e. Single Sign On request with oAuth2)
     *
     * Assume either a JSON Web Token encoded by resto or a token generated by an SSO issuer (e.g. google)
     *
     * @param string $token
     */
    private function authenticateBearer($token)
    {
        try {

            /*
             * If issuer_id is specified in the request then assumes a third party token.
             * In this case, transform this third party token into a resto token
             */
            if (isset($this->context->query['issuerId']) && isset($this->context->addons['Auth'])) {
                $auth = new Auth($this->context, null);
                $token = $auth->getProfileToken($this->context->query['issuerId'], $token);
            }

            /*
             * Get user from JWT payload if valid
             */
            $userid = $this->getIdFromBearer($token);

            if (isset($userid)) {
                $this->user = new RestoUser(array('id' => $userid), $this->context, false);
                $this->user->token = $token;
            }
        } catch (Exception $ex) {
            return false;
        }

        return true;
    }

    /**
     * Check if token is not revoked
     * [PERFO WISE] only do this for long time token i.e. > 7 days
     *
     * @param array $payloadObject JWT payload
     */
    private function getIdFromBearer($token)
    {
        $payloadObject = $this->context->decodeJWT($token);

        // Unvalid token => no auth
        if (!isset($payloadObject) || !isset($payloadObject['sub'])) {
            return null;
        }

        // Missing times in token => no auth
        if (!isset($payloadObject['iat']) || !isset($payloadObject['exp'])) {
            return null;
        }

        // Valid token but too old => no auth
        if ($payloadObject['exp'] - $payloadObject['iat'] <= 0) {
            return null;
        }

        // Token is valid but old - check revokation
        if ($payloadObject['exp'] - $payloadObject['iat'] > 604800) {
            if ((new GeneralFunctions($this->context->dbDriver))->isTokenRevoked($token)) {
                return null;
            }
        }

        return $payloadObject['sub'];
    }

    /**
     * Call one of the output method from $object (i.e. toJSON(), toATOM(), etc.)
     *
     * @param object $object
     * @throws Exception
     */
    private function format($object)
    {

        /*
         * Case 0 - Object is null
         */
        if (!isset($object)) {
            return RestoLogUtil::httpError(400, 'Empty object');
        }

        $pretty = isset($this->context->query['_pretty']) ? filter_var($this->context->query['_pretty'], FILTER_VALIDATE_BOOLEAN) : false;

        /*
         * Case 1 - Object is an array
         * (Only JSON is supported for arrays)
         */
        if (is_array($object)) {
            $this->context->outputFormat = 'json';
            return json_encode($object, $pretty ? JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES : JSON_UNESCAPED_SLASHES);
        }

        /*
         * Case 2 - Object is an object
         */ elseif (is_object($object)) {
            $methodName = 'to' . strtoupper($this->context->outputFormat);
            if (method_exists(get_class($object), $methodName)) {
                return $this->context->outputFormat === 'json' ? $object->$methodName($pretty) : $object->$methodName();
            }
            return RestoLogUtil::httpError(404);
        }

        /*
         * Unknown stuff
         */
        return RestoLogUtil::httpError(400, 'Invalid object');
    }

    /**
     * Set CORS headers (HTTP OPTIONS request)
     */
    private function setCORSHeaders()
    {

        /*
         * Only set access to known servers
         */
        $httpOrigin = filter_input(INPUT_SERVER, 'HTTP_ORIGIN', FILTER_SANITIZE_STRING);
        if (isset($httpOrigin) && $this->corsIsAllowed($httpOrigin)) {
            header('Access-Control-Allow-Origin: ' . $httpOrigin);
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Max-Age: 3600');
        }

        /*
         * Control header are received during OPTIONS requests
         */
        $httpRequestMethod = filter_input(INPUT_SERVER, 'HTTP_ACCESS_CONTROL_REQUEST_METHOD', FILTER_SANITIZE_STRING);
        if (isset($httpRequestMethod)) {
            header('Access-Control-Allow-Methods: GET, POST, DELETE, PUT, OPTIONS');
        }

        $httpRequestHeaders = filter_input(INPUT_SERVER, 'HTTP_ACCESS_CONTROL_REQUEST_HEADERS', FILTER_SANITIZE_STRING);
        if (isset($httpRequestHeaders)) {
            header('Access-Control-Allow-Headers: ' . $httpRequestHeaders);
        }

        return null;
    }

    /**
     * Return true if $httpOrigin is allowed to do CORS
     * If corsWhiteList is empty, then every $httpOrigin is allowed.
     * Otherwise only origin in white list are allowed
     *
     * @param {String} $httpOrigin
     */
    private function corsIsAllowed($httpOrigin)
    {

        /*
         * No white list => all allowed
         */
        if (!isset($this->corsWhiteList) || count($this->corsWhiteList) === 0) {
            return true;
        }

        /*
         * Nasty hack for WKWebView and iOS setting a HTTP_ORIGIN null
         * Will remove it once corrected by Telerik
         * (https://github.com/Telerik-Verified-Plugins/WKWebView/issues/59)
         */
        $toCheck = 'null';
        $url = explode('//', $httpOrigin);
        if (isset($url[1])) {
            $toCheck = explode(':', $url[1])[0];
        }
        for ($i = count($this->corsWhiteList); $i--;) {
            if ($this->corsWhiteList[$i] === $toCheck) {
                return true;
            }
        }

        return false;
    }

    /**
     * Store query
     */
    private function storeQuery()
    {

        if (!$this->context->core['storeQuery'] || !isset($this->user)) {
            return false;
        }

        return (new GeneralFunctions($this->context->dbDriver))->storeQuery($this->user->profile['id'], array(
            'path' => $this->context->path,
            'query' => RestoUtil::kvpsToQueryString($this->context->query),
            'method' => $this->context->method
        ));
    }
}
