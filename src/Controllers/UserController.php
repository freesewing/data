<?php
/** Freesewing\Data\Controllers\UserController class */
namespace Freesewing\Data\Controllers;

use \Freesewing\Data\Data\User as User;
use \Freesewing\Data\Tools\Utilities as Utilities;

/**
 * Holds data for a user.
 *
 * @author Joost De Cock <joost@decock.org>
 * @copyright 2017 Joost De Cock
 * @license http://opensource.org/licenses/GPL-3.0 GNU General Public License, Version 3
 */
class UserController 
{
    protected $container;

    // constructor receives container instance
    public function __construct(\Slim\Container $container) {
        $this->container = $container;
    }

    // Anonymous calls

    /** User signup
     *
     * Handles POST requests to /user/signup 
     * Expects email and password in request params
     */
    public function signup($request, $response, $args) 
    {
        // Handle request data 
        $in = new \stdClass();
        $in->email = Utilities::scrub($request, 'signup-email');
        $in->password = Utilities::scrub($request, 'signup-password');
        
        // Get a logger instance from the container
        $logger = $this->container->get('logger');
        $logger->info("Signup request from: ".$in->email);

        // Don't continue if we don't have the required input
        if($in->email === false || $in->password === false || $in->password == '') {
            return Utilities::prepResponse($response, [
                'result' => 'error', 
                'reason' => 'invalid_input', 
                'message' => 'generic/error',
            ], 400, $this->container['settings']['app']['origin']);
        }
        
        // Get a user instance from the container
        $user = clone $this->container->get('User');
        $user->loadFromEmail($in->email);

        // Don't continue if this user already exists
        if ($user->getId() != '') { 
            $logger->info("Signup rejected: ".$in->email." already has an account");
            
            return Utilities::prepResponse($response, [
                'result' => 'error', 
                'reason' => 'account_exists', 
                'message' => 'signup/account-exists',
            ], 400, $this->container['settings']['app']['origin']);
        } 
        
        // Create new user
        $user->create($in->email, $in->password);

        // Send email 
        $mailKit = $this->container->get('MailKit');
        $mailKit->signup($user);
        $logger->info("Activation email sent to: ".$in->email);
        $logger->info("Signup: ".$in->email." is user ".$user->getId());

        return Utilities::prepResponse($response, [
            'result' => 'ok', 
            'reason' => 'signup_complete', 
            'message' => 'signup/success',
        ], 200, $this->container['settings']['app']['origin']);
    }
    
    /** User activation */
    public function activate($request, $response, $args) {

        // Handle request data 
        $activation_data = [
            'handle' => filter_var($args['handle'], FILTER_SANITIZE_STRING),
            'token' => filter_var($args['token'], FILTER_SANITIZE_STRING),
        ];

        // Get a logger instance from the container
        $logger = $this->container->get('logger');
        
        $user = clone $this->container->get('User');
        $user->loadFromHandle($activation_data['handle']);

        // Does the user exist?
        if ($user->getId() == '') { 
            $logger->info("Activation rejected: User handle ".$activation_data['handle']." does not exist");
        
            return Utilities::prepResponse($response, [
                'result' => 'error', 
                'reason' => 'no_such_account', 
                'message' => 'activation/no-such-account'
            ], 404, $this->container['settings']['app']['origin']);
        }

        // Is the user blocked? 
        if($user->getStatus() === 'blocked') {
            $logger->info('Activation rejected: User '.$user->getId().' is blocked');
            
            return Utilities::prepResponse($response, [
                'result' => 'error', 
                'reason' => 'account_blocked', 
                'message' => 'account/blocked'
            ], 400, $this->container['settings']['app']['origin']);
        }

        // Is there a token mismatch? 
        if($activation_data['token'] != $user->getActivationToken()) {
            $logger->info("Activation rejected: Token mismatch for user ".$user->getId());
            
            return Utilities::prepResponse($response, [
                'result' => 'error', 
                'reason' => 'token_mismatch', 
                'message' => 'activation/token-mismatch'
            ], 400, $this->container['settings']['app']['origin']);
        }

        // Get the token kit from the container
        $TokenKit = $this->container->get('TokenKit');
        
        // Activate user
        $user->setStatus('active');

        // Login user
        $user->setLogin();
        $user->save();
        
        // Log
        $logger->info("Activation: User ".$user->getId()." is now active");
        $logger->info("Login: User ".$user->getId())." auto-login upon activation";
        
        return Utilities::prepResponse($response, [
            'result' => 'ok',
            'reason' => 'signup_complete', 
            'message' => 'login/success',
            'token' => $TokenKit->create($user->getId()),
            'userid' => $user->getId(),
            'email' => $user->getEmail(),
            'username' => $user->getUsername(),
        ], 200, $this->container['settings']['app']['origin']);
    }
    
