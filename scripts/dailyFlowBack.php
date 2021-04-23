<?php
$now_time = time();
@file_put_contents('dailyFlowBack.log', "运行时间:" . date("Y-m-d H:i:s",$now_time) . " | time:" . $now_time . "\n", FILE_APPEND);
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/4/27
 * Time: 19:18
 */
define('S_ROOT', dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR);
require S_ROOT . 'core' . DIRECTORY_SEPARATOR . 'base.php';
ini_set('max_execution_time', '0');
ini_set(‘memory_limit’, ’1024M’);

$db = getconn();

function index($start_time,$end_time)
{
    global $db;

    $where = " addtime BETWEEN {$start_time} and {$end_time}";
    $addtime = date("Y-m-d 00:00:00",$start_time);
    //用户列表
    $sql = "SELECT DISTINCT user_id FROM un_account_log  WHERE" . $where . "  AND reg_type NOT IN (0,8,9,11)";
    $userList = $db->getall($sql);

    if(empty($userList)){
        @file_put_contents('dailyFlowBack.log', " NULL-1: ". $sql .  "\n", FILE_APPEND);
        return true;
    }

    foreach ($userList as $v) {
        //交易金额
        $sql = "SELECT type, SUM(money) AS money FROM un_account_log WHERE" . $where . " AND user_id = {$v['user_id']} GROUP BY type";
        $tradeLog = $db->getall($sql);
        if(empty($tradeLog)){
            @file_put_contents('dailyFlowBack.log'," NULL-2: ". $sql .  "\n", FILE_APPEND);
            continue;
        }

        //插入数据
        foreach ($tradeLog as $vt) {
            $sql = "INSERT INTO `un_daily_flow` (`user_id`, `money`, `type`, `addtime`) VALUES ( {$v['user_id']}, {$vt['money']}, {$vt['type']}, '{$addtime}')";
           $res = $db->query($sql);
           if(!$res){
               @file_put_contents('dailyFlowBack.log', " SQL: ".$sql .  "\n", FILE_APPEND);
           }
            @file_put_contents('dailyFlowBack.log', "用户id :" . $v['user_id'] . " |  类型: ".$vt['type']." | " . $res ." | " .$addtime. "\n", FILE_APPEND);
        }
    }
}

$sql = "SELECT addtime FROM `un_account_log` ORDER BY `id` LIMIT 0, 1";
$res = $db->result($sql);

$stime = strtotime(date("Y-m-d 00:00:00", $res));
$etime = strtotime(date("Y-m-d 23:59:59", $res));
$day_time = strtotime(date("Y-m-d 00:00:00", time()));
while (1){
    index($stime,$etime);
    $stime += 86400;
    $etime += 86400;
    if($stime>=$day_time){
        break;
    }
    sleep(2);
}

//运行结束
$now_time1 = time();
$new_time = $now_time1-$now_time;
@file_put_contents('dailyFlowBack.log', "\n\n\n运行结束时间:" . date("Y-m-d H:i:s",$now_time1) . " | time:" . $now_time1 .' 运行时长: ' .$new_time."\n\n\n", FILE_APPEND);