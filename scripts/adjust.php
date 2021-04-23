<?php

$now_tiem = time();

@file_put_contents('adjust.log', "运行时间:" . date("Y-m-d H:i:s",$now_tiem) . " | time:" . $now_tiem . "\n", FILE_APPEND);

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

    //用户列表

    $sql = "SELECT id, money FROM `un_account_log` WHERE `type` = '32' AND `remark` LIKE '%现金账户调整:-%'";



    $lists = $db->getall($sql);

    if(empty($lists)){

        return true;

    }



    foreach ($lists as $v) {

        if($v['money']<0){

            continue;

        }

        $sql = "UPDATE `un_account_log` SET `money`='-{$v['money']}' WHERE (`id`={$v['id']}) LIMIT 1";

        $res = O('model')->db->query($sql);

        if(!$res){

            @file_put_contents('adjust.log', "SQL: ".$sql .  "\n", FILE_APPEND);

        }

        @file_put_contents('adjust.log', "id :" . $v['id'] . " |  " . $v['money'] .  "\n", FILE_APPEND);

    }

}



index();



//运行结束

$now_tiem1 = time();

$new_time = $now_tiem1-$now_tiem;

@file_put_contents('adjust.log', "\n\n\n运行结束时间:" . date("Y-m-d H:i:s",$now_tiem1) . " | time:" . $now_tiem1 .' 运行时长: ' .$new_time."\n\n\n", FILE_APPEND);