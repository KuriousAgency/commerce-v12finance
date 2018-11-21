<?php
/**
 * Commerce V12Finance plugin for Craft CMS 3.x
 *
 * V12 Finance plugin for Craft Commerce
 *
 * @link      https://kurious.agency
 * @copyright Copyright (c) 2018 Kurious Agency
 */

namespace kuriousagency\commerce\v12finance\migrations;

use kuriousagency\commerce\v12finance\V12finance;

use Craft;
use craft\config\DbConfig;
use craft\db\Migration;

/**
 * @author    Kurious Agency
 * @package   CommerceV12finance
 * @since     1.0.0
 */
class Install extends Migration
{
    // Public Properties
    // =========================================================================

    /**
     * @var string The database driver to use
     */
    public $driver;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        if ($this->createTables()) {
            // $this->createIndexes();
            // $this->addForeignKeys();
            // Refresh the db schema caches
            Craft::$app->db->schema->refresh();
            $this->insertDefaultData();
        }

        return true;
    }

   /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        $this->removeTables();

        return true;
    }

    // Protected Methods
    // =========================================================================

    // /**
    //  * @return bool
    //  */
    protected function createTables()
    {
        $tablesCreated = false;

        $tableSchema = Craft::$app->db->schema->getTableSchema('{{%v12finance}}');
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                '{{%v12finance}}',
                [
					'id' => $this->primaryKey(),
					'v12ProductId' => $this->integer()->notNull(),
                    'enabled' => $this->boolean()->defaultValue(false),
                    'enabledForSaleItems' => $this->boolean()->defaultValue(false),
                    'dateCreated' => $this->dateTime()->notNull(),
                    'dateUpdated' => $this->dateTime()->notNull(),
                    'uid' => $this->uid(),
                    // 'siteId' => $this->integer()->notNull(),
                    
                ]
            );
        }

        return $tablesCreated;
    }

    /**
     * @return void
     */
    // protected function createIndexes()
    // {
    //     $this->createIndex(
    //         $this->db->getIndexName(
    //             '{{%v12finance}}',
    //             'some_field',
    //             true
    //         ),
    //         '{{%v12finance}}',
    //         'some_field',
    //         true
    //     );
    //     // Additional commands depending on the db driver
    //     switch ($this->driver) {
    //         case DbConfig::DRIVER_MYSQL:
    //             break;
    //         case DbConfig::DRIVER_PGSQL:
    //             break;
    //     }
    // }

    // /**
    //  * @return void
    //  */
    // protected function addForeignKeys()
    // {
    //     $this->addForeignKey(
    //         $this->db->getForeignKeyName('{{%v12finance}}', 'siteId'),
    //         '{{%v12finance}}',
    //         'siteId',
    //         '{{%sites}}',
    //         'id',
    //         'CASCADE',
    //         'CASCADE'
    //     );
    // }

    /**
     * @return void
     */
    protected function insertDefaultData()
    {
    }

    /**
     * @return void
     */
    protected function removeTables()
    {
        $this->dropTableIfExists('{{%v12finance}}');
    }
}
