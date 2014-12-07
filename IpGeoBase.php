<?php
/**
 * @link https://github.com/himiklab/yii2-ipgeobase-component
 * @copyright Copyright (c) 2014 HimikLab
 * @license http://opensource.org/licenses/MIT MIT
 */

namespace himiklab\ipgeobase;

use Yii;
use yii\base\Component;
use yii\base\Exception;

/**
 * Компонент для работы с базой IP-адресов сайта IpGeoBase.ru,
 * он Реализует поиск географического местонахождения IP-адреса,
 * выделенного RIPE локальным интернет-реестрам (LIR-ам).
 * Для Российской Федерации и Украины с точностью до города.
 *
 * @author HimikLab
 * @package himiklab\ipgeobase
 */
class IpGeoBase extends Component
{
    const XML_URL = 'http://ipgeobase.ru:7020/geo?ip=';
    const ARCHIVE_URL = 'http://ipgeobase.ru/files/db/Main/geo_files.zip';
    const ARCHIVE_IPS_FILE = 'cidr_optim.txt';
    const ARCHIVE_CITIES_FILE = 'cities.txt';

    const DB_IP_INSERTING_ROWS = 20000; // максимальный размер (строки) пакета для INSERT запроса
    const DB_IP_TABLE_NAME = '{{%geobase_ip}}';
    const DB_CITY_TABLE_NAME = '{{%geobase_city}}';
    const DB_REGION_TABLE_NAME = '{{%geobase_region}}';

    /** @var bool $useLocalDB Использовать ли локальную базу данных */
    public $useLocalDB = false;

    /**
     * Определение географического положеня по IP-адресу.
     * @param string $ip
     * @return array|bool ('country', 'city', 'region', 'lat', 'lng') или false если ничего не найдено.
     */
    public function getLocation($ip)
    {
        if ($this->useLocalDB) {
            return $this->fromDB($ip);
        } else {
            return $this->fromSite($ip);
        }
    }

    /**
     * Тест скорости получения данных из БД.
     * @param int $iterations
     * @return float IP/second
     */
    public function speedTest($iterations)
    {
        $ips = [];
        for ($i = 0; $i < $iterations; ++$i) {
            $ips[] = mt_rand(0, 255) . '.' . mt_rand(0, 255) . '.' . mt_rand(0, 255) . '.' . mt_rand(0, 255);
        }

        $begin = microtime(true);
        foreach ($ips as $ip) {
            $this->getLocation($ip);
        }
        $time = microtime(true) - $begin;

        if ($time != 0 && $iterations != 0) {
            return $iterations / $time;
        } else {
            return 0.0;
        }
    }

    /**
     * Метод создаёт или обновляет локальную базу IP-адресов.
     * @throws Exception
     */
    public function updateDB()
    {
        if (($fileName = $this->getArchive()) == false) {
            throw new Exception('Ошибка загрузки архива.');
        }
        $zip = new \ZipArchive;
        if ($zip->open($fileName) !== true) {
            @unlink($fileName);
            throw new Exception('Ошибка распаковки.');
        }

        $this->generateIpTable($zip);
        $this->generateCityTables($zip);
        $zip->close();
        @unlink($fileName);
    }

    /**
     * @param string $ip
     * @return array|bool
     */
    protected function fromSite($ip)
    {
        $xmlData = $this->getRemoteContent(self::XML_URL . urlencode($ip));
        $ipData = (new \SimpleXMLElement($xmlData))->ip;
        if (isset($ip->message)) {
            return false;
        }

        return [
            'country' => (string)$ipData->country,
            'city' => isset($ipData->city) ? (string)$ipData->city : null,
            'region' => isset($ipData->region) ? (string)$ipData->region : null,
            'lat' => isset($ipData->lat) ? (string)$ipData->lat : null,
            'lng' => isset($ipData->lng) ? (string)$ipData->lng : null
        ];
    }

    /**
     * @param string $ip
     * @return array|bool
     */
    protected function fromDB($ip)
    {
        $dbIpTableName = self::DB_IP_TABLE_NAME;
        $dbCityTableName = self::DB_CITY_TABLE_NAME;
        $dbRegionTableName = self::DB_REGION_TABLE_NAME;

        return Yii::$app->db->createCommand(
            "SELECT tIp.country_code AS country, tCity.name AS city,
                    tRegion.name AS region, tCity.latitude AS lat,
                    tCity.longitude AS lng
            FROM (SELECT * FROM {$dbIpTableName} WHERE ip_begin <= INET_ATON(:ip) ORDER BY ip_begin DESC LIMIT 1) AS tIp
            LEFT JOIN {$dbCityTableName} AS tCity ON tCity.id = tIp.city_id
            LEFT JOIN {$dbRegionTableName} AS tRegion ON tRegion.id = tCity.region_id
            WHERE INET_ATON(:ip) <= tIp.ip_end"
        )->bindValue(':ip', $ip)->queryOne();
    }

