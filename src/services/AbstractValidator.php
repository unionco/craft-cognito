<?php

namespace unionco\craftcognitoauth\services;

use Craft;
use craft\base\Component;
use unionco\craftcognitoauth\CraftJwtAuth;
use unionco\craftcognitoauth\events\UserCreateEvent;

abstract class AbstractValidator extends Component{
    
    const EVENT_AFTER_CREATE_USER = 'afterCreateUser';
    private $autocreateUser;

    public abstract function isEnabled();

    protected abstract function getTokenFromRequest();

    protected abstract function parseToken(string $accessToken);
    protected abstract function verifyToken($accessToken);

    protected abstract function getIssuerByToken($token);
    protected abstract function getUserByToken($token);
    protected abstract function createUserByToken($token);

    public function __construct()
    {
        $this->autocreateUser = CraftJwtAuth::getInstance()->settingsService->get()->normal->getAutoCreateUser();
    }

    public function parseTokenAndCreateUser($accessToken = null)
    {
        if(!$accessToken)
            $accessToken = $this->getTokenFromRequest();

        if(!$accessToken)
            return;

        $token = $this->parseAndVerifyToken($accessToken);

        // If the token passes verification...
        if ($token) {

            // Look for the user
            $user = $this->getUserByToken($token);

            $userCreated = false;
            // If we don't have a user, but we're allowed to create one...
            if (!$user && $this->autocreateUser) {
                $user = $this->createUserByToken($token);
                $userCreated = $user && $user->id;
            }

            if ($user && $user->id) {
                /*
                * We need to login before triggering the event
                * because the twig globals are updated before the user is logged in
                * This causes the currentUser twig variable to be null even though the user is logged
                */
                Craft::$app->user->loginByUserId($user->id);

                if($userCreated && $this->hasEventHandlers(self::EVENT_AFTER_CREATE_USER)){
                    $event = new UserCreateEvent(['user' => $user, 'issuer' => $this->getIssuerByToken($token)]);    
                    $this->trigger(self::EVENT_AFTER_CREATE_USER, $event);
                }
            }
        }
    }

    private function parseAndVerifyToken($accessToken)
    {
        $token = $this->parseToken($accessToken);

        if ($token && $this->verifyToken($token)) {
            return $token;
        }

        return null;
    }
}