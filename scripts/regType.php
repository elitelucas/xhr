<?php
$now_tiem = time();
@file_put_contents('regType.log', "运行时间:" . date("Y-m-d H:i:s",$now_tiem) . " | time:" . $now_tiem . "\n", FILE_APPEND);
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/4/27
 * Time: 19:18
 */
define('S_ROOT', dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR);
require S_ROOT . 'core' . DIRECTORY_SEPARATOR . 'base.php';
ini_set('max_execution_time', '0');
ini_set(‘memory_limit’, ’2048M’);

$db = getconn();

function index($start,$end)
{
    global $db;
    //用户列表
    $sql = "select id,reg_type from un_user LIMIT {$start}, {$end}";

    $userList = $db->getall($sql);
    if(empty($userList)){
        return true;
    }
    foreach ($userList as $v) {
        $sql = "UPDATE `un_orders` SET `reg_type`={$v['reg_type']} WHERE (`user_id`={$v['id']})";
        $res = O('model')->db->query($sql);
        if(!$res){
            @file_put_contents('regType.log', "SQL: ".$sql .  "\n", FILE_APPEND);
        }
//        echo $v['id']." | :" .$v['reg_type']." <br>";
        @file_put_contents('regType.log', "用户id :" . $v['id'] . " |  " . $res .  "\n", FILE_APPEND);
    }
}

$i = 0;
$start = 0;
$end = 1000;
while (1){
    $start = $i*$end;
    $res = index($start,$end);
    if($res){
        break;
    }
    $i++;
    sleep(2);
}

//运行结束
$now_tiem1 = time();
$new_time = $now_tiem1-$now_tiem;
@file_put_contents('regType.log', "\n\n\n运行结束时间:" . date("Y-m-d H:i:s",$now_tiem1) . " | time:" . $now_tiem1 .' 运行时长: ' .$new_time."\n\n\n", FILE_APPEND);