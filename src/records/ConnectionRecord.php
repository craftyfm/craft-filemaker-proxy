<?php

namespace craftyfm\filemakerproxy\records;

use craft\db\ActiveRecord;
use craftyfm\filemakerproxy\db\Table;

/**
 * @property int $id
 * @property string $host
 * @property string $name
 * @property string $handle
 * @property string $database
 * @property string $username
 * @property string $password
 * @property string $uid
 */
class ConnectionRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return Table::CONNECCTIONS;
    }
}