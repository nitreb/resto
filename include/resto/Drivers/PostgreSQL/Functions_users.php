<?php
/*
 * Copyright 2014 Jérôme Gasperi
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
 * RESTo PostgreSQL users functions
 */
class Functions_users {
    
    private $dbDriver = null;
    private $dbh = null;
    
    /**
     * Constructor
     * 
     * @param array $config
     * @param RestoCache $cache
     * @throws Exception
     */
    public function __construct($dbDriver) {
        $this->dbDriver = $dbDriver;
        $this->dbh = $dbDriver->dbh;
    }
    
    /**
     * Return encrypted user password
     * 
     * @param string $identifier : email
     * 
     * @throws Exception
     */
    public function getUserPassword($identifier) {
        $query = 'SELECT password FROM usermanagement.users WHERE email=\'' . pg_escape_string($identifier) . '\'';
        $results = $this->dbDriver->fetch($this->dbDriver->query($query));
        return count($results) === 1 ? $results[0]['password'] : null;
    }
        
    /**
     * Get user profile
     * 
     * @param string $identifier : can be email (or string) or integer (i.e. uid)
     * @param string $password : if set then profile is returned only if password is valid
     * @return array : this function should return array('userid' => -1, 'groupname' => 'unregistered')
     *                 if user is not found in database
     * @throws exception
     */
    public function getUserProfile($identifier, $password = null) {
        
        /*
         * Unregistered users
         */
        if (!isset($identifier) || !$identifier || $identifier === 'unregistered') {
            RestoLogUtil::httpError(404);
        }
        
        $query = 'SELECT userid, email, md5(email) as userhash, groupname, username, givenname, lastname, to_char(registrationdate, \'YYYY-MM-DD"T"HH24:MI:SS"Z"\'), country, organization, organizationcountry, flags, topics, activated, grantedvisibility, validatedby, to_char(validationdate, \'YYYY-MM-DD"T"HH24:MI:SS"Z"\') FROM usermanagement.users WHERE ' . $this->useridOrEmailFilter($identifier) . (isset($password) ? ' AND password=\'' . pg_escape_string(RestoUtil::encrypt($password)). '\'' : '');
        $results = $this->dbDriver->fetch($this->dbDriver->query($query));
        
        if (count($results) === 0) {
            RestoLogUtil::httpError(404);
        }
        
        $results[0]['activated'] = (integer) $results[0]['activated'];
        
        return $results[0];
        
    }
    
    /**
     * Get full profiles for all users
     * 
     * @return array : this function should return array('userid' => -1, 'groupname' => 'unregistered')
     *                 if user is not found in database
     * @throws exception
     */
    public function getUsersProfiles() {
        $results = $this->dbDriver->query('SELECT userid, email, md5(email) as userhash, groupname, username, givenname, lastname, to_char(registrationdate, \'YYYY-MM-DD"T"HH24:MI:SS"Z"\') as registrationdate, country, organization, organizationcountry, flags, topics, activated, grantedvisibility, validatedby, to_char(validationdate, \'YYYY-MM-DD"T"HH24:MI:SS"Z"\') as validationdate FROM usermanagement.users');
        $profiles = array();
        while ($profile = pg_fetch_assoc($results)) {
            $profiles[] = array(
                'userid' => $profile['userid'],
                'email' => $profile['email'],
                'userhash' => $profile['userhash'],
                'groupname' => $profile['groupname'],
                'username' => $profile['username'],
                'givenname' => $profile['givenname'],
                'lastname' => $profile['lastname'],
                'registrationdate' => $profile['registrationdate'],
                'country' => $profile['country'],
                'organization' => $profile['organization'],
                'organizationcountry' => $profile['organizationcountry'],
                'flags' => $profile['flags'],
                'topics' => $profile['topics'],
                'activated' => (integer) $profile['activated'],
                'grantedvisibility' => $profile['grantedvisibility'],
                'validatedby' => $profile['validatedby'],
                'validationdate' => $profile['validationdate']
            );
        }
        
        return $profiles;
        
    }
    
    /**
     * Check if user identified by $identifier exists within database
     * 
     * @param string $email - user email
     * 
     * @return boolean
     * @throws Exception
     */
    public function userExists($email) {
        $query = 'SELECT 1 FROM usermanagement.users WHERE email=\'' . pg_escape_string($email) . '\'';
        $results = $this->dbDriver->fetch($this->dbDriver->query(($query)));
        return !empty($results);
    }
    
