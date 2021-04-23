<?php
$now_time = time();
@file_put_contents('dailyFlow.log', "运行时间:" . date("Y-m-d H:i:s",$now_time) . " | time:" . $now_time . "\n", FILE_APPEND);
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

function index()
{
    global $db;
    $noSql = "select sum(money) as noOpen from un_orders left join un_user on un_user.id = un_orders.user_id where un_orders.state = 0 and award_state = 0 and un_orders.reg_type not in(0,8,9,11)";
    $yeSql = "select sum(money) as yeOpen from un_orders left join un_user on un_user.id = un_orders.user_id  where un_orders.state = 0 and award_state != 0 and un_orders.reg_type not in(0,8,9,11)";
    $cancelSql = "select sum(money) as cancel from un_orders left join un_user on un_user.id = un_orders.user_id  where un_orders.state = 1 and un_orders.reg_type not in(0,8,9,11)";
    $betSql = "select sum(money) as bet from un_orders left join un_user on un_user.id = un_orders.user_id  where un_orders.state = 0 and un_orders.reg_type not in(0,8,9,11)";
    $bonusSql = "select sum(award) as bonus from un_orders left join un_user on un_user.id = un_orders.user_id  where un_orders.state = 0 and award_state = 2 and un_orders.reg_type not in(0,8,9,11)";
    $rt1 = $db->getone($noSql);
    $rt2 = $db->getone($yeSql);
    $rt3 = $db->getone($cancelSql);
    $rt4 = $db->getone($betSql);
    $rt5 = $db->getone($bonusSql);
	$data=array("noOpen" => $rt1['noOpen'], "yeOpen" => $rt2['yeOpen'], "cancel" => $rt3['cancel'], "bet" => $rt4['bet'], "bonus" => $rt5['bonus'], "gain" => $rt4['bet'] - $rt5['bonus']);
	$redis=initCacheRedis();
	$data=json_encode($data);
	$redis->set("countOrderAmount", $data);
}
index(); 
//运行结束
$now_time1 = time();
$new_time = $now_time1-$now_time;
@file_put_contents('countordermoney.log', "\n\n\n运行结束时间:" . date("Y-m-d H:i:s",$now_time1) . " | time:" . $now_time1 .' 运行时长: ' .$new_time."\n\n\n", FILE_APPEND);