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
class PluginSettings
{
    // Public Properties
    // =========================================================================

    public Settings $normal;
    public ExtraSettings $extra;
    
}
