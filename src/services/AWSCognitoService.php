<?php

namespace unionco\craftcognitoauth\services;

use Craft;
use Aws\Result;
use Lcobucci\JWT\Token;
use craft\base\Component;
use unionco\craftcognitoauth\CraftJwtAuth;
use Aws\CognitoIdentityProvider\CognitoIdentityProviderClient;
use Aws\CognitoIdentityProvider\Exception\CognitoIdentityProviderException;
use Throwable;

class AWSCognitoService extends Component
{
    private string $region;
    private string $client_id;
    private string $userpool_id;
    private string $client_secret;

    private $client = null;

    public function __construct()
    {
        $settings = CraftJwtAuth::getInstance()->getSettings();
        $this->region = $settings->getRegion();
        $this->client_id = $settings->getClientId();
        $this->userpool_id = $settings->getUserPoolId();
        $this->client_secret = $settings->getClientSecret();

        $this->initialize();
    }

    public function initialize(): void
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
                    'REFRESH_TOKEN' => $refreshToken,
                    'SECRET_HASH' => $this->client_secret,
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

    public function authenticate(string $username, string $password): array
    {
        try {
            $secretHash = $this->cognitoSecretHash($username);
            /** @var Result */
            $initiateAuthResult = $this->client->adminInitiateAuth([
                'AuthFlow' => 'ADMIN_NO_SRP_AUTH',
                'ClientId' => $this->client_id,
                'UserPoolId' => $this->userpool_id,
                'AuthParameters' => [
                    'USERNAME' => $username,
                    'PASSWORD' => $password,
                    'SECRET_HASH' => $secretHash, // added by Union 9/5/22
                ],
            ]);
        } catch (\Exception $e) {
            return ["error" => $e->getMessage()];
        }

        // Added by Union 9/5/22 - Check if the user needs to reset their password
        if ($this->checkForNewPasswordChallenge($initiateAuthResult)) {
            return $this->handleNewPasswordChallenge($initiateAuthResult);
        } elseif ($this->checkForMfaSetupChallenge($initiateAuthResult)) {
            return $this->handleMfaSetupChallenge($initiateAuthResult);
        }

        return $this->handleSuccessfulAuth($initiateAuthResult);
    }

    private function checkForNewPasswordChallenge(Result $result): bool
    {
        $challengeName = $result->get('ChallengeName');
        if (!$challengeName) {
            return false;
        }
        return strpos($challengeName, 'NEW_PASSWORD_REQUIRED') !== false;
    }

    private function handleNewPasswordChallenge(Result $result): array
    {
        try {
            $session = $result->get('Session');
            return [
                'resetPasswordFlag' => true,
                'message' => 'Please reset your password',
                'session' => $session,
            ];
        } catch (Throwable $e) {
            throw $e;
        }
    }

    private function checkForMfaSetupChallenge(Result $result): bool
    {
        $challengeName = $result->get('ChallengeName');
        if (!$challengeName) {
            return false;
        }
        return strpos($challengeName, 'MFA_SETUP') !== false;
    }

    private function handleMfaSetupChallenge(Result $result): array
    {
        $session = $result->get('Session');
        $params = $result->get('ChallengeParameters')['MFAS_CAN_SETUP'];
        return [
            'success' => true,
            'mfaSetupFlag' => true,
            'session' => $session,
            'message' => 'Please Setup Multi-Factor Authentication',
            'parameters' => $params,
        ];
    }

    private function handleSuccessfulAuth(Result $result): array
    {
        return [
            "token" => $result->get('AuthenticationResult')['IdToken'],
            "accessToken" => $result->get('AuthenticationResult')['AccessToken'],
            "refreshToken" => $result->get('AuthenticationResult')['RefreshToken'],
            "expiresIn" => $result->get('AuthenticationResult')['ExpiresIn']
        ];
    }

    public function signup(
        string $email,
        string $password,
        string $firstname = null,
        string $lastname = null,
        string $phone = null,
        string $username = null
    ): array {
        $userAttributes = [
            [
                'Name' => 'email',
                'Value' => $email
            ]
        ];

        if ($firstname) {
            $userAttributes[] = [
                'Name' => 'given_name',
                'Value' => $firstname
            ];
        }
        if ($lastname) {
            $userAttributes[] = [
                'Name' => 'family_name',
                'Value' => $lastname
            ];
        }
        if ($phone) {
            $userAttributes[] = [
                'Name' => 'phone_number',
                'Value' => $phone
            ];
        }
        try {
            $secretHash = $this->cognitoSecretHash($email);
            $result = $this->client->signUp([
                'ClientId' => $this->client_id,
                'Username' => $username ? $username : $email,
                'Password' => $password,
                'UserAttributes' => $userAttributes,
                'SecretHash' => $secretHash,
            ]);

            return ["UserSub" => $result->get('UserSub')];
        } catch (\Exception $e) {
            return ["error" => $e->getMessage()];
        }
    }

