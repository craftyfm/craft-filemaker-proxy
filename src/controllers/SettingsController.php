<?php

namespace craftyfm\filemakerproxy\controllers;

use Craft;
use craft\web\Controller;
use craftyfm\filemakerproxy\FmProxy;
use craftyfm\filemakerproxy\models\Connection;
use yii\web\BadRequestHttpException;
use yii\web\MethodNotAllowedHttpException;
use yii\web\Response;

class SettingsController extends Controller
{
    public function actionIndex(): Response
    {
        $settings = FmProxy::getInstance()->getSettings();
        return $this->renderTemplate('filemaker-proxy/settings', ['settings' => $settings, 'currentPage' => 'general']);
    }

    /**
     * @throws MethodNotAllowedHttpException
     * @throws BadRequestHttpException
     */
    public function actionSave(): Response
    {
        $this->requirePostRequest();

        $adminEmail = $this->request->getBodyParam('adminEmail');
        $token = $this->request->getBodyParam('token');

        $settings = compact('adminEmail', 'token');

        if (Craft::$app->getPlugins()->savePluginSettings(FmProxy::getInstance(), $settings)) {
            $this->setSuccessFlash('Settings saved successfully.');
        } else {
            $this->setFailFlash('Failed to save settings.');
        }

        return $this->redirectToPostedUrl();
    }
}