    /**
     * Save user profile to database i.e. create new entry if user does not exist
     * 
     * @param array $profile
     * @return array (userid, activationcode)
     * @throws exception
     */
    public function storeUserProfile($profile) {
       
        if (!is_array($profile) || !isset($profile['email'])) {
            RestoLogUtil::httpError(500, 'Cannot save user profile - invalid user identifier');
        }
        if ($this->userExists($profile['email'])) {
            RestoLogUtil::httpError(500, 'Cannot save user profile - user already exist');
        }
        
        $email = trim(strtolower($profile['email']));
        $values = "'" . pg_escape_string($email) . "',";
        $values .= "'" . (isset($profile['password']) ? RestoUtil::encrypt($profile['password']) : str_repeat('*', 40)) . "',";
        $values .= "'" . (isset($profile['groupname']) ? pg_escape_string($profile['groupname']) : 'default') . "',";
        
        foreach (array_values(array('username', 'givenname', 'lastname', 'country', 'organization', 'topics', 'grantedvisibility', 'organizationcountry', 'flags')) as $field) {
            $values .= (isset($profile[$field]) ? "'". $profile[$field] . "'" : 'NULL') . ",";
        }
        $values .= "'" . pg_escape_string(RestoUtil::encrypt($email . microtime())) . "',";
        $values .= $profile['activated'] . ',now()';
        
        $results = $this->dbDriver->query('INSERT INTO usermanagement.users (email,password,groupname,username,givenname,lastname,country,organization,organizationcountry,flags,topics,grantedvisibility,activationcode,activated,registrationdate) VALUES (' . $values . ') RETURNING userid, activationcode');
        return pg_fetch_array($results);
    }
    
    /**
     * Update user profile to database
     * 
     * @param array $profile
     * @return integer (userid)
     * @throws exception
     */
    public function updateUserProfile($profile) {
       
        if (!is_array($profile) || !isset($profile['email'])) {
            RestoLogUtil::httpError(500, 'Cannot update user profile - invalid user identifier');
        }

        /*
         * The following parameters cannot be updated :
         *   - email
         *   - userid 
         *   - activationcode
         *   - registrationdate
         */
        $values = array();
        if (isset($profile['password'])) {
            $values[] = 'password=\'' . RestoUtil::encrypt($profile['password']) . '\'';
        }
        if (isset($profile['activated'])) {
            $values[] = 'activated=' . $profile['activated'];
        }
        foreach (array_values(array('username', 'givenname', 'lastname', 'groupname', 'country', 'organization', 'topics', 'organizationcountry', 'flags')) as $field) {
            if (isset($profile[$field])) {
                $values[] = $field . '=\'' . pg_escape_string($profile[$field]) . '\'';
            }
        }
        
        $results = array();
        if (count($values) > 0) {
            $results = $this->dbDriver->fetch($this->dbDriver->query('UPDATE usermanagement.users SET ' . join(',', $values) . ' WHERE email=\'' . pg_escape_string(trim(strtolower($profile['email']))) .'\' RETURNING userid'));
        }
        
        return count($results) === 1 ? $results[0]['userid'] : null;
        
    }

    /**
     * Add granted visibility to user $userid
     * @param $userid
     * @param $visibility
     * @return null
     * @throws Exception
     */
    public function storeVisibility($userid, $visibility) {
        return $this->storeOrDeleteVisibility('store', $userid, $visibility);
    }
    
    /**
     * Remove granted visibility to user $userid
     * @param $userid
     * @param $visibility
     * @return null
     * @throws Exception
     */
    public function deleteVisibility($userid, $visibility) {
        return $this->storeOrDeleteVisibility('delete', $userid, $visibility);
    }

    /**
     * Return true if token is revoked
     * 
     * @param string $token
     */
    public function isTokenRevoked($token) {
        $query = 'SELECT 1 FROM usermanagement.revokedtokens WHERE token= \'' . pg_escape_string($token) . '\'';
        $results = $this->dbDriver->fetch($this->dbDriver->query(($query)));
        return !empty($results);
    }

    /**
     * Revoke token
     * 
     * @param string $token
     */
    public function revokeToken($token) {
        if (isset($token) && !$this->isTokenRevoked($token)) {
            $this->dbDriver->query('INSERT INTO usermanagement.revokedtokens (token) VALUES(\'' . pg_escape_string($token) . '\')');
        }
        return true;
    }

    /**
     * Check if user signed collection license
     * 
     * @param string $identifier
     * @param string $collectionName
     * 
     * @return boolean
     */
    public function isLicenseSigned($identifier, $collectionName) {
        $query = 'SELECT 1 FROM usermanagement.signatures WHERE email= \'' . pg_escape_string($identifier) . '\' AND collection= \'' . pg_escape_string($collectionName) . '\'';
        $results = $this->dbDriver->fetch($this->dbDriver->query(($query)));
        return !empty($results);
    }
    
