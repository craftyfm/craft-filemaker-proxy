<?php

namespace craftyfm\filemakerproxy\models;

use craft\base\Model;
use craft\helpers\App;
use craft\helpers\UrlHelper;
use craftyfm\filemakerproxy\FmProxy;
use DateTime;

class Profile extends Model
{
    public ?int $id = null;
    public string $name = '';
    public string $handle = '';
    public ?int $connectionId = null;
    public string $layout = '';
    public bool $enabled = true;
    public bool $endpointEnabled = false;
    public ?DateTime $dateCreated = null;
    public ?DateTime $dateUpdated = null;
    public ?string $uid = null;
    private ?Connection $_connection;

    public function __construct(array $config = [])
    {
        if (isset($config['uri'])) {
            unset($config['uri']);
        }
        parent::__construct($config);
    }
    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('filemaker-proxy/profiles/' . $this->id);
    }

    public function getRecordUrl(): string
    {
        $connection = $this->getConnection();
        $host = App::parseEnv($connection->host);
        $database = App::parseEnv($connection->database);
        return "https://$host/fmi/data/vLatest/databases/$database/layouts/$this->layout/records";
    }

    public function getConnection(): ?Connection
    {
        if(isset($this->_connection)) {
            return $this->_connection;
        }

        if (!$this->connectionId) {
            return null;
        }
        $this->_connection = FmProxy::getInstance()->connections->getConnectionById($this->connectionId);
        return $this->_connection;
    }

    public function getUrl(): ?string
    {
        return "http://localhost/actions/filemaker-proxy/api/middleware?profile=$this->handle";
    }

    public function defineRules(): array
    {
        return [
            [['name', 'handle', 'layout', 'connectionId'], 'required'],
            [['name', 'handle', 'layout', ], 'string'],
            [['connectionId'], 'number'],
        ];
    }
}