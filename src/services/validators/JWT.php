<?php

/**
 * Craft JWT Auth plugin for Craft CMS 3.x
 *
 * Enable authentication to Craft through the use of JSON Web Tokens (JWT)
 *
 * @link      https://edenspiekermann.com
 * @copyright Copyright (c) 2019 Mike Pierce
 */

namespace unionco\craftcognitoauth\services\validators;

use Craft;
use craft\base\Component;
use craft\elements\User;
use craft\helpers\StringHelper;
use craft\helpers\ArrayHelper;
use unionco\craftcognitoauth\CraftJwtAuth;
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token;

use CoderCat\JWKToPEM\JWKConverter;
use unionco\craftcognitoauth\services\AbstractValidator;

/**
 * @author    Mike Pierce
 * @package   CraftJwtAuth
 * @since     0.1.0
 */
class JWT extends AbstractValidator
{
    // Public Methods
    // =========================================================================

    private $jwtEnabled;
    private $jwksUrl;

    public function __construct()
    {
        $this->jwtEnabled = CraftJwtAuth::getInstance()->settingsService->get()->normal->getJwtEnabled();
        $this->jwksUrl = CraftJwtAuth::getInstance()->settingsService->get()->normal->getJwks();

        parent::__construct();
    }

    public function isEnabled(){
        return $this->jwtEnabled;
    }


    protected function getTokenFromRequest()
    {
        // Look for an access token in the settings
        $accessToken = Craft::$app->request->headers->get('authorization') ?: Craft::$app->request->headers->get('x-access-token');

        // If "Bearer " is present, strip it to get the token.
        if (StringHelper::startsWith($accessToken, 'Bearer ')) {
            $accessToken = StringHelper::substr($accessToken, 7);
        }

        // If we find one, and it looks like a JWT...
        if ($accessToken) {
            return $accessToken;
        }

        return null;
    }

    protected function parseToken($accessToken)
    {
        if (count(explode('.', $accessToken)) === 3) {
            $token = (new Parser())->parse((string) $accessToken);

            return $token;
        }

        return null;
    }

    protected function verifyToken($token)
    {
        $jwks = json_decode(file_get_contents($this->jwksUrl), true);
        $jwk = null;
        foreach($jwks['keys'] as $struct) {
            if ($token->getHeader('kid') === $struct['kid']) {
                $jwk = $struct;
                break;
            }
        }

        $jwkConverter = new JWKConverter();
        $convertedJwk = $jwkConverter->toPEM($jwk);

        // Attempt to verify the token
        $verify = $token->verify((new Sha256()), $convertedJwk);

        return $verify;
    }

    protected function getIssuerByToken($token){
        //TODO Diferentiate different issuers inside cognito?
        return 'cognito';
    }

    protected function getUserByToken($token)
    {
        // Derive the username from the subject in the token
        $email = $token->getClaim('email', '');
        $userName = $token->getClaim('sub', '');

        // Look for the user with email
        $user = Craft::$app->users->getUserByUsernameOrEmail($email ?: $userName);

        return $user;
    }

    protected function createUserByToken($token)
    {
        // Email is a mandatory field
        if ($token->hasClaim('email')) {
            $email = $token->getClaim('email');

            // Create a new user and populate with claims
            $user = new User();

            // Set username and email
            $user->email = $email;
            $user->username = $token->getClaim('cognito:username', $email);

            // These are optional, so pass empty string as the default
            $user->firstName = $token->getClaim('given_name', '');
            $user->lastName = $token->getClaim('family_name', '');

            // Attempt to save the user
            $success = Craft::$app->getElements()->saveElement($user);

            // If user saved ok...
            if ($success) {
                // Assign the user to the default public group
                Craft::$app->users->assignUserToDefaultGroup($user);

                return $user;
            } 
        }

        return null;
    }
}
