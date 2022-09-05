<?php

/**
 * Craft JWT Auth plugin for Craft CMS 3.x
 *
 * Enable authentication to Craft through the use of JSON Web Tokens (JWT)
 *
 * @link      https://edenspiekermann.com
 * @copyright Copyright (c) 2019 Mike Pierce
 */

namespace unionco\craftcognitoauth\models;

use Craft;
use craft\base\Model;
use craft\behaviors\EnvAttributeParserBehavior;

/**
 * @author    Mike Pierce
 * @package   CraftJwtAuth
 * @since     0.1.0
 */
class Settings extends Model
{
    // Public Properties
    // =========================================================================

    /**
     * @var string
     */
    public bool $autoCreateUser = false;
    public string $region = '';
    public string $clientId = '';
    public string $clientSecret = '';
    public string $userpoolId = '';
    public string $jwks = '';
    public bool $jwtEnabled = true;
    
    //Saml cert path to validate SAML tokens
    public $samlCert = '';

    //Login URL of the SAML IdP
    public $samlIdPLogin;
    
    public $samlEnabled = false;

    // Public Methods
    // =========================================================================
    public function behaviors(): array
    {
        return [
            'parser' => [
                'class' => EnvAttributeParserBehavior::class,
                'attributes' => [
                    'autoCreateUser',
                    'region','clientId','userpoolId','jwks',
                    'samlCert', 'samlIdPLogin'
                ],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            ['jwtEnabled', 'boolean'],
            ['autoCreateUser', 'boolean'],
            ['region', 'string'],
            ['clientId', 'string'],
            ['clientSecret', 'string'],
            ['userpoolId', 'string'],
            ['jwks', 'string'],
            ['samlEnabled', 'boolean'],
            ['samlCert', 'string'],
            ['samlIdPLogin', 'string'],
        ];
    }

    public function getAutoCreateUser(): bool
    {
        return boolval(Craft::parseEnv($this->autoCreateUser));
    }

    public function getRegion(): string
    {
        return Craft::parseEnv($this->region);
    }

    public function getClientId(): string
    {
        return Craft::parseEnv($this->clientId);
    }
    
    public function getClientSecret(): string
    {
        return Craft::parseEnv($this->clientSecret);
    }

    public function getUserPoolId(): string
    {
        return Craft::parseEnv($this->userpoolId);
    }

    public function getJwks(): string
    {
        return Craft::parseEnv($this->jwks);
    }

    public function getSamlCert(): string
    {
        return Craft::parseEnv($this->samlCert);
    }

    public function getSamlIdpLogin(): string
    {
        return Craft::parseEnv($this->samlIdPLogin);
    }

    public function getSamlEnabled(): bool
    {
        return $this->samlEnabled;
    }

    public function getJwtEnabled(): bool
    {
        return $this->jwtEnabled;
    }
}
