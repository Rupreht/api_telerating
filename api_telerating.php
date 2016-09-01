<?php
include "../common.php";
use Stalker\Lib\Core\Mysql;
use Stalker\Lib\Core\Config;

$MAX_ROW_ITEMS = 100000;
/*

CREATE TABLE IF NOT EXISTS `api_telerating_counter` (
`id` BIGINT UNSIGNED NOT NULL)
ENGINE = INNODB;
START TRANSACTION;
LOCK TABLES api_telerating_counter WRITE, users READ, itv READ;
INSERT
    INTO `api_telerating_counter` (`id`)
    SELECT "0" FROM DUAL
    WHERE NOT EXISTS ( SELECT `id` FROM `api_telerating_counter` LIMIT 1 );
UPDATE `api_telerating_counter` SET `id` = LAST_INSERT_ID(`id` + 1);
UNLOCK TABLES;

*/

$param_api = Config::getSafe('apiserver_var', array());
$apiserver_url = Config::getSafe('apiserver_url', '');
$apiserver_debug = Config::getSafe('apiserver_debug', false);

function microtime_float() {
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
}

$time_start = microtime_float();
//set_error_handler(array($debug = Debug::getInstance(), 'parsePHPError'));


Mysql::getInstance()->query('START TRANSACTION;')->result();
Mysql::getInstance()->query('LOCK TABLES users as u READ, itv as i READ, user_log as l READ, api_telerating_counter WRITE')->result();
$counter = Mysql::getInstance()->from('api_telerating_counter')->get()->first('id');

if (!isset($counter)) {
    $counter = 1;
}

$where = "WHERE l.type IN ('0','1')";

if (isset ($counter)) {
    $where .= " and l.id > " . $counter;
}

$where .= ' ORDER BY l.id';

$query = "SELECT
    l.id as external_id,
    l.mac as device_id,
    u.ls as user_id,
    i.xmltv_id as channel_id,
    l.time as datetime,
    l.type as action
FROM
    user_log l
    LEFT JOIN users u ON l.mac=u.mac
    LEFT JOIN itv i ON l.param=i.cmd
$where
LIMIT 0, $MAX_ROW_ITEMS";

$user_log = Mysql::getInstance()->query($query)->all();

if (empty($user_log)) {
    die("Empty user_log\n\n");
}

$last_raw = end($user_log);
$counter = $last_raw{'external_id'};
reset($user_log);
$param_api['json'] = json_encode($user_log, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
$size = strlen(implode("",$param_api));

$curl = curl_init();
curl_setopt($curl, CURLOPT_URL, $apiserver_url);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 60);
curl_setopt($curl, CURLOPT_USERAGENT, 'Script TeleSmot.ru');
curl_setopt($curl, CURLOPT_POST, true);
curl_setopt($curl, CURLOPT_POSTFIELDS, $param_api);
curl_setopt($curl, CURLOPT_HTTPHEADER,
    array(
	"Content-type: multipart/form-data",
    )
);

//For Debugging 
curl_setopt($curl, CURLOPT_VERBOSE, $apiserver_debug);

$json     = curl_exec($curl);
$time_end = microtime_float();
$time     = $time_end - $time_start;
$obj      = json_decode($json, true);

if ($apiserver_debug) echo "size: $size time: $time\n";

if (isset($obj['response']['errors'][0]['code'])) {
    if ($apiserver_debug) echo sprintf('Error: [%s], Exec time: %s, Last counter: %d', $obj['response']['errors'][0]['text'], $time, $counter ) . PHP_EOL;
    Mysql::getInstance()->query('UNLOCK TABLES')->result();
    Mysql::getInstance()->query('ROLLBACK')->result();
    if ($apiserver_debug) print_r($user_log);
    exit(1);
}

Mysql::getInstance()->update(
    'api_telerating_counter',
    array(
	'id' => $counter
    )
);

if ($apiserver_debug) echo "counter: $counter\n";
