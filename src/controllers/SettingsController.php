<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace unionco\craftcognitoauth\controllers;

use Craft;
use craft\errors\MissingComponentException;
use craft\web\Controller;
use unionco\craftcognitoauth\CraftJwtAuth;
use yii\web\BadRequestHttpException;
use yii\web\Response;

class SettingsController extends Controller
{
    // Public Methods
    // =========================================================================

    /**
     * Edit the plugin settings.
     *
     * @return Response|null
     */
    public function actionEdit()
    {
        $settings = CraftJwtAuth::$plugin->settings;
        $view = Craft::$app->view;
        $view->setTemplateMode($view::TEMPLATE_MODE_CP);

        return $this->renderTemplate('craft-cognito/_settings', [
            'settings' => $settings
        ]);
    }

    /**
     * Saves the plugin settings.
     *
     * @return Response|null
     * @throws BadRequestHttpException
     * @throws MissingComponentException
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();

        $postedSettings = $request->getBodyParam('settings', []);

        $settings = CraftJwtAuth::$plugin->settings;
        $settings->setAttributes($postedSettings, false);

        // Validate
        $settings->validate();

        if ($settings->hasErrors()
        ) {
            Craft::$app->getSession()->setError(Craft::t('craft-cognito', 'Couldn’t save plugin settings.'));

            return null;
        }

        // Save it
        Craft::$app->getPlugins()->savePluginSettings(CraftJwtAuth::$plugin, $settings->getAttributes());

        $notice = Craft::t('craft-cognito', 'Plugin settings saved.');
        $errors = [];

        if (!empty($errors)) {
            Craft::$app->getSession()->setError($notice.' '.implode(' ', $errors));

            return null;
        }

        Craft::$app->getSession()->setNotice($notice);

        return $this->redirectToPostedUrl();
    }
}