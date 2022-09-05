<?php

namespace unionco\craftcognitoauth\services;

use Craft;
use craft\base\Component;
use Aws\CognitoIdentityProvider\CognitoIdentityProviderClient;
use Aws\CognitoIdentityProvider\Exception\CognitoIdentityProviderException;
use Lcobucci\JWT\Token;
use unionco\craftcognitoauth\CraftJwtAuth;

class AWSCognitoService extends Component
{
    private $region;
    private $client_id;
    private $userpool_id;

    private $client = null;

    public function __construct()
    {
        $this->region = CraftJwtAuth::getInstance()->getSettings()->getRegion();
        $this->client_id = CraftJwtAuth::getInstance()->getSettings()->getClientId();
        $this->userpool_id = CraftJwtAuth::getInstance()->getSettings()->getUserPoolId();

        $this->initialize();
    }

    public function initialize() : void
    {
        $this->client = new CognitoIdentityProviderClient([
          'version' => '2016-04-18',
          'region' => $this->region
        ]);
        
    }

    public function refreshAuthentication($username, $refreshToken)
    {
        try {
            $result = $this->client->adminInitiateAuth([
                'AuthFlow' => 'REFRESH_TOKEN_AUTH',
                'AuthParameters' => [
                    'USERNAME' => $username,
                    'REFRESH_TOKEN' => $refreshToken
                ],
                'ClientId' => $this->client_id,
                'UserPoolId' => $this->userpool_id,
            ]);

            return [
                "token" => $result->get('AuthenticationResult')['IdToken'],
                "accessToken" => $result->get('AuthenticationResult')['AccessToken'],
                "expiresIn" => $result->get('AuthenticationResult')['ExpiresIn']
            ];
        } catch (\Exception $e) {
            return ["error" => $e->getMessage()];
        }
    }

    public function authenticate(string $username, string $password) : array
    {
        try {
            $result = $this->client->adminInitiateAuth([
                'AuthFlow' => 'ADMIN_NO_SRP_AUTH',
                'ClientId' => $this->client_id,
                'UserPoolId' => $this->userpool_id,
                'AuthParameters' => [
                    'USERNAME' => $username,
                    'PASSWORD' => $password,
                ],
            ]);
        } catch (\Exception $e) {
            return ["error" => $e->getMessage()];
        }

        return [
            "token" => $result->get('AuthenticationResult')['IdToken'],
            "accessToken" => $result->get('AuthenticationResult')['AccessToken'],
            "refreshToken" => $result->get('AuthenticationResult')['RefreshToken'],
            "expiresIn" => $result->get('AuthenticationResult')['ExpiresIn']
        ];
    }

    public function signup(string $email, string $password, string $firstname = null, string $lastname = null, string $phone = null, string $username = null) : array
    {
        $userAttributes = [
            [
                'Name' => 'email',
                'Value' => $email
            ]
        ];

        if($firstname)
            $userAttributes[] = [
                'Name' => 'given_name',
                'Value' => $firstname
            ];

        if($lastname)
            $userAttributes[] = [
                'Name' => 'family_name',
                'Value' => $lastname
            ];
            
        if($phone)
            $userAttributes[] = [
                'Name' => 'phone_number',
                'Value' => $phone
            ];

        try {
            $result = $this->client->signUp([
                'ClientId' => $this->client_id,
                'Username' => $username ? $username : $email,
                'Password' => $password,
                'UserAttributes' => $userAttributes,
            ]);

            return ["UserSub" => $result->get('UserSub')];
            
        } catch (\Exception $e) {
            return ["error" => $e->getMessage()];
        }
    }