    /** Resend actication email
     *
     * Handles POST requests to /user/resend 
     * Expects email in request params
     */
    public function resend($request, $response, $args) {
        // Handle request data 
        $resend_data = ['email' => Utilities::scrub($request,'resend-email'), 'email'];
        
        // Get a logger instance from the container
        $logger = $this->container->get('logger');
        
        // Get a user instance from the container
        $user = clone $this->container->get('User');
        $user->loadFromEmail($resend_data['email']);

        // Does this user already exist?
        if ($user->getId() == '') { 
            $logger->info("Signup rejected: No account for ".$resend_data['email']);
            
            return Utilities::prepResponse($response, [
                'result' => 'error', 
                'reason' => 'no_such_account', 
                'message' => 'resend/no-such-account',
            ], 404, $this->container['settings']['app']['origin']);
        } 
        
        if($user->getStatus() === 'blocked') {
            $logger->info("Resend blocked: User ".$user->getId()." is blocked");
            
            return Utilities::prepResponse($response, [
                'result' => 'error', 
                'reason' => 'account_blocked', 
                'message' => 'resend/account-blocked',
            ], 400, $this->container['settings']['app']['origin']);
        }

        if($user->getStatus() === 'active') {
            $logger->info("Resend blocked: User ".$user->getId()." is already active");
            
            return Utilities::prepResponse($response, [
                'result' => 'error', 
                'reason' => 'account_active', 
                'message' => 'resend/account-active',
            ], 400, $this->container['settings']['app']['origin']);
        }
        // Send email 
        $mailKit = $this->container->get('MailKit');
        $mailKit->signup($user);
        
        $logger->info("Resend: ".$resend_data['email']." is user ".$user->getId());
        return Utilities::prepResponse($response, [
            'result' => 'ok', 
            'reason' => 'signup_complete', 
            'message' => 'signup/success',
        ], 200, $this->container['settings']['app']['origin']);
    }
    
    /** Email change confirmation */
    public function confirm($request, $response, $args) 
    {
        // Request data
        $in = new \stdClass();
        $in->handle = filter_var($args['handle'], FILTER_SANITIZE_STRING);
        $in->token = filter_var($args['token'], FILTER_SANITIZE_STRING);

        // Get a logger instance from the container
        $logger = $this->container->get('logger');

        // Load user
        $user = clone $this->container->get('User');
        $user->loadFromHandle($in->handle);

        // Does the user exist?
        if ($user->getId() == '') { 
            $logger->info("Confirmation rejected: User handle ".$in->handle." does not exist");
        
            return Utilities::prepResponse($response, [
                'result' => 'error', 
                'reason' => 'no_such_account', 
                'message' => 'activation/no-such-account'
            ], 404, $this->container['settings']['app']['origin']);
        }

        // Is the user blocked? 
        if($user->getStatus() === 'blocked') {
            $logger->info('Confirmation rejected: User '.$user->getId().' is blocked');
            
            return Utilities::prepResponse($response, [
                'result' => 'error', 
                'reason' => 'account_blocked', 
                'message' => 'account/blocked'
            ], 400, $this->container['settings']['app']['origin']);
        }

        // Is there a token mismatch? 
        if($in->token != $user->getActivationToken()) {
            $logger->info("Confirmation rejected: Token mismatch for user ".$user->getId());
            
            return Utilities::prepResponse($response, [
                'result' => 'error', 
                'reason' => 'token_mismatch', 
                'message' => 'activation/token-mismatch'
            ], 400, $this->container['settings']['app']['origin']);
        }

        // Get the token kit from the container
        $TokenKit = $this->container->get('TokenKit');
        
        // Confirm address
        $user->setEmail($user->getPendingEmail());
        $user->unsetPendingEmail();
        $user->save();
        
        $logger->info("Confirmation: User ".$user->getId()." is now confirmed for address ".$user->getEmail());
        
        return Utilities::prepResponse($response, [
            'result' => 'ok', 
            'reason' => 'confirm_complete', 
        ], 200, $this->container['settings']['app']['origin']);
    }

