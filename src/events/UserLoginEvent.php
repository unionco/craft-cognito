<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license MIT
 */

namespace unionco\craftcognitoauth\events;

use yii\base\Event;

/**
 * Class UserLoginEvent
 *
 * @author    unionco
 * @since     1.0
 */
class UserLoginEvent extends Event
{
    // Properties
    // =========================================================================

    /**
     * @var string
     */
    public $email;
}
