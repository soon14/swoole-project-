<?php
date_default_timezone_set('Asia/Singapore');
ini_set('display_errors', true);
error_reporting(E_ALL);

$configFile = end($argv);
$configJson = file_get_contents($configFile);
$configValues = json_decode($configJson, true);

include '/opt/gauss/php/Lib/Loader.php';
Lib\Loader::register('/opt/gauss/php/MODULE/PROTOCOL', 'MODULE\\PROTOCOL');

$config = new Lib\Config();
foreach ($configValues as $name => $value) {
    if (is_string($value)) {
        switch (strtolower(parse_url($value, PHP_URL_SCHEME))) {
            case 'mysql':
                $config->$name = new Lib\Data\Connection($value);
                break;
            case 'redis':
                $config->$name = new Lib\Cache($value);
                break;
            default:
                $config->$name = $value;
                break;
        }
    } else {
        $config->$name = $value;
    }
}

$daemon = new Lib\PROTOCOL\Daemon($config->cache_daemon, 'MODULE\\PROTOCOL');

$daemon->run($config);