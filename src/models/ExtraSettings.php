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
 * @author    Ohmycode
 * @package   CraftJwtAuth
 * @since     0.5.0
 */
class ExtraSettings extends Model
{
    /**
     * This class holds settings that need to be excluded from the craftcms projectconfig
     */
    
    // Public Properties
    // =========================================================================

    
    /**
     * An array map with the Response attribute names as the array keys and the
     * array values as the user element field. The array value can also be a callable.
     *
     * Simple mapping works by matching the Response name in the array with the user's
     * property, and setting what is found in the Response's value to the user element.
     * "IDP Attribute Name" => "Craft Property Name"
     * 
     * With more complex user fields, you can set the array value to a callable
     * "IDP Attribute Name" => function($attribute) {...}
     * */
    public $samlAttributesMap = [
        
        'email' => 'email',
        'firstname' => 'firstName',
        'lastname' => 'lastName'
    ];
}
