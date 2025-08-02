<?php

namespace craftyfm\filemakerproxy\models;

use craft\base\Model;
use craft\helpers\App;
use craft\helpers\UrlHelper;
use craftyfm\filemakerproxy\records\ConnectionRecord;
use DateTime;

class Connection extends Model
{
    public ?int $id = null;
    public ?string $username = null;
    public ?string $password = null;
    public ?string $database = null;
    public ?string $host = null;
    public ?string $name = null;
    public ?string $handle = null;
    public ?string $uid = null;

    public ?DateTime $dateCreated = null;
    public ?DateTime $dateUpdated = null;

    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('filemaker-proxy/settings/connections/' . $this->id);
    }


    public function getAuthUrl(): string
    {
        $host = App::parseEnv($this->host);
        $database = App::parseEnv($this->database);
        return "https://$host/fmi/data/vLatest/databases/$database/sessions";

    }

    public function defineRules(): array
    {
        return [
            [['name', 'handle', 'database', 'host', 'username', 'password'], 'required'],
            [['name', 'handle', 'database', 'host', 'username', 'password'], 'string'],
            [['name', 'handle', 'database', 'host', 'username', 'password'], 'trim'],
            ['handle', 'match', 'pattern' => '/^[a-zA-Z0-9_-]+$/', 'message' => 'Handle can only contain letters, numbers, underscores, and hyphens.'],
            [
                'handle',
                'unique',
                'targetClass' => ConnectionRecord::class,
                'targetAttribute' => 'handle',
                'filter' => function($query) {
                    if ($this->id !== null) {
                        $query->andWhere(['not', ['id' => $this->id]]);
                    }
                },
                'message' => 'Handle must be unique.'
            ],

            [['name', 'handle'], 'string', 'max' => 255],
            ['username', 'string', 'max' => 100],
            ['password', 'string', 'max' => 255],
        ];
    }

}