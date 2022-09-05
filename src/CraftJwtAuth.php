<?php

/**
 * Craft JWT Auth plugin for Craft CMS 3.x
 *
 * Enable authentication to Craft through the use of JSON Web Tokens (JWT)
 *
 * @link      https://edenspiekermann.com
 * @copyright Copyright (c) 2019 Mike Pierce
 */

namespace unionco\craftcognitoauth;

use Craft;
use yii\base\Event;
use craft\base\Plugin;
use craft\web\UrlManager;
use craft\web\Application;
use craft\events\RegisterUrlRulesEvent;
use unionco\craftcognitoauth\models\Settings;
use unionco\craftcognitoauth\services\SettingsService;
use unionco\craftcognitoauth\helpers\ValidatorsHelper;
use unionco\craftcognitoauth\services\AbstractValidator;
use unionco\craftcognitoauth\services\AWSCognitoService;

/**
 * Class CraftJwtAuth
 *
 * @author    Mike Pierce
 * @package   CraftJwtAuth
 * @since     0.1.0
 *
 * @property  AWSCognitoService $cognito
 * @property  SettingsService $settingsService
 * @property  Settings $settings
 * @method    Settings getSettings()
 */
class CraftJwtAuth extends Plugin
{
    // Static Properties
    // =========================================================================

    /**
     * @var CraftJwtAuth
     */
    public static $plugin;

    // Public Properties
    // =========================================================================

    /**
     * @var string
     */
    public string $schemaVersion = '0.1.0';

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        Craft::setAlias('@cognito', $this->getBasePath());
        self::$plugin = $this;

        foreach (ValidatorsHelper::getAllTypes() as $name => $validator) {
            $this->set($name, $validator);
        }

        Event::on(
            Application::class,
            Application::EVENT_INIT,
            function () {
                if (Craft::$app instanceof craft\web\Application) {
                    foreach (ValidatorsHelper::getAllTypes() as $name => $validator) {
                        /**
                         * @var AbstractValidator
                         */
                        $validator = $this->get($name);
                        if ($validator->isEnabled())
                            $this->get($name)->parseTokenAndCreateUser();
                    }
                }
            }
        );

        $i18n = Craft::$app->getI18n();
        /** @noinspection UnSafeIsSetOverArrayInspection */
        if (!isset($i18n->translations[$this->id]) && !isset($i18n->translations[$this->id . '*'])) {
            $i18n->translations[$this->id] = [
                'class' => PhpMessageSource::class,
                'sourceLanguage' => 'en-US',
                'basePath' => '@cognito/translations',
                'forceTranslation' => true,
                'allowOverrides' => true,
            ];
        }

        // Register our CP routes
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                // Merge so that settings controller action comes first (important!)
                $event->rules = array_merge(
                    [
                        'settings/plugins/craft-cognito' => 'craft-cognito/settings/edit',
                    ],
                    $event->rules
                );
            }
        );

        // Craft::info(
        //     Craft::t(
        //         'craft-cognito',
        //         '{name} plugin loaded',
        //         ['name' => $this->name]
        //     ),
        //     __METHOD__
        // );
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function createSettingsModel(): ?\craft\base\Model
    {
        return new Settings();
    }

    /**
     * @inheritdoc
     */
    protected function settingsHtml(): ?string
    {
        return Craft::$app->view->renderTemplate(
            '@cognito/settings',
            [
                'settings' => $this->getSettings()
            ]
        );
    }
}
