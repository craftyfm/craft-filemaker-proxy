<?php

namespace craftyfm\filemakerproxy\records;

use craft\db\ActiveRecord;
use craftyfm\filemakerproxy\db\Table;

/**
 * @property string $name
 * @property int $id
 * @property string $handle
 * @property int $connectionId
 * @property string $layout
 * @property string $uid
 * @property bool $enabled
 * `@property bool|mixed|null $endpointEnabled
 */
class ProfileRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return Table::PROFILES;
    }
}