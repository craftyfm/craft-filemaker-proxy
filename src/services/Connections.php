<?php

namespace craftyfm\filemakerproxy\services;

use Craft;
use craft\base\Component;
use craft\errors\BusyResourceException;
use craft\errors\StaleResourceException;
use craft\events\ConfigEvent;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use craftyfm\filemakerproxy\db\Table;
use craftyfm\filemakerproxy\models\Connection;
use craftyfm\filemakerproxy\records\ConnectionRecord;
use Throwable;
use yii\base\ErrorException;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\base\NotSupportedException;
use yii\db\StaleObjectException;
use yii\web\ServerErrorHttpException;

class Connections extends Component
{
    const CONFIG_KEY = 'fmproxy.connections';

    /**
     * @throws NotSupportedException
     * @throws InvalidConfigException
     * @throws ServerErrorHttpException
     * @throws StaleResourceException
     * @throws ErrorException
     * @throws Exception
     * @throws BusyResourceException
     * @throws \Exception
     */
    public function saveConnection(Connection $model, bool $runValidation = true): bool
    {
        if ($runValidation && !$model->validate()) {
            return false;
        }

        $projectConfig = Craft::$app->getProjectConfig();
        if (!$model->id) {
            $model->uid = StringHelper::UUID();
        } else {
            $model->uid = Db::uidById(Table::CONNECCTIONS, $model->id);
        }

        $configPath = self::CONFIG_KEY . '.' . $model->uid;
        $projectConfig->set($configPath, $model->toArray());

        if(!$model->id) {
            $model->id = Db::idByUid(Table::CONNECCTIONS, $model->uid);
        }
        return true;
    }

    /**
     * @throws \yii\db\Exception
     */
    public function handleChangedConnection(ConfigEvent $event): void
    {
        $uid = $event->tokenMatches[0];
        $data = $event->newValue;

        $record = ConnectionRecord::findOne(['uid' => $uid]) ?? new ConnectionRecord();
        $record->uid = $uid;
        $record->name = $data['name'];
        $record->handle = $data['handle'];
        $record->host = $data['host'];
        $record->username = $data['username'];
        $record->password = $data['password'];
        $record->database = $data['database'];
        $record->save();
    }

    public function deleteConnection(Connection $model): bool
    {
        $projectConfig = Craft::$app->getProjectConfig();
        $projectConfig->remove(self::CONFIG_KEY . '.' . $model->uid);
        return true;
    }
    /**
     * @throws Throwable
     * @throws StaleObjectException
     */
    public function handleDeletedConnection(ConfigEvent $event): void
    {
        $uid = $event->tokenMatches[0];
        $record = ConnectionRecord::findOne(['uid' => $uid]);
        $record?->delete();
    }

    public function getConnectionById(int $id): ?Connection
    {
        $record = ConnectionRecord::findOne(['id' => $id]);
        if (!$record) {
            return null;
        }
        return new Connection($record->toArray());
    }

    public function getAllConnections(): array
    {
        $records =  ConnectionRecord::find()->all();
        $models = [];
        foreach ($records as $record) {
            $models[] = new Connection($record->toArray());
        }

        return $models;
    }
}