    public function adminCreateUser(string $email, string $password, string $firstname, string $lastname, string $phone = null, $username = null) : array
    {
        $userAttributes = [
            [
                'Name' => 'given_name',
                'Value' => $firstname
            ],
            [
                'Name' => 'family_name',
                'Value' => $lastname
            ],
            [
                'Name' => 'email',
                'Value' => $email
            ],
            [
                'Name' => 'email_verified',
                'Value' => 'true'
            ]
        ];

        if($phone){
            $userAttributes[] = [
                'Name' => 'phone_number',
                'Value' => $phone
            ];

            $userAttributes[] = [
                'Name' => 'phone_verified',
                'Value' => 'true'
            ];
        }

        try {
            $result = $this->client->adminCreateUser([
                'UserPoolId' => $this->userpool_id,
                'Username' => $username ? $username : $email,
                'MessageAction' => 'SUPPRESS',
                'TemporaryPassword' => $password,
                'UserAttributes' => $userAttributes
            ]);

            $userSub = $result->get('User')['Username'];

            $result = $this->client->adminInitiateAuth([
                'AuthFlow' => 'ADMIN_NO_SRP_AUTH',
                'AuthParameters' => [
                    "USERNAME" => $email,
                    "PASSWORD" => $password
                ],
                'ClientId' => $this->client_id,
                'UserPoolId' => $this->userpool_id
            ]);            

            $session = $result->get("Session");

            $result = $this->client->adminRespondToAuthChallenge([
                'ChallengeName' => 'NEW_PASSWORD_REQUIRED',
                'ChallengeResponses'=> [
                    "USERNAME"=>$email,
                    "NEW_PASSWORD"=>$password
                ],
                'ClientId' => $this->client_id,
                'Session' => $session,
                'UserPoolId' => $this->userpool_id
            ]);

            return ["UserSub" => $userSub];
            
        } catch (\Exception $e) {
            return ["error" => $e->getMessage()];
        }
    }

    public function resendConfirmationCode(string $email)
    {
        try {
            $this->client->resendConfirmationCode([
                'ClientId' => $this->client_id,
                'Username' => $email
            ]);
        } catch (\Exception $e) {
            return $e->getMessage();
        }

        return '';
    }

    public function confirmSignup(string $email, string $code) : string
    {
        try {
            $result = $this->client->confirmSignUp([
                'ClientId' => $this->client_id,
                'Username' => $email,
                'ConfirmationCode' => $code,
            ]);
        } catch (\Exception $e) {
            return $e->getMessage();
        }

        return '';
    }

    public function updateUserAttributes($username, $firstname, $lastname, $phone = null, $email = null) : string
    {
        try {
            $userAttributes = [];
            if($firstname !=null)
                $userAttributes[] = [
                    'Name' => 'given_name',
                    'Value' => $firstname,
                ];

            if($lastname !=null)
                $userAttributes[] = [
                    'Name' => 'family_name',
                    'Value' => $lastname,
                ];

            if($phone !=null)
                $userAttributes[] = [
                    'Name' => 'phone_number',
                    'Value' => $phone,
                ];

            if($email !=null)
                $userAttributes[] = [
                    'Name' => 'email',
                    'Value' => $email,
                ];
            $this->client->adminUpdateUserAttributes([
                'Username' => $username,
                'UserPoolId' => $this->userpool_id,
                'UserAttributes' => $userAttributes,
            ]);
        } catch (\Exception $e) {
            return $e->getMessage();
        }

        return '';
    }

    public function deleteUser($username)
    {
        try {
            $this->client->adminDeleteUser([
                'Username' => $username,
                'UserPoolId' => $this->userpool_id
            ]);
        } catch (\Exception $e) {
            return $e->getMessage();
        }

        return '';
    }

    public function disableUser($username) : string
    {
        try {
            $this->client->adminDisableUser([
                'Username' => $username,
                'UserPoolId' => $this->userpool_id
            ]);
        } catch (\Exception $e) {
            return $e->getMessage();
        }

        return '';
    }

    public function sendPasswordResetMail(string $email) : string
    {
        try {
            $this->client->forgotPassword([
                'ClientId' => $this->client_id,
                'Username' => $email
            ]);
        } catch (\Exception $e) {
            return $e->getMessage();
        }

        return '';
    }

    public function resetPassword(string $code, string $password, string $email) : string
    {
        try {
            $this->client->confirmForgotPassword([
                'ClientId' => $this->client_id,
                'ConfirmationCode' => $code,
                'Password' => $password,
                'Username' => $email
            ]);
        } catch (\Exception $e) {
            return $e->getMessage();
        }

        return '';
    }

    public function getEmail(?Token $token){
        if(!$token) return '';
        
        return $token->getClaim('email','');
    }

    public function isAdmin(?Token $token){
        if(!$token) return false;

        $groups = $token->getClaim('cognito:groups',[]);
        if($groups && in_array('admin', $groups)){
            return true;
        }

        return false;
    }
}