    /** User login */
    public function login($request, $response, $args) {
        // Handle request data 
        $data = $request->getParsedBody();
        $login_data = [ 
            'email' => Utilities::scrub($request, 'login-email', 'email'), 
            'password' => Utilities::scrub($request, 'login-password'), 
        ];

        // Get a logger instance from the container
        $logger = $this->container->get('logger');
        
        // Get a user instance from the container
        $user = clone $this->container->get('User');
        $user->loadFromEmail($login_data['email']);

        if($user->getId() == '') {
            $logger->info("Login blocked: No user with address ".$login_data['email']);

            return Utilities::prepResponse($response, [
                'result' => 'error', 
                'reason' => 'login_failed', 
                'message' => 'login/failed',
            ], 400, $this->container['settings']['app']['origin']);
        }

        if($user->getStatus() === 'blocked') {
            $logger->info("Login blocked: User ".$user->getId()." is blocked");
            
            return Utilities::prepResponse($response, [
                'result' => 'error', 
                'reason' => 'account_blocked', 
                'message' => 'login/account-blocked',
            ], 400, $this->container['settings']['app']['origin']);
        }

        if($user->getStatus() === 'inactive') {
            $logger->info("Login blocked: User ".$user->getId()." is inactive");
            
            return Utilities::prepResponse($response, [
                'result' => 'error', 
                'reason' => 'account_inactive', 
                'message' => 'login/account-inactive',
            ], 400, $this->container['settings']['app']['origin']);
        }

        if(!$user->checkPassword($login_data['password'])) {
            $logger->info("Login failed: Incorrect password for user ".$user->getId());
            
            return Utilities::prepResponse($response, [
                'result' => 'error', 
                'reason' => 'login_failed', 
                'message' => 'login/failed',
            ], 400, $this->container['settings']['app']['origin']);
        }

        // Log login
        $user->setLogin();
        $user->save();
        $logger->info("Login: User ".$user->getId());
        
        // Get the token kit from the container
        $TokenKit = $this->container->get('TokenKit');
        if($user->isPatron()) $tier = $user->getPatronTier();
        else $tier = 0;
        
        return Utilities::prepResponse($response, [
            'result' => 'ok', 
            'reason' => 'password_correct', 
            'message' => 'login/success',
            'token' => $TokenKit->create($user->getId()),
            'userid' => $user->getId(),
            'email' => $user->getEmail(),
            'username' => $user->getUsername(),
            'patron' => $tier,
        ], 200, $this->container['settings']['app']['origin']);
    }

    /** User password reset */
    public function reset($request, $response, $args) {
        // Handle request data 
        $data = $request->getParsedBody();
        $reset_data = [ 
            'password' => Utilities::scrub($request, 'reset-password'), 
            'handle' => Utilities::scrub($request, 'reset-handle'), 
            'token' => Utilities::scrub($request, 'reset-token'), 
        ];
        
        // Get a logger instance from the container
        $logger = $this->container->get('logger');
        
        // Get a user instance from the container
        $user = clone $this->container->get('User');
        $user->loadFromHandle($reset_data['handle']);
        if($user->getId() === null) {
            $logger->info("Reset blocked: No user with handle ".$reset_data['token']);

            return Utilities::prepResponse($response, [
                'result' => 'error', 
                'reason' => 'reset_failed', 
                'message' => 'reset/failed',
            ], 400, $this->container['settings']['app']['origin']);
        }

        if($user->getStatus() === 'blocked') {
            $logger->info("Reset blocked: User ".$user->getId()." is blocked");
            
            return Utilities::prepResponse($response, [
                'result' => 'error', 
                'reason' => 'account_blocked', 
                'message' => 'reset/blocked',
            ], 400, $this->container['settings']['app']['origin']);
        }

        if($user->getStatus() === 'inactive') {
            $logger->info("Reset blocked: User ".$user->getId()." is inactive");
            
            return Utilities::prepResponse($response, [
                'result' => 'error', 
                'reason' => 'account_inactive', 
                'message' => 'reset/inactive',
            ], 400, $this->container['settings']['app']['origin']);
        }

        $user->setPassword($reset_data['password']);
        $user->save();

        $logger->info("Reset: Password reset for user ".$user->getId());

        return Utilities::prepResponse($response, [
            'result' => 'ok', 
            'reason' => 'password_reset', 
            'message' => 'reset/success',
        ], 200, $this->container['settings']['app']['origin']);
    }

