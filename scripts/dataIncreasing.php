<?php
$now_tiem = time();
@file_put_contents('regType.log', "运行时间:" . date("Y-m-d H:i:s",$now_tiem) . " | time:" . $now_tiem . "\n", FILE_APPEND);

define('S_ROOT', dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR);
require S_ROOT . 'core' . DIRECTORY_SEPARATOR . 'base.php';
ini_set('max_execution_time', '0');
ini_set('memory_limit', '2048M');
$config_nid = array(
    0 => 'Config:100001', //已为用户赚取元宝总数
    1 => 'Config:100002', //回扣返水赚钱率
    2 => 'Config:100003', //注册用户总数
);
$redis = initCacheRedis();
foreach ($config_nid as $v) {
    $redisData = $redis->HMGet($v,array('nid','name','value'));
    $oldData[$redisData['nid']] = $redisData;
    unset($redisData);
}
$scale = rand(92,99);
$yb = rand(4000,20000);
$regNum = rand(5,20);
$oldData['100001']['value'] = $oldData['100001']['value']+$yb;
$oldData['100002']['value'] = $scale;
$oldData['100003']['value'] = $oldData['100003']['value']+$regNum;

$db = getconn();
foreach ($oldData as $k => $v) {
    $sql = "update un_config set value = {$v['value']} where nid = {$v['nid']} ";
    $a = $db->query($sql);
    if ($a) {
        $redis->hMset("Config:".$v['nid'],$v);
    }
}

deinitCacheRedis($redis);
