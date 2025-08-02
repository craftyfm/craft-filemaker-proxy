<?php

namespace craftyfm\filemakerproxy\controllers;

use Cake\Core\App;
use Craft;
use craft\errors\BusyResourceException;
use craft\errors\MissingComponentException;
use craft\errors\StaleResourceException;
use craft\web\Controller;
use craftyfm\filemakerproxy\FmProxy;
use craftyfm\filemakerproxy\models\Connection;
use craftyfm\filemakerproxy\models\Connections;
use craftyfm\filemakerproxy\records\ConnectionRecord;
use yii\base\ErrorException;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\base\NotSupportedException;
use yii\base\Response;
use yii\db\StaleObjectException;
use yii\web\BadRequestHttpException;
use yii\web\MethodNotAllowedHttpException;
use yii\web\NotFoundHttpException;
use yii\web\ServerErrorHttpException;


class ConnectionsController extends Controller
{
    public function actionIndex(): Response
    {
        $connections = FmProxy::getInstance()->connections->getAllConnections();
        $tableData = [];
        foreach ($connections as $connection) {
            $tableData[] = [
                'id' => $connection->id,
                'title' => $connection->name,
                'handle' =>  $connection->handle,
                'url' => $connection->getCpEditUrl(),
            ];
        }
        return $this->renderTemplate('filemaker-proxy/settings/connections/_index', [
            'tableData' => $tableData, 'currentPage' => 'connections'
        ]);
    }

    /**
     * @throws NotFoundHttpException
     */
    public function actionEdit(int $id = null, Connection $connection = null): Response
    {
        if (!$connection) {
            if ($id === null) {
                $connection = new Connection();
            } else {
                $connection = FmProxy::getInstance()->connections->getConnectionById($id);
                if (!$connection) {
                    throw new NotFoundHttpException('Connection not found');
                }
            }
        }

        return $this->renderTemplate('filemaker-proxy/settings/connections/_edit', [
            'connection' => $connection, 'currentPage' => 'connections'
        ]);
    }

    /**
     * @return Response
     * @throws Exception
     * @throws InvalidConfigException
     * @throws MethodNotAllowedHttpException
     * @throws BusyResourceException
     * @throws StaleResourceException
     * @throws ErrorException
     * @throws NotSupportedException
     * @throws ServerErrorHttpException
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();

        $id = $request->getBodyParam('id') ?? null;
        if ($id === null) {
            $model = new Connection();
        } else {
            $model = FmProxy::getInstance()->connections->getConnectionById($id);
            if (!$model) {
                throw new NotFoundHttpException('Connection not found');
            }
        }

        $model->handle = $request->getBodyParam('handle');
        $model->name = $request->getBodyParam('name');
        $model->host = $request->getBodyParam('host');
        $model->username = $request->getBodyParam('username');
        $model->password = $request->getBodyParam('password');
        $model->database = $request->getBodyParam('database');

        if(!FmProxy::getInstance()->connections->saveConnection($model)) {

            return $this->asFailure('Failed to save connection', [
                'success' => false,
                'errors' => $model->getErrors()
            ], [
                'connection' => $model
            ]);
        }

        return $this->asSuccess('Connection saved', [
            'connection' => $model,
        ]);
    }

    /**
     * @throws NotFoundHttpException
     */
    public function actionDelete(int $id): Response
    {
        $connection = FmProxy::getInstance()->connections->getConnectionById($id);
        if (!$connection) {
            throw new NotFoundHttpException('Connection not found');
        }

        FmProxy::getInstance()->connections->deleteConnection($connection);
        return $this->asSuccess('Connection deleted successfully.');
    }
}