    public function adminCreateUser(
        string $email,
        string $password,
        string $firstname,
        string $lastname,
        string $phone = null,
        $username = null
    ): array {
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

        if ($phone) {
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
            /** @var Result */
            $createUserResult = $this->client->adminCreateUser([
                'UserPoolId' => $this->userpool_id,
                'Username' => $username ? $username : $email,
                'MessageAction' => 'SUPPRESS',
                'TemporaryPassword' => $password,
                'UserAttributes' => $userAttributes,
            ]);

            $userSub = $createUserResult->get('User')['Username'];

            $secretHash = $this->cognitoSecretHash($email);
            /** @var Result */
            $initiateAuthResult = $this->client->adminInitiateAuth([
                'AuthFlow' => 'ADMIN_NO_SRP_AUTH',
                'AuthParameters' => [
                    "USERNAME" => $email,
                    "PASSWORD" => $password,
                    'SECRET_HASH' => $secretHash, // Added by Union 9/5/22
                ],
                'ClientId' => $this->client_id,
                'UserPoolId' => $this->userpool_id
            ]);

            if ($this->checkForNewPasswordChallenge($initiateAuthResult)) {
                return $this->handleNewPasswordChallenge($initiateAuthResult);
            }

            // $session = $initiateAuthResult->get("Session");

            // /** @var Result */
            // $respondToAuthChallengeResult = $this->client->adminRespondToAuthChallenge([
            //     'ChallengeName' => 'NEW_PASSWORD_REQUIRED',
            //     'ChallengeResponses' => [
            //         "USERNAME" => $email,
            //         "NEW_PASSWORD" => $password,
            //         'SECRET_HASH' => $secretHash,
            //     ],
            //     'ClientId' => $this->client_id,
            //     'Session' => $session,
            //     'UserPoolId' => $this->userpool_id
            // ]);

            return ["UserSub" => $userSub];
        } catch (\Exception $e) {
            return ["error" => $e->getMessage()];
        }
    }

    public function setMfaPreferences(
        string $email,
        string $session,
        bool $enableSms = false,
        bool $enableApp = true,
        bool $preferApp = true
    ): array {
        $secretHash = $this->cognitoSecretHash($email);
        /** @var Result */
        $respondToAuthChallengeResult = $this->client->adminRespondToAuthChallenge([
            'ChallengeName' => 'MFA_SETUP',
            'ChallengeResponses' => [
                "USERNAME" => $email,
                'SECRET_HASH' => $secretHash,
            ],
            'ClientId' => $this->client_id,
            'Session' => $session,
            'UserPoolId' => $this->userpool_id
        ]);
        $session = $respondToAuthChallengeResult->get("Session");
        return [
            'success' => true,
            'session' => $session,
        ];
    }

    /**
     * Added by Union 9/5/22
     * If a user tries to log in, but is presented the NEW_PASSWORD_REQUIRED challenge, then they
     * can set their permanent password here
     *
     * @param string $email
     * @param string $session
     * @param string $newPass
     * @return void
     */
    public function setPassword(string $email, string $session, string $newPass)
    {
        try {
            $secretHash = $this->cognitoSecretHash($email);
            /** @var Result */
            $respondToAuthChallengeResult = $this->client->adminRespondToAuthChallenge([
                'ChallengeName' => 'NEW_PASSWORD_REQUIRED',
                'ChallengeResponses' => [
                    "USERNAME" => $email,
                    'SECRET_HASH' => $secretHash,
                    "NEW_PASSWORD" => $newPass,
                ],
                'ClientId' => $this->client_id,
                'Session' => $session,
                'UserPoolId' => $this->userpool_id
            ]);
            // The next step will be MFA Setup
            if ($this->checkForMfaSetupChallenge($respondToAuthChallengeResult)) {
                return $this->handleMfaSetupChallenge($respondToAuthChallengeResult);
            }
            return [
                'Success' => true,
            ];
        } catch (Throwable $e) {
            return ['error' => $e->getMessage()];
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

    public function confirmSignup(string $email, string $code): string
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

    public function updateUserAttributes($username, $firstname, $lastname, $phone = null, $email = null): string
    {
        try {
            $userAttributes = [];
            if ($firstname != null) {
                $userAttributes[] = [
                    'Name' => 'given_name',
                    'Value' => $firstname,
                ];
            }
            if ($lastname != null) {
                $userAttributes[] = [
                    'Name' => 'family_name',
                    'Value' => $lastname,
                ];
            }
            if ($phone != null) {
                $userAttributes[] = [
                    'Name' => 'phone_number',
                    'Value' => $phone,
                ];
            }
            if ($email != null) {
                $userAttributes[] = [
                    'Name' => 'email',
                    'Value' => $email,
                ];
            }
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

    public function disableUser($username): string
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

    /**
     * If a user forgets their password, this method will trigger and email code to them
     *
     * @param string $email
     * @return string
     */
    public function sendPasswordResetMail(string $email): string
    {
        try {
            $secretHash = $this->cognitoSecretHash($email);
            $this->client->forgotPassword([
                'ClientId' => $this->client_id,
                'Username' => $email,
                'SecretHash' => $secretHash,
            ]);
        } catch (\Exception $e) {
            return $e->getMessage();
        }

        return '';
    }

    /**
     * If a user requests a forgot password email, they set their new password here
     *
     * @param string $code
     * @param string $password
     * @param string $email
     * @return string
     */
    public function resetPassword(string $code, string $password, string $email): string
    {
        try {
            $secretHash = $this->cognitoSecretHash($email);
            $this->client->confirmForgotPassword([
                'ClientId' => $this->client_id,
                'ConfirmationCode' => $code,
                'Password' => $password,
                'Username' => $email,
                'SecretHash' => $secretHash,
            ]);
        } catch (\Exception $e) {
            return $e->getMessage();
        }

        return '';
    }

    public function getEmail(?Token $token): string
    {
        if (!$token) {
            return '';
        }

        return $token->getClaim('email', '');
    }

    public function isAdmin(?Token $token): bool
    {
        if (!$token) {
            return false;
        }

        $groups = $token->getClaim('cognito:groups', []);
        if ($groups && in_array('admin', $groups)) {
            return true;
        }

        return false;
    }

    /**
     * Added by Union 9/5/22 to support client secret
     * 'SECRET_HASH' must be sent hashed with the user's username (in our case, this is the email)
     */
    private function cognitoSecretHash(string $username): string
    {
        $hash = hash_hmac('sha256', $username . $this->client_id, $this->client_secret, true);
        return base64_encode($hash);
    }
}
