<?php

/**
 * Craft JWT Auth plugin for Craft CMS 3.x
 *
 * Enable authentication to Craft through the use of JSON Web Tokens (JWT)
 *
 * @link      https://edenspiekermann.com
 * @copyright Copyright (c) 2019 Mike Pierce
 */

namespace unionco\craftcognitoauth\controllers;

use Craft;
use craft\web\Controller;
use unionco\craftcognitoauth\CraftJwtAuth;

/**
 * @author    Mike Pierce
 * @package   CraftJwtAuth
 * @since     0.1.0
 */
class SamlController extends Controller
{

    // Protected Properties
    // =========================================================================

    /**
     * @var    bool|array Allows anonymous access to this controller's actions.
     *         The actions must be in 'kebab-case'
     * @access protected
     */
    protected array|int|bool $allowAnonymous = ['auth', 'login'];

    public function beforeAction($action): bool
	{

        $this->enableCsrfValidation = false;

		return parent::beforeAction($action);
	}

    // Public Methods
    // =========================================================================

    public function actionAuth()
    {
        $samlResponse = Craft::$app->request->getParam('SAMLResponse');
        CraftJwtAuth::getInstance()->get('saml')->parseTokenAndCreateUser($samlResponse);
        
        $userService = Craft::$app->getUser();

        return Craft::$app->getResponse()->redirect($userService->getReturnUrl());
    }

    public function actionLogin()
    {
        if(!Craft::$app->getUser()->identity){
            $samlLogin = CraftJwtAuth::$plugin->settingsService->get()->normal->getSamlIdpLogin();
            Craft::$app->response->redirect($samlLogin);

            Craft::$app->end();
        }

        return "";
    }
}
