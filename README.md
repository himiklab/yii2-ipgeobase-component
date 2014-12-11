Yii2 IpGeoBase.ru wrapper
========================
Компонент для работы с базой IP-адресов сайта [IpGeoBase.ru](http://ipgeobase.ru/), он
реализует поиск географического местонахождения IP-адреса, выделенного RIPE локальным интернет-реестрам (LIR-ам).
Для Российской Федерации и Украины с точностью до города.

Установка
----------
Предпочтительным является способ установки через [composer](http://getcomposer.org/download/).

* Выполните команду

```
php composer.phar require --prefer-dist "himiklab/yii2-ipgeobase-component" "*"
```

или добавьте в `composer.json` в секцию `require` строку

```json
"himiklab/yii2-ipgeobase-component" : "*"
```

* Добавьте новый компонент в секцию `components` конфигурационного файла приложения:

```php
'components' => [
    'ipgeobase' => [
        'class' => 'himiklab\ipgeobase\IpGeoBase',
        'useLocalDB' => true,
    ],
    // ...
],
```

* Если хотите использовать локальную базу IP-адресов (работает на порядки быстрее чем напрямую через сайт),
то примените миграции из папки `migrations`, установите свойство компонента `useLocalDB` в `true`
и добавьте вызов метода `IpGeoBase::updateDB` в ежедневное расписание `cron`. Не забыв вызвать его однократно
для первоначального заполнения базы данных.

Использование
-------------
```php
var_dump(Yii::$app->ipgeobase->getLocation('144.206.192.6'));
var_dump(Yii::$app->ipgeobase->getLocation('144.206.192.6', false));
```