    /** User password recovery */
    public function recover($request, $response, $args) {
        // Handle request data 
        $data = $request->getParsedBody();
        $recover_data = [ 'email' => Utilities::scrub($request, 'recover-email', 'email') ];
        
        // Get a logger instance from the container
        $logger = $this->container->get('logger');
        
        // Get a user instance from the container
        $user = clone $this->container->get('User');
        $user->loadFromEmail($recover_data['email']);

        if($user->getId() == '') {
            $logger->info("Recover blocked: No user with address ".$recover_data['email']);

            return Utilities::prepResponse($response, [
                'result' => 'error', 
                'reason' => 'recover_failed', 
                'message' => 'recover/failed',
            ], 400, $this->container['settings']['app']['origin']);
        }

        if($user->getStatus() === 'blocked') {
            $logger->info("Recover blocked: User ".$user->getId()." is blocked");
            
            return Utilities::prepResponse($response, [
                'result' => 'error', 
                'reason' => 'account_blocked', 
                'message' => 'recover/blocked',
            ], 400, $this->container['settings']['app']['origin']);
        }

        if($user->getStatus() === 'inactive') {
            $logger->info("Recover blocked: User ".$user->getId()." is inactive");
            
            return Utilities::prepResponse($response, [
                'result' => 'error', 
                'reason' => 'account_inactive', 
                'message' => 'recover/inactive',
            ], 400, $this->container['settings']['app']['origin']);
        }

        $logger->info("Recover: User ".$user->getId());

        // Send email 
        $mailKit = $this->container->get('MailKit');
        $mailKit->recover($user);
        
        return Utilities::prepResponse($response, [
            'result' => 'ok', 
            'reason' => 'recover_initiated', 
            'message' => 'recover/sent',
        ], 200, $this->container['settings']['app']['origin']);
    }

    /** Patron list */
    public function patronList($request, $response, $args) 
    {
        $db = $this->container->get('db');
        $sql = "SELECT `id` FROM `users` WHERE `data` LIKE '%\"tier\":%' LIMIT 100";
        $result = $db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
        $db = null;
        $tiers = $this->container['settings']['patrons']['tiers'];
        if(!$result) {
            return Utilities::prepResponse($response, [
                'result' => 'error', 
                'reason' => 'no_patrons', 
            ], 400, $this->container['settings']['app']['origin']);
        } else {
            // Cycle through users
            foreach($result as $id) {
                // Load account
                $user = clone $this->container->get('User');
                $user->loadFromId($id['id']);
                if($user->getStatus() === 'active' && in_array($user->getPatronTier(), $tiers)) {
                    $patron = new \stdClass();
                    $patron->username = $user->getUsername();
                    $patron->handle = $user->getHandle();
                    $patron->tier = $user->getPatronTier();
                    $patron->picture = $user->getPictureUrl();
                    $patron->badges = $user->getBadges();
                    $patron->social = $user->getSocial();
                    $timestamp = $user->getPatronSince();
                    if($timestamp === false) $timestamp = time();
                    $patron->since = $timestamp;
                    while(isset($patrons[$timestamp])) $timestamp++;
                    $patrons[$timestamp] = $patron;
                    $keys[] = $timestamp;
                } else {
                    echo "\nUser ".$user->getId()." is not a patron";
                }
            }
        } 

        // Newest patrons at the top
        rsort($keys);
        foreach($keys as $t) $sorted[] = $patrons[$t];
    
        return Utilities::prepResponse($response, [
            'result' => 'ok', 
            'patrons' => $sorted, 
        ], 200, $this->container['settings']['app']['origin']);
    } 

    // Authenticated calls
   
