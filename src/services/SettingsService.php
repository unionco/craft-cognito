<?php

namespace unionco\craftcognitoauth\services;

use Craft;
use craft\base\Component;
use unionco\craftcognitoauth\CraftJwtAuth;
use unionco\craftcognitoauth\models\ExtraSettings;
use unionco\craftcognitoauth\models\PluginSettings;

class SettingsService extends Component
{
    // Public Methods
    // =========================================================================

    /**
     * The extra settings are there so that they are not handled by craft
     * Therefore they are kept outside of the projectconfig
     */
    public function get(): PluginSettings
    {
        $settings = new PluginSettings();
        $settings->normal = CraftJwtAuth::$plugin->settings;
        $settings->extra = new ExtraSettings();
        $settings->extra->setAttributes(Craft::$app->config->getConfigFromFile('craft-cognito-extra'), false);

        return $settings;
    }
}