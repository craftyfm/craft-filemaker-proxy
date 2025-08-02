<?php

namespace craftyfm\filemakerproxy\controllers;

use Craft;
use craft\web\Controller;
use craftyfm\filemakerproxy\FmProxy;
use craftyfm\filemakerproxy\models\Profile;
use yii\base\Response;
use yii\db\StaleObjectException;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\MethodNotAllowedHttpException;
use yii\web\NotFoundHttpException;


class ProfilesController extends Controller
{
    /**
     * @throws ForbiddenHttpException
     */
    public function actionIndex(): Response
    {
        $this->requirePermission('fmProxy-view-profile');
        $profiles = FmProxy::getInstance()->profiles->getAllProfiles();
        $tableData = [];
        foreach ($profiles as $profile) {
            $tableData[] = [
                'id' => $profile->id,
                'title' => $profile->name,
                'handle' =>  $profile->handle,
                'enabled' => $profile->enabled,
                'endpointUrl' => $profile->getUrl(),
                'url' => $profile->getCpEditUrl(),
            ];
        }
        return $this->renderTemplate('filemaker-proxy/profiles/_index', [
            'tableData' => $tableData,
        ]);
    }

    /**
     * @throws NotFoundHttpException
     * @throws ForbiddenHttpException
     */
    public function actionEdit(int $id = null, Profile $profile = null): Response
    {
        $this->requirePermission('fmProxy-view-profile');
        if (!$profile) {
            if ($id === null) {
                $profile = new Profile();
            } else {
                $profile = FmProxy::getInstance()->profiles->getProfileById($id);
                if (!$profile) {
                    throw new NotFoundHttpException('Profiles not found');
                }
            }
        }

        $connectionOptions = [
            ['value' => null, 'label' => 'Select a connection'],
        ];

        $connections = FmProxy::getInstance()->connections->getAllConnections();
        foreach ($connections as $connection) {
            $connectionOptions[] = [
                'value' => $connection->id,
                'label' => $connection->name,
            ];
        }

        return $this->renderTemplate('filemaker-proxy/profiles/_edit', [
            'profile' => $profile, 'connectionOptions' => $connectionOptions,
        ]);
    }

    /**
     * @return Response|null
     * @throws MethodNotAllowedHttpException
     * @throws NotFoundHttpException
     * @throws \yii\db\Exception
     * @throws ForbiddenHttpException
     */
    public function actionSave(): ?Response
    {
        $this->requirePermission('fmProxy-update-profile');
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();

        $id = $request->getBodyParam('id') ?? null;
        if ($id === null) {
            $model = new Profile();
        } else {
            $model = FmProxy::getInstance()->profiles->getProfileById($id);
            if (!$model) {
                throw new NotFoundHttpException('Profiles not found');
            }
        }
        $model->name = $request->getBodyParam('name');
        $model->handle = $request->getBodyParam('handle');
        $model->connectionId = $request->getBodyParam('connectionId') ? intval($request->getBodyParam('connectionId')) : null;
        $model->layout = $request->getBodyParam('layout');
        $model->enabled = $request->getBodyParam('enabled');
        $model->endpointEnabled = $request->getBodyParam('endpointEnabled');


        if(!FmProxy::getInstance()->profiles->saveProfile($model)) {
            return $this->asFailure('Failed to save connection', [
                'success' => false,
                'errors' => $model->getErrors()
            ], [
                'profile' => $model
            ]);
        }

        return $this->asSuccess('Connection saved', [
            'profile' => $model,
        ]);
    }

    /**
     * @return Response
     * @throws BadRequestHttpException
     * @throws ForbiddenHttpException
     * @throws NotFoundHttpException
     * @throws \Throwable
     * @throws StaleObjectException
     */
    public function actionDelete(): Response
    {
        $this->requirePermission('fmProxy-delete-profile');
        $id = intval(Craft::$app->getRequest()->getRequiredBodyParam('id'));
        $profile = FmProxy::getInstance()->profiles->getProfileById($id);
        if (!$profile) {
            throw new NotFoundHttpException('Profiles not found');
        }
        if (FmProxy::getInstance()->profiles->deleteProfile($profile->id)) {
            return $this->asSuccess('Profile deleted');
        }

        return $this->asFailure('Failed to delete profile');
    }
}