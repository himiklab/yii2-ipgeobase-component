<?php
/**
 * @link https://github.com/himiklab/yii2-ipgeobase-component
 * @copyright Copyright (c) 2014 HimikLab
 * @license http://opensource.org/licenses/MIT MIT
 */

use yii\db\Migration;

class m141125_101036_ip_geobase extends Migration
{
    const DB_IP_TABLE_NAME = '{{%geobase_ip}}';
    const DB_CITY_TABLE_NAME = '{{%geobase_city}}';
    const DB_REGION_TABLE_NAME = '{{%geobase_region}}';

    public function up()
    {
        $this->createTable(self::DB_IP_TABLE_NAME, [
            'ip_begin' => 'INT UNSIGNED NOT NULL',
            'ip_end' => ' INT UNSIGNED NOT NULL',
            'country_code' => 'VARCHAR(2) NOT NULL',
            'city_id' => 'INT(6) UNSIGNED NOT NULL'
        ]);
        $this->createIndex('ip_begin', self::DB_IP_TABLE_NAME, 'ip_begin', true);

        $this->createTable(self::DB_CITY_TABLE_NAME, [
            'id' => 'INT(6) UNSIGNED NOT NULL',
            'name' => 'VARCHAR(50) NOT NULL',
            'region_id' => 'INT(6) UNSIGNED NOT NULL',
            'latitude' => 'DOUBLE NOT NULL',
            'longitude' => 'DOUBLE NOT NULL'
        ]);
        $this->createIndex('id', self::DB_CITY_TABLE_NAME, 'id', true);

        $this->createTable(self::DB_REGION_TABLE_NAME, [
            'id' => 'INT(6) UNSIGNED NOT NULL',
            'name' => 'VARCHAR(50) NOT NULL'
        ]);
        $this->createIndex('id', self::DB_REGION_TABLE_NAME, 'id', true);
    }

    public function down()
    {
        $this->dropTable(self::DB_IP_TABLE_NAME);
        $this->dropTable(self::DB_CITY_TABLE_NAME);
        $this->dropTable(self::DB_REGION_TABLE_NAME);
    }
}
