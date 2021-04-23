<?php

$now_time = time();
@file_put_contents('delSession.log', "运行时间:" . date("Y-m-d H:i:s",$now_time) . " | time:" . $now_time . "\n", FILE_APPEND);
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/4/27
 * Time: 19:18
 */
define('S_ROOT', dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR);
require S_ROOT . 'core' . DIRECTORY_SEPARATOR . 'base.php';
ini_set('max_execution_time', '0');
ini_set('memory_limit', '1024M');

$db = getconn();

function index()
{

    global $db;
    $nowTime = time()-86400;
    $now = $nowTime;
    //用户列表
    $sql = "SELECT * FROM `un_session` WHERE `lastvisit` < $now";
    @file_put_contents('delSession.log', date('Y-m-d H:i:s',$nowTime)." SQL: ".$sql .PHP_EOL, FILE_APPEND);
    $userList = $db->getall($sql);

    if(empty($userList)){
        return true;
    }

    foreach ($userList as $v) {
        @file_put_contents('delSession.log', "用户id :" . $v['user_id'] . " |  sessionid: ".$v['sessionid']." | lastvisit: " . $v['lastvisit'] ." | " .date('Y-m-d H:i:s',$v['lastvisit']). PHP_EOL, FILE_APPEND);
        $sql = "DELETE FROM `un_session` WHERE (`sessionid`='{$v['sessionid']}')";
        $res = $db->query($sql);
    }
}

index();


//运行结束
$now_time1 = time();
$new_time = $now_time1-$now_time;
@file_put_contents('delSession.log', "\n\n\n运行结束时间:" . date("Y-m-d H:i:s",$now_time1) . " | time:" . $now_time1 .' 运行时长: ' .$new_time."\n\n\n", FILE_APPEND);