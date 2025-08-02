<?php

namespace craftyfm\filemakerproxy\migrations;

use craft\db\Migration;
use craftyfm\filemakerproxy\db\Table;

class Install extends Migration
{
    public function safeUp(): void
    {
        $this->createTable(Table::CONNECCTIONS, [
            'id' => $this->primaryKey(),
            'uid' => $this->uid(),
            'name' => $this->string()->notNull(),
            'handle' => $this->string()->notNull(),
            'host' => $this->string()->notNull(),
            'username' => $this->string()->notNull(),
            'password' => $this->string()->notNull(),
            'database' => $this->string()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
        ]);

        $this->createTable(Table::PROFILES, [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'handle' => $this->string()->notNull(),
            'connectionId' => $this->integer()->notNull(),
            'layout' => $this->string()->notNull(),
            'endpointEnabled' => $this->boolean()->notNull(),
            'enabled' => $this->boolean()->notNull(),
            'uid' => $this->uid(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
        ]);



        // Add indexes for performance
        $this->createIndex(null, Table::CONNECCTIONS, 'handle', true); // unique index
        $this->createIndex(null, Table::CONNECCTIONS, 'uid', true); // unique index
        $this->createIndex(null, Table::PROFILES, 'handle', true);
        $this->createIndex(null, Table::CONNECCTIONS, 'uid', true);
        $this->createIndex(null, Table::PROFILES, 'uid', true);

        $this->addForeignKey(
            null,
            Table::PROFILES,
            'connectionId',
            Table::CONNECCTIONS,
            'id',
            'CASCADE',
            'CASCADE'
        );

    }

    public function safeDown(): void
    {
        $this->dropTableIfExists(Table::PROFILES);
        $this->dropTableIfExists(Table::CONNECCTIONS);
    }
}