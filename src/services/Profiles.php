<?php

namespace craftyfm\filemakerproxy\services;

use craftyfm\filemakerproxy\models\Profile;
use craftyfm\filemakerproxy\records\ProfileRecord;
use yii\base\Component;
use yii\db\Exception;
use yii\db\StaleObjectException;

class Profiles extends Component
{
    public function getAllProfiles(): array
    {
        $records = ProfileRecord::find()->all();
        $models = [];
        foreach ($records as $record) {
            $models[] = new Profile($record->toArray());
        }
        return $models;
    }

    public function getEnabledProfiles(): array
    {
        $records = ProfileRecord::find()->where(['enabled' => true])->all();
        $models = [];
        foreach ($records as $record) {
            $models[] = new Profile($record->toArray());
        }
        return $models;

    }
    public function getProfileByHandle(string $handle): ?Profile
    {
        $record = ProfileRecord::findOne(['handle' => $handle]);
        if (!$record) {
            return null;
        }
        return new Profile($record->toArray());
    }
    public function getProfileById(int $id): ?Profile
    {
        $record = ProfileRecord::findOne(['id' => $id]);
        if (!$record) {
            return null;
        }
        return new Profile($record->toArray());
    }

    /**
     * @throws Exception
     */
    public function saveProfile(Profile $profile, bool $runValidation = true): bool
    {
        if ($runValidation && !$profile->validate()) {
            return false;
        }

        if (!$profile->id) {
            $record = new ProfileRecord();
        } else {
            $record = ProfileRecord::findOne(['id' => $profile->id]);
            if (!$record) {
                return false;
            }
        }
        $record->name = $profile->name;
        $record->handle = $profile->handle;
        $record->connectionId = $profile->connectionId;
        $record->layout = $profile->layout;
        $record->enabled = $profile->enabled;
        $record->endpointEnabled = $profile->endpointEnabled;
        $record->save();
        $profile->id = $record->id;
        $profile->uid = $record->uid;
        return true;
    }

    /**
     * @throws StaleObjectException
     * @throws \Throwable
     */
    public function deleteProfile(int $id): bool
    {
        $record = ProfileRecord::findOne(['id' => $id]);
        if (!$record) {
            return false;
        }
        $record->delete();
        return true;
    }
}