    /**
     * Sign license for collection collectionName
     * 
     * @param string $identifier : user identifier 
     * @param string $collectionName
     * @return boolean
     * @throws Exception
     */
    public function signLicense($identifier, $collectionName) {
        
        if (!$this->dbDriver->check(RestoDatabaseDriver::COLLECTION, array(
            'collectionName' => $collectionName
        ))) {
            RestoLogUtil::httpError(500, 'Cannot sign license');
        }
        $results = $this->dbDriver->query('SELECT email FROM usermanagement.signatures WHERE email=\'' . pg_escape_string($identifier) . '\' AND collection=\'' . pg_escape_string($collectionName) . '\'');
        if (pg_fetch_assoc($results)) {
            $this->dbDriver->query('UPDATE usermanagement.signatures SET signdate=now() WHERE email=\'' . pg_escape_string($identifier) . '\' AND collection=\'' . pg_escape_string($collectionName) . '\'');
        }
        else {
            $this->dbDriver->query('INSERT INTO usermanagement.signatures (email, collection, signdate) VALUES (\'' . pg_escape_string($identifier) . '\',\'' . pg_escape_string($collectionName) . '\',now())');
        }
        return true;
    }
    
    /**
     * Activate user
     * 
     * @param string $userid : can be userid or base64(email)
     * @param string $activationcode
     * 
     * @throws Exception
     */
    public function activateUser($userid, $activationcode = null) {
        $query = 'UPDATE usermanagement.users SET activated=1 WHERE userid=\'' . pg_escape_string($userid) . '\'' . (isset($activationcode) ? ' AND activationcode=\'' . pg_escape_string($activationcode) . '\'' :'') . ' RETURNING userid';
        $results = $this->dbDriver->fetch($this->dbDriver->query($query));
        if (count($results) === 1) {
            return true;
        }
        return false;
    }
    
    /**
     * Deactivate user
     * 
     * @param string $userid
     * @throws Exception
     */
    public function deactivateUser($userid) {
        $query = 'UPDATE usermanagement.users SET activated=0 WHERE userid=\'' . pg_escape_string($userid) . '\'';
        $results = $this->dbDriver->fetch($this->dbDriver->query($query));
        if (count($results) === 1) {
            return true;
        }
        return false;
    }
    
    /**
     * Return filter on user
     * 
     * @param string $identifier
     */
    private function useridOrEmailFilter($identifier) {
        return ctype_digit($identifier) ? 'userid=' . $identifier : 'email=\'' . pg_escape_string($identifier) . '\'';
    }
    
    /**
     * Add granted visibility to user $userid
     * 
     * @param string $storeOrDelete
     * @param integer $userid
     * @param string $visibility
     * @return null
     * @throws Exception
     */
    private function storeOrDeleteVisibility($storeOrDelete, $userid, $visibility) {
        
        if (!isset($userid)) {
            RestoLogUtil::httpError(500, 'Cannot ' . $storeOrDelete . ' granted visibility - invalid user identifier:'. $userid);
        }
        if (!isset($visibility)) {
            RestoLogUtil::httpError(500, 'Cannot ' . $storeOrDelete . ' granted visibility - invalid visibility :'. $visibility);
        }

        $profile = $this->getUserProfile($userid);
        if (!isset($profile)) {
            RestoLogUtil::httpError(500, 'Cannot ' . $storeOrDelete . ' granted visibility - user profile not found for :'. $userid);
        }

        $grantedvisibility = $profile['grantedvisibility'];
        
        /*
         * Explode existing grantedvisibility into an associative array
         */
        $visibilities = isset($grantedvisibility) ? array_flip(explode(',', $grantedvisibility)) : array();
       
        /*
         * Explode new visibilities (i.e. input $visibility)
         */
        $newVisibilities = array();
        $rawNewVisibilities = explode(',', $visibility);
        for ($i = 0, $ii = count($rawNewVisibilities); $i < $ii; $i++) {
            if ($rawNewVisibilities[$i] !== '') {
                $newVisibilities[$rawNewVisibilities[$i]] = 1;
            }
        }
        
        /*
         * Store new visibilities = merge with previous
         */
        if ($storeOrDelete === 'store') {
            $visibilities = array_keys(array_merge($visibilities, $newVisibilities));
        }
        else {
            foreach (array_keys($newVisibilities) as $key) {
                unset($visibilities[$key]);
            }
            $visibilities = array_keys($visibilities);
        }
        
        /*
         * Update user profile
         */
        $results = count($visibilities) > 0 ? implode(',', $visibilities) : null;
        $this->dbDriver->fetch($this->dbDriver->query('UPDATE usermanagement.users SET grantedvisibility=' . (isset($results) ? '\'' . pg_escape_string($results)  . '\'' : 'NULL') . ' WHERE userid=\''. $userid .'\''));
        
        return $results;
        
    }
    
}