    /** Minimal auth check */
    public function auth($request, $response, $args)
    {
        $return = false;
        // Get ID from authentication middleware
        $in = new \stdClass();
        $in->id = $request->getAttribute("jwt")->user;
        // Get a user instance from the container
        $user = clone $this->container->get('User');
        $user->loadFromId($in->id);
        if($user->isPatron()) $patron = $user->getPatronTier();
        else $patron = 0;
        
        // Prepare return JSON
        $return = [
            'result' => 'ok', 
            'id' => $user->getId(), 
            'email' => $user->getEmail(), 
            'user' => $user->getUsername(), 
            'patron' => $patron, 
            'role' => $user->getRole(), 
        ];

        // Add badge if needed
        $badge = $this->container['settings']['badges']['login'];
        if($user->addBadge($badge)) {
            $user->save();
            $return['new_badge'] = $badge;
        }
    
        $response->getBody()->write(json_encode($return));

        return $response
            ->withHeader('Access-Control-Allow-Origin', $this->container['settings']['app']['origin']);
    }

    /** Load user account */
    public function load($request, $response, $args) 
    {
        // Get ID from authentication middleware
        $id = $request->getAttribute("jwt")->user;
        
        // Get a user instance from the container and load user data
        $user = clone $this->container->get('User');
        $user->loadFromId($id);

        // Get the AvatarKit to create the avatar
        $avatarKit = $this->container->get('AvatarKit');

        return Utilities::prepResponse($response, [
            'account' => [
                'id' => $user->getId(), 
                'email' => $user->getEmail(), 
                'username' => $user->getUsername(), 
                'handle' => $user->getHandle(), 
                'status' => $user->getStatus(), 
                'created' => $user->getCreated(), 
                'login' => $user->getLogin(), 
                'picture' => $user->getPicture(), 
                'pictureSrc' => $avatarKit->getWebDir($user->getHandle(), 'user').'/'.$user->getPicture(), 
                'data' => $user->getData(), 
            ],
            'models' => $user->getModels(),
            'drafts' => $user->getDrafts(),
        ], 200, $this->container['settings']['app']['origin']);
    } 

    /** Update account */
    public function update($request, $response, $args) 
    {
        // Handle request
        $in = new \stdClass();
        $in->email = Utilities::scrub($request,'email','email');
        $in->username = Utilities::scrub($request,'username');
        $in->address = Utilities::scrub($request,'address');
        $in->birthmonth = Utilities::scrub($request,'birthday-month');
        $in->birthday = Utilities::scrub($request,'birthday-day');
        $in->twitter = Utilities::scrub($request,'twitter');
        $in->instagram = Utilities::scrub($request,'instagram');
        $in->github = Utilities::scrub($request,'github');
        $in->picture = Utilities::scrub($request,'picture');
        (Utilities::scrub($request,'units') == 'imperial') ? $in->units = 'imperial' : $in->units = 'metric';
        (Utilities::scrub($request,'theme') == 'paperless') ? $in->theme = 'paperless' : $in->theme = 'classic';
        
        // Get ID from authentication middleware
        $in->id = $request->getAttribute("jwt")->user;
        
        // Get a logger instance from the container
        $logger = $this->container->get('logger');
        
        // Get a user instance from the container
        $user = clone $this->container->get('User');
        $user->loadFromId($in->id);

        // Handle picture
        if($in->picture != '') {
            // Get the AvatarKit to create the avatar
            $avatarKit = $this->container->get('AvatarKit');
            $user->setPicture($avatarKit->createFromDataString($in->picture, $user->getHandle()));
        }

        // Handle username change
        if($user->getUsername() != $in->username) {
            if($user->usernameTaken($in->username)) {
                $logger->info("Failed to update profile for user ".$user->getId().": Username ".$in->username." is taken");
                
                return Utilities::prepResponse($response, [
                    'result' => 'error', 
                    'reason' => 'username_taken', 
                    'message' => 'account/username-taken',
                ], 400, $this->container['settings']['app']['origin']);
            }
            $user->setUsername($in->username);
        }

        // Handle toggles
        if($user->getAccountUnits() != $in->units) $user->setAccountUnits($in->units);
        if($user->getAccountTheme() != $in->theme) $user->setAccountTheme($in->theme);
        
        // Handle 3rd party accounts
        $user->setTwitterHandle($in->twitter);
        $user->setInstagramHandle($in->instagram);
        $user->setGithubHandle($in->github);

        // Patron info 
        if($in->address !== false) $user->setPatronAddress($in->address);
        if($in->birthday !== false) $user->setPatronBirthday($in->birthday, $in->birthmonth);

        // Handle email change
        $pendingEmail = false;
        if($in->email !== false && $user->getEmail() != $in->email) {
            if($user->emailTaken($in->email)) {
                $logger->info("Failed to update profile for user ".$user->getId().": Email ".$in->email." is taken");

                return Utilities::prepResponse($response, [
                    'result' => 'error', 
                    'reason' => 'email_taken', 
                    'message' => 'account/email-taken',
                ], 400, $this->container['settings']['app']['origin']);
            }
            // Send email 
            $mailKit = $this->container->get('MailKit');
            $mailKit->emailChange($user, $in->email);
            $logger->info("Email change requested for user ".$user->getId().": From ".$user->getEmail()." to ".$in->email);
            // Store future email address pending confirmation
            $user->setPendingEmail($in->email);
            
            // Save changes 
            $user->save();

            return Utilities::prepResponse($response, [
                'result' => 'ok', 
                'message' => 'account/updated',
                'pendingEmail' => $user->getPendingEmail(),
                'data' => $user->getDataAsJson(),
            ], 200, $this->container['settings']['app']['origin']);
        } else {
            // Save changes 
            $user->save();
            
            return Utilities::prepResponse($response, [
                'result' => 'ok', 
                'message' => 'account/updated',
                'data' => $user->getDataAsJson(),
            ], 200, $this->container['settings']['app']['origin']);
        }
    }
    
