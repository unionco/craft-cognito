<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license MIT
 */

namespace unionco\craftcognitoauth\events;

use craft\elements\User;
use yii\base\Event;

/**
 * Class UserCreateEvent
 *
 * @author    unionco
 * @since     0.5
 */
class UserCreateEvent extends Event
{
    // Properties
    // =========================================================================

    /**
     * @var User
     */
    public $user;

    /**
     * @var string
     */
    public $issuer;
}
