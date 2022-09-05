<?php

namespace unionco\craftcognitoauth\helpers;

use unionco\craftcognitoauth\services\AbstractValidator;
use unionco\craftcognitoauth\services\validators\JWT;
use unionco\craftcognitoauth\services\validators\SAML;

class ValidatorsHelper {

    private static $validators = [
        'jwt' => JWT::class,
        'saml' => SAML::class
    ];

    public static function getAllTypes(): array {
        return static::$validators;
    }

    public static function getType($name): AbstractValidator
    {
        return static::$validators[$name];
    }
}