    /** Load user profile */
    public function profile($request, $response, $args) 
    {
        // Request data
        $in = new \stdClass();
        $in->handle = filter_var($args['handle'], FILTER_SANITIZE_STRING);
        
        // Get a user instance from the container and load user data
        $user = clone $this->container->get('User');
        $user->loadFromHandle($in->handle);

        // Get the AvatarKit to create the avatar
        $avatarKit = $this->container->get('AvatarKit');

        $return = [
            'profile' => [
                'username' => $user->getUsername(), 
                'handle' => $user->getHandle(), 
                'status' => $user->getStatus(), 
                'created' => $user->getCreated(), 
                'pictureSrc' => $avatarKit->getWebDir($user->getHandle(), 'user').'/'.$user->getPicture(), 
            ],
            'drafts' => $user->getDrafts(),
        ];
        $data = $user->getData();
        if(isset($data->badges)) $return['badges'] = $data->badges;
        if(isset($data->social)) $return['social'] = $data->social;
        if(isset($data->patron)) $return['patron'] = $data->patron;

        return Utilities::prepResponse($response, $return, 200, $this->container['settings']['app']['origin']);
    } 

    /** Remove account */
    public function remove($request, $response, $args) 
    {
        // Get ID from authentication middleware
        $id = $request->getAttribute("jwt")->user;
        
        // Get a user instance from the container and load user data
        $user = clone $this->container->get('User');
        $user->loadFromId($id);

        // Send email 
        $mailKit = $this->container->get('MailKit');
        $mailKit->goodbye($user);
        
        // Get a logger instance from the container
        $logger = $this->container->get('logger');
        
        $logger->info("User removed: ".$user->getId()." (".$user->getEmail().")is no more");
        
        $user->remove();
        
        return Utilities::prepResponse($response, [
            'result' => 'ok', 
            'reason' => 'user_removed', 
        ], 200, $this->container['settings']['app']['origin']);
    } 

    /** Export user data */
    public function export($request, $response, $args) 
    {
        // Get ID from authentication middleware
        $id = $request->getAttribute("jwt")->user;
        
        // Get a user instance from the container and load user data
        $user = clone $this->container->get('User');
        $user->loadFromId($id);

        // Get a logger instance from the container
        $logger = $this->container->get('logger');
        $logger->info("Exporting user data for: ".$user->getId()." (".$user->getEmail().")");
        
        $zip = $user->export();
        
        return Utilities::prepResponse($response, [
            'result' => 'ok', 
            'archive' => $zip, 
        ], 200, $this->container['settings']['app']['origin']);
    } 
    
    public function role($request, $response, $args) 
    {
        // Get ID from authentication middleware
        $id = $request->getAttribute("jwt")->user;
        
        // Get a user instance from the container and load user data
        $user = clone $this->container->get('User');
        $user->loadFromId($id);

        return Utilities::prepResponse(
            $response, 
            ['role' => $user->getRole()],
            200,
            $this->container['settings']['app']['origin']
        );
    } 

}
