<?php
/**
 * @link https://github.com/himiklab/yii2-ipgeobase-component
 * @copyright Copyright (c) 2014-2018 HimikLab
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
            'ip_begin' => $this->integer()->unsigned()->notNull(),
            'ip_end' => $this->integer()->unsigned()->notNull(),
            'country_code' => $this->string(2)->notNull(),
            'city_id' => $this->integer(6)->unsigned()->notNull()
        ]);
        $this->createIndex('ip_begin', self::DB_IP_TABLE_NAME, 'ip_begin', true);

        $this->createTable(self::DB_CITY_TABLE_NAME, [
            'id' => $this->integer(6)->unsigned()->notNull(),
            'name' => $this->string(50)->notNull(),
            'region_id' => $this->integer(6)->unsigned()->notNull(),
            'latitude' => $this->double()->notNull(),
            'longitude' => $this->double()->notNull()
        ]);
        $this->createIndex('city_id', self::DB_CITY_TABLE_NAME, 'id', true);

        $this->createTable(self::DB_REGION_TABLE_NAME, [
            'id' => $this->integer(6)->unsigned()->notNull(),
            'name' => $this->string(50)->notNull()
        ]);
        $this->createIndex('region_id', self::DB_REGION_TABLE_NAME, 'id', true);
    }

    public function down()
    {
        $this->dropTable(self::DB_IP_TABLE_NAME);
        $this->dropTable(self::DB_CITY_TABLE_NAME);
        $this->dropTable(self::DB_REGION_TABLE_NAME);
    }
}