    /**
     * Метод производит заполнение таблиц городов и регионов используя
     * данные из файла self::ARCHIVE_CITIES.
     * @param $zip \ZipArchive
     * @throws \yii\db\Exception
     */
    protected function generateCityTables($zip)
    {
        $citiesArray = explode("\n", $zip->getFromName(self::ARCHIVE_CITIES_FILE));
        array_pop($citiesArray); // пустая строка

        $cities = [];
        $uniqueRegions = [];
        $regionId = 1;
        foreach ($citiesArray as $city) {
            $row = explode("\t", $city);

            $regionName = iconv('WINDOWS-1251', 'UTF-8', $row[2]);
            if (!isset($uniqueRegions[$regionName])) {
                // новый регион
                $uniqueRegions[$regionName] = $regionId++;
            }

            $cities[$row[0]][0] = $row[0]; // id
            $cities[$row[0]][1] = iconv('WINDOWS-1251', 'UTF-8', $row[1]); // name
            $cities[$row[0]][2] = $uniqueRegions[$regionName]; // region_id
            $cities[$row[0]][3] = $row[4]; // latitude
            $cities[$row[0]][4] = $row[5]; // longitude
        }

        // города
        Yii::$app->db->createCommand()->truncateTable(self::DB_CITY_TABLE_NAME)->execute();
        Yii::$app->db->createCommand()->batchInsert(
            self::DB_CITY_TABLE_NAME,
            ['id', 'name', 'region_id', 'latitude', 'longitude'],
            $cities
        )->execute();

        // регионы
        $regions = [];
        foreach ($uniqueRegions as $regionUniqName => $regionUniqId) {
            $regions[] = [$regionUniqId, $regionUniqName];
        }
        Yii::$app->db->createCommand()->truncateTable(self::DB_REGION_TABLE_NAME)->execute();
        Yii::$app->db->createCommand()->batchInsert(
            self::DB_REGION_TABLE_NAME,
            ['id', 'name'],
            $regions
        )->execute();
    }

    /**
     * Метод производит заполнение таблиц IP-адресов используя
     * данные из файла self::ARCHIVE_IPS.
     * @param $zip \ZipArchive
     * @throws \yii\db\Exception
     */
    protected function generateIpTable($zip)
    {
        $ipsArray = explode("\n", $zip->getFromName(self::ARCHIVE_IPS_FILE));
        array_pop($ipsArray); // пустая строка

        $i = 0;
        $values = '';
        $dbIpTableName = self::DB_IP_TABLE_NAME;
        Yii::$app->db->createCommand()->truncateTable($dbIpTableName)->execute();
        foreach ($ipsArray as $ip) {
            $row = explode("\t", $ip);
            $values .= '(' . (float)$row[0] .
                ',' . (float)$row[1] .
                ',' . Yii::$app->db->quoteValue($row[3]) .
                ',' . ($row[4] !== '-' ? (int)$row[4] : 0) .
                ')';
            ++$i;

            if ($i === self::DB_IP_INSERTING_ROWS) {
                Yii::$app->db->createCommand(
                    "INSERT INTO {$dbIpTableName} (ip_begin, ip_end, country_code, city_id)
                    VALUES {$values}"
                )->execute();
                $i = 0;
                $values = '';
                continue;
            }
            $values .= ',';
        }

        // оставшиеся строки не вошедшие в пакеты
        Yii::$app->db->createCommand(
            "INSERT INTO {$dbIpTableName} (ip_begin, ip_end, country_code, city_id)
            VALUES " . rtrim($values, ',')
        )->execute();
    }

    /**
     * Метод загружает архив с данными с адреса self::ARCHIVE_URL.
     * @return bool|string путь к загруженному файлу или false если файл загрузить не удалось.
     */
    protected function getArchive()
    {
        $fileData = $this->getRemoteContent(self::ARCHIVE_URL);
        if ($fileData == false) {
            return false;
        }

        $fileName = Yii::getAlias('@runtime') . DIRECTORY_SEPARATOR .
            substr(strrchr(self::ARCHIVE_URL, '/'), 1);
        if (file_put_contents($fileName, $fileData) != false) {
            return $fileName;
        }

        return false;
    }

    /**
     * Метод возвращает содержимое документа полученного по указанному url.
     * @param string $url
     * @return mixed|string
     */
    protected function getRemoteContent($url)
    {
        if (function_exists('curl_version')) {
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_HEADER => false,
                CURLOPT_RETURNTRANSFER => true
            ]);
            $data = curl_exec($curl);
            curl_close($curl);
            return $data;
        } else {
            return file_get_contents($url);
        }
    }
}
