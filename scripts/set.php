<?php
/**
 *
 * 排程设置redis让其进行返水操作
 *
 */

//引用系统的功能
define('S_ROOT', dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR);
require S_ROOT . 'core' . DIRECTORY_SEPARATOR . 'base.php';
ini_set('max_execution_time', '0');
ini_set('memory_limit','1024M');
lg('back_log','crontab触发返水');
$redis = initCacheRedis();
$redis->set('back_water_success',0);
lg('back_log','crontab执行后的back_water_success值::'.$redis->get('back_water_success'));
$redis->set('back_water_server_model',2);
$redis->expire('back_water_server_model',60*60*2); //一小时有效期
lg('back_log','crontab执行后的back_water_server_model值::'.$redis->get('back_water_server_model'));
deinitCacheRedis($redis);