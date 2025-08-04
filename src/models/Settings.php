<?php

namespace craftyfm\filemakerproxy\models;

use Craft;
use craft\base\Model;

/**
 * Filemaker Middleware settings
 */
class Settings extends Model
{

    public string $adminEmail = '';
    public string $token = '';

    /** @var Connection[]  */
    public array $connections = [];


    public function rules(): array
    {
        return [
            [['token'], 'required'],
            [['token'], 'string'],
        ];
    }
}
