<?php
$now_time = time();
@file_put_contents('backlog.log', "运行时间:" . date("Y-m-d H:i:s",$now_tiem) . " | time:" . $now_tiem . "\n", FILE_APPEND);

//返水记录入库脚本
define('S_ROOT', dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR);
require S_ROOT . 'core' . DIRECTORY_SEPARATOR . 'base.php';
ini_set('max_execution_time', '0');
ini_set('memory_limit','2048M');

//获取DB连接
$db = getconn();
//脚本处理的数据时间范围(时间戳),昨天的0点至昨天的23点59分59秒
//脚本处理的数据时间范围(时间戳),昨天的0点至昨天的23点59分59秒
$stime = strtotime(date("Y-m-d 00:00:00", strtotime("-1 day")));

$etime = strtotime(date("Y-m-d 23:59:59", strtotime("-1 day")));
//$stime = strtotime(date("2019-04-22 00:00:00")); //测试用的，上线要去掉
//$etime = strtotime(date("2019-04-22 23:59:59")); //测试用的，上线要去掉

@file_put_contents('backlog.log', "查询起始时间:" . date("Y-m-d H:i:s", $stime) . " | time:" . $stime . "\n", FILE_APPEND);
@file_put_contents('backlog.log', "查询结束时间:" . date("Y-m-d H:i:s", $etime) . " | time:" . $etime . "\n", FILE_APPEND);

//记录当前PID
$pid = posix_getpid();
lg('back_log','scripts/redo_back.php当前脚本运行的::'.$pid);

//初始化redis
$redis = initCacheRedis();
$redis->set('redo_back_pid',$pid); //记录PID

$redis->set('back_water_success',0);
$redis->expire('back_water_success',60*60*23); //一天的有效期


//获取所有彩种
$ids  = $redis->lrange('LotteryTypeIds',0,-1);
sort($ids);
$lotteryIds = $ids;


//获取配置信息
$config_nid = array(
    0 => 'typeBack', //返水类型
    1 => '100012', //直属会员返水率
    2 => 'three_no_return_limit', //三无玩家返水限制
);
$config = array();


//获取配置
foreach ($config_nid as $v) {
    $GameConfig = $redis->HMGet("Config:" . $v, array('nid', 'name', 'value'));
    $config[$GameConfig['nid']] = $GameConfig['value'];
}


//获取代理
$Lagent = $redis->lRange('agentIds', 0, -1);
$agent = array();
foreach ($Lagent as $v) {
    $res = $redis->hGetAll("agent:" . $v);
    if($res['lottery_type']>0){
        $agent[$res['lottery_type']][] = $res;
    }
}

//获取会员层级
$LLayer = $redis->lRange('LayerIds', 0, -1);
$layer = array();
foreach ($LLayer as $v){
    $res = $redis->hGetAll("Layer:" . $v);
    $layer[$res['layer']] = $res;
}
//关闭redis链接
deinitCacheRedis($redis);

//查询房间信息
$sql = "SELECT id,backRate FROM `un_room`";
$room_list = $db->getall($sql);
$rooms = array();
foreach ($room_list as $v) {
    $rooms[$v['id']] = $v['backRate'];
}
$argv=2;
$call_back = $argv[1]?$argv[1]:2; //是否重置返水 1表示重新计算返水，2表示继续上次算返水
//var_dump('$call_back::'.$call_back);
$ydate = date("Y-m-d", $stime);
if($call_back==1 || $call_back==3){
    //重置订单返水状态sql
    $sql = "update un_orders set is_backwater = 0 where state = 0 and addtime BETWEEN {$stime} AND {$etime}";
    $db->query($sql);
    lg('back_log','重置订单返水状态::'.$sql);
    $sql = "DELETE FROM `un_back_log` WHERE addtime='{$ydate}'";
    $db->query($sql);
    lg('back_log','删除返水表记录::'.$sql);
//    $list_sql = "SELECT O.id,O.room_no,O.order_no,O.user_id,O.issue,O.money,O.way,O.award_state,O.award,O.lottery_type,T.pids FROM `un_orders` AS O INNER JOIN un_user_tree AS T ON T.user_id = O.user_id WHERE O.`addtime` BETWEEN {$stime} AND {$etime} AND O.`is_backwater` = 0 AND O.state = 0 AND O.award_state > 0";
    $list_sql = "SELECT O.id,O.room_no,O.order_no,O.user_id,O.issue,O.money,O.way,O.award_state,O.award,O.lottery_type,T.pids FROM `un_orders` AS O INNER JOIN un_user_tree AS T ON T.user_id = O.user_id WHERE O.`addtime` BETWEEN {$stime} AND {$etime} AND O.state = 0 AND O.award_state > 0";
    lg('back_log','重新算返水，SQL::'.$list_sql);

    if($call_back==1){
        die();
    }
}else if($call_back==2){ //继续未完成的
    $list_sql = "SELECT O.id,O.room_no,O.order_no,O.user_id,O.issue,O.money,O.way,O.award_state,O.award,O.lottery_type,T.pids FROM `un_orders` AS O INNER JOIN un_user_tree AS T ON T.user_id = O.user_id WHERE O.`addtime` BETWEEN {$stime} AND {$etime} AND O.state = 0 AND O.award_state > 0";
    lg('back_log','继续未完成的返水，SQL::'.$list_sql);
}else{
    echo "第一个参数只能1或者2,1表示重新计算返水，2表示继续上次算返水!";
    die();
}

//订单表
$orderList = $db->getall($list_sql);

lg('back_log','订单数量::'.count($orderList)."\n\n");

$orders = array();
$uids = array();
$ordersByUidBet = array();
$ordersByUidWin = array();
foreach ($orderList as $v) { //遍历订单
    //根据用户 区分彩种
    $ordersByUidBet[$v['user_id']][$v['lottery_type']] = bcadd($ordersByUidBet[$v['user_id']][$v['lottery_type']],$v['money'],2);  //投注金额
    $ordersByUidWin[$v['user_id']][$v['lottery_type']] = bcadd($ordersByUidWin[$v['user_id']][$v['lottery_type']],$v['award'],2);  //中奖额

    $orders[$v['user_id']][] = $v;
    $orders[$v['user_id']]['oids'][] = $v['id'];
    $uids[$v['user_id']] = $v['user_id'];
    if ($v['pids'] != ',') {
        $pids = explode(',', $v['pids']);
        array_shift($pids);
        array_pop($pids);
        foreach ($pids as $c) {
            $uids[$c] = $c;
        }
    }
}

lg('back_log','用户数量::'.count($uids).',用户集::'.var_export($uids,true)."\n\n");

$done_uids=array(); //已经计算过的用户
if($call_back==2){
    $sql = "SELECT user_id FROM `un_back_log` WHERE addtime='{$ydate}'";
    lg('back_log','进入2模式,查计算过的用户::'.$sql);
    $re = $db->getall($sql);

    $done_uids = array_column($re,'user_id'); //二维转一维

    lg('back_log','进入2模式,$done_uids::'.var_export($done_uids,1));
    $temp = array_diff($uids,$done_uids);

    lg('back_log','合并前::'.count($uids).',合并后的数据::'.count($temp)."\n");
    $uids = $temp; //整理后的数据
}

lg('backlog.log', "用户数量: " . count($uids) . "\n\n", FILE_APPEND);

//遍历用户列表   把满足条件(合计返水 > 0)的数据插入返日志表
foreach ($uids as $value) {
    $log_btime = time();

    $sql = "SELECT reg_type FROM un_user WHERE id = {$value}";
    $reg_type = $db->result($sql);
    if(in_array($reg_type,array(null,'',0,8,9,11))){
        lg('backlog.log', "时间:" . date("Y-m-d H:i:s") . "  用户注册类型: " . $value." | ".$reg_type . "\n");
        continue;
    }
    lg('backlog.log', "时间:" . date("Y-m-d H:i:s") . "  用户: " . json_encode($value) . "\n");

    if($value != 1){
        //continue;
    }


    $userId = $value; //用户ID
    $selfBack = selfBack($userId); //个人投注返水
    $sonBack = sonBack($userId); //直属投注返水

    lg('back_log',"uid::{$value},直属返水完成，进入团队返水流程\n\n");
    $teamBack = teamBack($userId); //团队投注返水
    lg('back_log',"uid::{$value},团队返水完成,进入最终的入库流程\n\n");

    //总计返水
    $array['cntBack'] = round($selfBack['selfBack'], 2) + round($sonBack['sonBack'], 2) + round($teamBack['teamBack'], 2);
    $array['cntBack'] = bcadd($array['cntBack'],0,2);
    $array['limitCntBack'] = round($selfBack['limitSelfBack'], 2) + round($sonBack['limitSonBack'], 2) + round($teamBack['limitTeamBack'], 2);
    $array['user_id'] = $userId;
    $array['selfMoney'] = ($selfBack['selfMoney']>0)?$selfBack['selfMoney']:0;
    $array['selfLose'] = ($selfBack['selfLose']>0)?$selfBack['selfLose']:0;
    $array['limitSelfMoney'] = $selfBack['limitSelfMoney'];
    $array['selfBack'] = $selfBack['selfBack'];
    $array['limitSelfBack'] = $selfBack['limitSelfBack'];
    $array['selfRate'] = $selfBack['selfRate'];
    $array['selfRateType'] = $selfBack['selfRateType'];
    $array['sonMoney'] = ($sonBack['sonBack']>0)?$sonBack['sonMoney']:0;
    $array['limitSonMoney'] = $sonBack['limitSonMoney'];
    $array['sonBack'] = $sonBack['sonBack'];
    $array['limitSonBack'] = $sonBack['limitSonBack'];
    $array['sonRate'] = $sonBack['sonRate'];
    $array['sonCnt'] = $sonBack['sonCnt'];
    $array['teamMoney'] = ($teamBack['teamBack']>0)?$teamBack['teamMoney']:0;
    $array['limitTeamMoney'] = $teamBack['limitTeamMoney'];
    $array['teamBack'] = $teamBack['teamBack'];
    $array['limitTeamBack'] = $teamBack['limitTeamBack'];
    $array['teamCnt'] = $teamBack['teamCnt'];
    $array['teamRate'] = $teamBack['teamRate'];
    $array['limitTeamRate'] = $teamBack['limitTeamRate'];
    lg('back_log','入库前的数据'.var_export(array($array),1));


    @file_put_contents('backlog.log', "总返水: " . json_encode($array['cntBack']) . " 用户返水: " . json_encode($array) . "\n\n\n", FILE_APPEND);
    addBackLog($array, $db, $stime, $etime,$orders[$value]['oids']); //返水列表入库
    $log_etime = time();
    lg('back_log','用户ID::'.$value.',结束时间::'.date('Y-m-d H:i:s',$log_etime).',耗时::'.($log_etime-$log_btime)."\n\n\n");
}

//运行结束
$now_tiem1 = time();
$new_time = $now_tiem1-$now_time;
lg('back_log','运行结束时间::'.date("Y-m-d H:i:s",$now_tiem1).',运行时长::'.$new_time);

//redis标识是否完成
$redis = initCacheRedis();
$redis->set('back_water_success',1);
lg('back_log','标识是否返水完成::'.$redis->get('back_water_success'));
$redis->set('back_water_server_model',0); //清除运行模式
lg('back_log','清除运行模式::'.$redis->get('back_water_server_model'));
deinitCacheRedis($redis);

lg('backlog.log', "\n\n\n运行结束时间:" . date("Y-m-d H:i:s",$now_tiem1) . " | time:" . $now_tiem1 .' 运行时长: ' .$new_time."\n\n\n");
/*======================================================================
========================================================================*/


//个人返水计算
function selfBack($userId) {
    global $orders;//订单表数据
    global $lotteryIds; //返水类型
    global $config;//配置信息
    global $rooms;//房间配置信息
    global $layer;//会员层级配置信息 该配置高于房间配置信息

    //结果集合
    $array = array();
    $array['selfMoney'] = 0; //个人投注-可返水投注总额
    $array['limitSelfMoney'] = 0;  //个人投注-不可返水投注总额
    $array['selfBack'] = 0; //会员返水-可返水
    $array['limitSelfBack'] = 0; //会员返水-不可返水
    $array['selfRate'] = 0;//返水比例
    $array['selfRateType'] = '0,0';//返水比例类型: 1-层级 2-房间,1-投注额 2-输分 层级反水高于房间反水

    //获取用户订单信息
    if (empty($orders[$userId])) {
        return $array;
    } else {
        $result = $orders[$userId];
    }

    $room_order = array(); //统计每个房间投注信息
    $tmp = []; //各个采种投注总额，中奖金额
    foreach ($result as $v){
        if (isset($v['lottery_type'])) {
            $tmp[$v['lottery_type']]['total_money'] += $v['money'];
            $tmp[$v['lottery_type']]['total_award'] += $v['award'];
            $room_order[$v['room_no']][] = $v;
        }
    }

    //获取用的层级
    $sql = "SELECT layer_id FROM `un_user` WHERE `id` = {$userId}";
    $layer_id = O("model")->db->result($sql);
    lg('redo_debug_1017',var_export([
        '$userId'=>$userId,
        '$sql'=>$sql,
        '$layer_id'=>$layer_id,
        '$layer'=>$layer,
    ],1));
    if(!empty($layer) && ($layer[$layer_id]['status'] == 2)){
        foreach ($lotteryIds as $key => $val) {
            //三无玩家返水限制  只限制【幸运28，加拿大28】
            $res = three_no_return_limit($userId, 1, $val); //false 有返水  true 无返水
            if (!empty($tmp[$val])) {
                if ($res) {
                    $array['limitSelfMoney'] += $tmp[$val]['total_money'];
                } else {
                    $array['selfMoney'] += $tmp[$val]['total_money'];
                }
                $array['total_money'] += $tmp[$val]['total_money'];
                $array['total_award'] += $tmp[$val]['total_award'];
            }
        }

        $layer_config = json_decode($layer[$layer_id]['config'],true); //层级返水配置

        if(!empty($layer_config)){

            if ($layer[$layer_id]['type'] == 1) { //个人返水--投注额

                $rate = 0; //返率
                foreach ($layer_config as $back) {
                    if ($array['selfMoney'] >= $back['min_money'] && $array['selfMoney'] < $back['max_money']) {
                        $rate = $back['backwater'];
                        break;
                    }
                }
                $array["selfBack"] = $array['selfMoney'] * $rate / 100;
                $array['selfRate'] = $rate;
                $array['selfRateType'] = "1,1,".$layer_id;

            } else { //个人返水--输分

                $rate = 0; //返率
                $lose = $array['total_money'] - $array['total_award'];

                if ($lose <= 0) { //输分必须大于0
                    $array["selfMoney"] = 0;
                    $array["selfLose"] = 0; //输分
                    $array["selfBack"] = 0;
                    return $array;
                }


                foreach ($layer_config as $back) {
                    if ($lose >= $back['min_money'] && $lose < $back['max_money']) {
                        $rate = $back['backwater'];
                        break;
                    }
                }

                lg('redo_debug_1017',var_export([
                    '$userId'=>$userId,
                    '$lose'=>$lose,
                    '$layer_config'=>$layer_config,
                    '$rate'=>$rate,
                ],1));


                $array["selfMoney"] += $lose;
                $array["selfLose"] += $lose; //输分
                $array["selfBack"] += $lose * $rate / 100;
                $array['selfRate'] = $rate;
                $array['selfRateType'] = "1,2,".$layer_id;

            }

        }else{

            $array["limitSelfMoney"] = 0;
            $array["limitSelfBack"] = 0;

        }
        return $array;
    }


    //房间返水
    $list = array();
    $redis = initCacheRedis();
    foreach ($room_order as $k => $v){
        $lottery_type = $redis -> hGetAll("allroom:".$k)['lottery_type'];
        $money = 0;
        $award = 0;
        $money_special = 0;
        $award_special = 0;
        $money_just = 0;
        $award_just = 0;
        foreach ($v as $vs){
            if (in_array($vs['lottery_type'],['7','8'])) {

                $a = explode("_",$vs['way']);
                if ($a[0] == "正码A") {
                    $money_just += $vs['money'];
                    $award_just += $vs['award'];
                } elseif($a[0] == "特码A") {
                    $money_special += $vs['money'];
                    $award_special += $vs['award'];
                } else {
                    $money += $vs['money'];
                    $award += $vs['award'];
                }
            } else {
                $money += $vs['money'];
                $award += $vs['award'];
            }

        }

        $arr['room_no'] = $k;
        $arr['money'] = $money;
        $arr['award'] = $award;
        $arr['lottery_type'] = $lottery_type;
        $list ['rate'][] = $arr;

        $special['room_no'] = $k;
        $special['money'] = $money_special;
        $special['award'] = $award_special;
        $special['lottery_type'] = $lottery_type;
        $list ['rate_special'][] = $special;

        $just['room_no'] = $k;
        $just['money'] = $money_just;
        $just['award'] = $award_just;
        $just['lottery_type'] = $lottery_type;
        $list ['rate_just'][] = $just;


    }
    //关闭redis链接
    deinitCacheRedis($redis);


    lg("self_back_log",'层级返水后的最终结果::'.var_export(array('$layer'=>$layer,'$array'=>$array),1));

    lg('redo_debug_1017',var_export([
        '$userId'=>$userId,
        '$list'=>$list,
    ],1));

    foreach ($list as $key => $val) {
        foreach ($val as $value) {
            $backRate = json_decode($rooms[$value['room_no']], true);
            if (empty($backRate)) {
                //$array["selfMoney"] += $value['money'];
                $array["limitSelfMoney"] += $value['money'];
                continue;
            }
            lg('redo_debug_1017',var_export([
                '$userId'=>$userId,
                '$rooms'=>$rooms,
                '$backRate'=>$backRate,
            ],1));

            $rate = 0; //返率
            $backType = 0;
            foreach ($backRate as $back) {
                if ($value['money'] > $back['lower'] && $value['money'] <= $back['upper'] && $back['type']==1) {

                    $rate = $back[$key];
                    break;
                }
            }

            $res = three_no_return_limit($userId, 1, $value['lottery_type']); //false 有返水  true 无返水
            if ($res || $rate == 0) {
                $array["limitSelfBack"] += $value['money'] * $rate / 100;
                $array["limitSelfMoney"] += $value['money'];
            } else {
                $array["selfBack"] += $value['money'] * $rate / 100;
                $array["selfMoney"] += $value['money'];
            }

            $array['selfRateType'] .= ",".$value['room_no'];

            lg("self_back_log","投注计算,用户ID::->{$userId}->彩种ID::{$value['lottery_type']}->投注总额::{$value['money']}->中奖总额::{$value['award']}->返水比例::{$rate}->三无限制::".var_export(array('$res'=>$res,'$array'=>$array),true).var_export(array('$array["selfMoney"]'=>$array["selfMoney"]),1));
        }
    }


    foreach ($list as $key => $val) {
        foreach ($val as $value) {
            $backRate = json_decode($rooms[$value['room_no']], true);
            $lose = $value['money'] - $value['award'];
            if($lose<0){
                $lose=0;
            }
            $lose = bcadd($lose,0,2);
            $rate = 0;
            $check = false;
            if ($lose > 0) { //输分 大于 0
                foreach ($backRate as $back) {
                    if ($lose > $back['lower'] && $lose <= $back['upper'] && $back['type']==2) {
                        $rate = $back[$key];
                        $check = true;
                        break;
                    }
                }
            }
            if($rate==0){
                continue;
            }
            $res = three_no_return_limit($userId, 1, $value['lottery_type']); //false 有返水  true 无返水

            if ($res === false) {
                if ($lose > 0 && !empty($backRate) && $check) {
                    $array['selfLose'] += $lose;
                } else {
                    if ($lose > 0 ){
                        $array['limitSelfMoney'] += $lose;
                    }
                }
                $array["selfBack"] += $lose * $rate / 100;
            } else {
                $array["limitSelfBack"] += $lose * $rate / 100;
            }
            $array['selfRateType'] .= ",".$value['room_no'];
            lg("self_back_log","输分计算,用户ID::->{$userId}->彩种ID::{$value['lottery_type']}->投注总额::{$value['money']}->中奖总额::{$value['award']}->盈亏::{$lose}->返水比例::{$rate}->三无限制::".var_export($res,true)."->是否有合适的返水规则::".var_export($check,true).var_export(array('$array["selfMoney"]'=>$array["selfMoney"]),1));
        }
    }


    lg('self_back_log','个人返水最终结果'.var_export(array('$userId'=>$userId,'$array'=>$array),1));

    return $array;
}

//直属返水计算
function sonBack($userId){
    global $orders;//订单数据
    global $db;
    global $config;//配置信息
    global $lotteryIds; //返水类型
    global $ordersByUidBet; //投注总额
    global $ordersByUidWin; //中奖总额

    $totalMoney = array();

    //初始化返回值
    $array = array();
    $array['sonMoney'] = 0;//投注金额
    $array['limitSonMoney'] = 0;//被限制的投注金额
    $array['sonBack'] = 0;//反水金额
    $array['limitSonBack'] = 0;//被限制的返水金额
    $array['sonCnt'] = 0;//投注人数
    $array['sonRate'] = 0;//返水比例

    $arrayTmp = array();

    $userSons = sonsList($userId); //团队ID集合

    if(count($userSons)==0){ //无直属时，不计算返水
        return $array;
    }

    $betPersonNum = count($userSons);

    //查团队人数是否够
    $effectivePerson = 0; //有效充值人数
    foreach ($userSons as $k => $tv) {
        $sql = "SELECT SUM(money) FROM `un_account_recharge` WHERE user_id={$tv['user_id']} AND `status`=1";
        $sumMoney = $db->result($sql); //有效总充值
        if($sumMoney>100){
            $effectivePerson++;
        }
        lg('back_log','直属返水结果'.var_export(array('$sumMoney'=>$sumMoney,'$sql'=>$sql),1));
    }

    lg('back_log','直属返水结果'.var_export(array('$userSons'=>$userSons,'$effectivePerson'=>$effectivePerson),1));

    if (!empty($userSons)) {
        foreach ($userSons as $k => $tv) {

            //获取用户订单信息
            if (empty($orders[$tv['user_id']])) {
                continue;
            }

            foreach ($lotteryIds as $ltk=>$ltv){
                //计算总额
                $totalMoney['bet'][$tv['user_id']][$ltv] = bcadd($totalMoney['bet'][$tv['user_id']][$ltv], $ordersByUidBet[$tv['user_id']][$ltv],2); //投注

                //输赢
                $shuTmp = bcsub($ordersByUidBet[$tv['user_id']][$ltv],$ordersByUidWin[$tv['user_id']][$ltv],2);
                $totalMoney['win'][$tv['user_id']][$ltv] = bcadd($totalMoney['win'][$tv['user_id']][$ltv], $shuTmp,2);

                //三无玩家返水限制
                $res = three_no_return_limit($tv['user_id'], 2,$ltv); //fasle 有返水  true 无返水
           
                $keyMoney = !$res?"sonMoney":"limitSonMoney";

                $arrayTmp['bet'][$keyMoney][$ltv] = bcadd($arrayTmp['bet'][$keyMoney][$ltv],$totalMoney['bet'][$tv['user_id']][$ltv],2); //投注
                $arrayTmp['win'][$keyMoney][$ltv] = bcadd($arrayTmp['win'][$keyMoney][$ltv],$totalMoney['win'][$tv['user_id']][$ltv],2); //输赢

                lg('back_log','直属返水'.var_export(array('$tv[\'user_id\']'=> $tv['user_id'],'$ltv'=>$ltv,'$tv'=>$tv,'$res'=>$res,'$keyMoney'=>$keyMoney,'$totalMoney[$tv[\'user_id\']][$ltv]'=>$totalMoney[$tv['user_id']][$ltv]),1));
            }
        }

        lg('back_log','直属返水结果'.var_export(array('$array'=>$array,'$arrayTmp'=>$arrayTmp),1));

        //团队总数
        $array['sonCnt'] = count($userSons);

        foreach ($lotteryIds as $ltk=>$ltv){

            foreach ($arrayTmp as $kk=>$vv){
                $sonRate = getTeamRate($vv['sonMoney'][$ltv],$ltv,$effectivePerson,$kk,1); //直属返率
                  if($sonRate>0){
                    $array['sonMoney'] +=$vv['sonMoney'][$ltv];
                }else{
                    $array['limitSonMoney'] +=$vv['sonMoney'][$ltv];
                }

                $array['sonBack'] = bcadd($array['sonBack'], ($vv['sonMoney'][$ltv] * $sonRate / 100),2);
                lg('back_log','直属获取返率-不受限查询'.var_export(array('$kk'=>$kk,'$ltv'=>$ltv,'有效值$vv[\'sonMoney\'][$ltv]'=>$vv['sonMoney'][$ltv],'$sonRate'=>$sonRate,'$array'=>$array,'$vv'=>$vv),1));


                $limitSonRate = getTeamRate($vv['limitSonMoney'][$ltv],$ltv,$effectivePerson,$kk,1); //直属限制三无返率
                $array['limitSonMoney'] += $vv['limitSonMoney'][$ltv];
                $array['limitSonBack'] = bcadd( $array['limitSonBack'],($vv['limitSonMoney'][$ltv] * $limitSonRate / 100),2);

                lg('back_log','直属获取返率--受限的'.var_export(array('$kk'=>$kk,'$ltv'=>$ltv,'$vv[\'limitSonMoney\'][$ltv]'=>$vv['limitSonMoney'][$ltv],'$limitSonRate'=>$limitSonRate),1));
            }
        }

    }

    lg('back_log','直属返水最终结果'.var_export(array('$userId'=>$userId,'$array'=>$array),1));

    if($array['limitSonMoney']<0){
        $array['limitSonMoney']=0;
    }

    return $array;
}

//团队返水计算
function teamBack($userId){
    global $orders;//订单数据
    global $db;
    global $config;//配置信息

    global $lotteryIds; //返水类型
    global $ordersByUidBet; //投注总额
    global $ordersByUidWin; //中奖总额

    $totalMoney = array();

    //初始化返回值
    $array = array();
    $array['teamMoney'] = 0;//投注金额
    $array['limitTeamMoney'] = 0;//被限制的投注金额
    $array['teamBack'] = 0;//反水金额
    $array['limitTeamBack'] = 0;//被限制的返水金额
    $array['teamCnt'] = 0;//投注人数
    $array['teamRate'] = 0;//返水比例

    $arrayTmp = array();
    $teamArray = array();

    $userTeams = teamLists($userId); //团队ID集合

    if(count($userTeams)==1){ //当团队只有一个人时，不计算返水
        $array['teamCnt'] = 1;
        return $array;
    }

    $betPersonNum = count($userTeams);

    //查团队符合条件的人数
    $effectivePerson = 0; //有效充值人数

    foreach ($userTeams as $k => $tv) {
        $sql = "SELECT SUM(money) FROM `un_account_recharge` WHERE user_id={$tv['user_id']} AND `status`=1";
        $sumMoney = $db->result($sql); //有效总充值
        if($sumMoney>100){
            $effectivePerson++;
        }
        lg('back_log',var_export(array('$sumMoney'=>$sumMoney,'$sql'=>$sql),1));
    }

    lg('back_log',var_export(array('$userTeams'=>$userTeams,'$effectivePerson团队'=>$effectivePerson),1));

    //下线是否投注 
    $ifTeamBet = 0;

    foreach ($userTeams as $k => $tv) {

        //获取用户订单信息
        if (empty($orders[$tv['user_id']])) {
            continue;
        }

        lg('back_uids_log',$tv['user_id']);


        if($tv['user_id'] != $userId){
            $ifTeamBet = 1;
        }

        foreach ($lotteryIds as $ltk=>$ltv){
            //计算总额

            $totalMoney['bet'][$tv['user_id']][$ltv] = bcadd($totalMoney['bet'][$tv['user_id']][$ltv], $ordersByUidBet[$tv['user_id']][$ltv],2); //投注

            //输赢

            $shuTmp = bcsub($ordersByUidBet[$tv['user_id']][$ltv],$ordersByUidWin[$tv['user_id']][$ltv],2);
            if($shuTmp<0){
                $shuTmp=0;
            }
            lg('back_log','团队计算用户本身输分'.var_export(array('彩种$ltv'=>$ltv,'用户::'=>$tv['user_id'],'投注:$ordersByUidBet[$tv[\'user_id\']][$ltv]'=>$ordersByUidBet[$tv['user_id']][$ltv],'Y分$ordersByUidWin[$tv[\'user_id\']][$ltv]'=>$ordersByUidWin[$tv['user_id']][$ltv],'$shuTmp'=>$shuTmp),1));
            $totalMoney['win'][$tv['user_id']][$ltv] = bcadd($totalMoney['win'][$tv['user_id']][$ltv], $shuTmp,2);


            //三无玩家返水限制
            $res = three_no_return_limit($tv['user_id'], 2,$ltv); //fasle 有返水  true 无返水
            $keyMoney = !$res?"teamMoney":"limitTeamMoney";

            $arrayTmp['bet'][$keyMoney][$ltv] = bcadd($arrayTmp['bet'][$keyMoney][$ltv],$totalMoney['bet'][$tv['user_id']][$ltv],2); //投注
            $arrayTmp['win'][$keyMoney][$ltv] = bcadd($arrayTmp['win'][$keyMoney][$ltv],$totalMoney['win'][$tv['user_id']][$ltv],2); //输赢


            lg('back_log','团队本身三无受限'.var_export(array('$tv[\'user_id\']'=>$tv['user_id'],'$ltv'=>$ltv,'$res'=>$res,'$keyMoney'=>$keyMoney,'$totalMoney[$tv[\'user_id\']][$ltv]'=>$totalMoney[$tv['user_id']][$ltv]),1));
        }

    }

    //团队总数
    $array['teamCnt'] = $betPersonNum;
    if($ifTeamBet ==0){ //下级没投注
        $array['teamMoney'] = 0; //投注金额
        $array['limitTeamMoney'] = 0; //被限制的投注金额
        $array['teamBack'] = 0; //反水金额
        $array['limitTeamBack'] = 0; //被限制的返水金额
        $array['teamRate'] = 0; //返水比例
        lg('back_log','下线没投注，返回空值'.var_export(array('用户ID$userId'=>$userId,'$array'=>$array),1));
        return $array;
    }

    foreach ($lotteryIds as $ltk=>$ltv){
        foreach ($arrayTmp as $kk=>$vv){
            $teamRate = getTeamRate($vv['teamMoney'][$ltv],$ltv,$effectivePerson,$kk,2); //团队返率
            if($teamRate>0){
                $array['teamMoney'] += $vv['teamMoney'][$ltv];
            }else{
                $array['limitTeamMoney'] += $vv['teamMoney'][$ltv];
            }
            lg('back_log','团队本身累加'.var_export(array('受限金额$array[\'limitTeamMoney\']'=>$array['limitTeamMoney'],'$kk'=>$kk,'$vv[\'teamMoney\'][$ltv]'=>$vv['teamMoney'][$ltv],'$array[\'teamMoney\']'=>$array['teamMoney'],'$ltv'=>$ltv,'$teamRate'=>$teamRate),1));

            $teamArray['teamBack'][$ltv][$kk] = bcadd($teamArray['teamBack'][$ltv][$kk], ($vv['teamMoney'][$ltv] * $teamRate / 100),2);

            $limitTeamRate = getTeamRate($vv['limitTeamMoney'][$ltv],$ltv,$effectivePerson,$kk,2); //团队不限制三无返率
            $array['limitTeamMoney'] += $vv['limitTeamMoney'][$ltv];
            $array['limitTeamBack'] = bcadd( $array['limitTeamBack'],($vv['limitTeamMoney'][$ltv] * $limitTeamRate / 100),2);
            lg('back_log','团队获取返率'.var_export(array('$kk'=>$kk,'$vv[\'limitTeamMoney\'][$ltv]'=>$vv['limitTeamMoney'][$ltv],'$ltv'=>$ltv,'$limitTeamRate'=>$limitTeamRate),1));

        }
    }

    lg('back_log','团队计算结果'.var_export(array('用户ID$userId'=>$userId,'$array'=>$array),1));


    /* 直属数据
     *
     * 先收集直属本身团队的所有用户总额
     * 根据彩种算出对应的返水数据
     *
     */

    $userSons = sonsList($userId); //直属id集合

    $sonArray = array();
    $totalMoney = array();

    if (!empty($userSons)) {
        foreach ($userSons as $key => $sv) {
            //直属自身的团队
            $sonTeams = teamLists($sv['user_id']); //团队ID集合

            $arrayTmp=array();
            foreach ($sonTeams as $tk => $tv) {
                //获取用户订单信息
                if (empty($orders[$tv['user_id']])) {
                    continue;
                }

                foreach ($lotteryIds as $ltk=>$ltv){
                    //计算总额

                    $totalMoney['bet'][$tv['user_id']][$ltv] = bcadd($totalMoney['bet'][$tv['user_id']][$ltv], $ordersByUidBet[$tv['user_id']][$ltv],2); //投注

                    //输赢
                    $shuTmp = bcsub($ordersByUidBet[$tv['user_id']][$ltv],$ordersByUidWin[$tv['user_id']][$ltv],2);
                    if($shuTmp<0){
                        $shuTmp=0;
                    }
                    $totalMoney['win'][$tv['user_id']][$ltv] = bcadd($totalMoney['win'][$tv['user_id']][$ltv], $shuTmp,2);

                    //三无玩家返水限制
                    $res = three_no_return_limit($tv['user_id'], 2,$ltv); //fasle 有返水  true 无返水
                    $keyMoney = !$res?"teamMoney":"limitTeamMoney";


                    $arrayTmp['bet'][$keyMoney][$ltv] = bcadd($arrayTmp['bet'][$keyMoney][$ltv],$totalMoney['bet'][$tv['user_id']][$ltv],2); //投注
                    $arrayTmp['win'][$keyMoney][$ltv] = bcadd($arrayTmp['win'][$keyMoney][$ltv],$totalMoney['win'][$tv['user_id']][$ltv],2); //输赢


                    lg('back_log','是否三无受限'.var_export(array('$tv[\'user_id\']'=>$tv['user_id'],'$ltv'=>$ltv,'$res'=>$res,'$keyMoney'=>$keyMoney,'$totalMoney[$tv[\'user_id\']][$ltv]'=>$totalMoney[$tv['user_id']][$ltv]),1));
                }
            }
            //这里计算每个直属的返水数据

            foreach ($lotteryIds as $ltk=>$ltv){

                $sonRe = 0; //当前彩种投注减输赢的结果

                foreach ($arrayTmp as $kk=>$vv){
                    $teamRate = getTeamRate($vv['teamMoney'][$ltv],$ltv,$effectivePerson,$kk,2); //团队返率
                    if($teamRate>0){
                        $sonArray['teamMoney'] += $vv['teamMoney'][$ltv];
                    }else{
                        $sonArray['limitTeamMoney'] += $vv['teamMoney'][$ltv];
                    }
                    lg('back_log','直属团队不受限获取返率'.var_export(array('$kk'=>$kk,'$vv[\'teamMoney\'][$ltv]'=>$vv['teamMoney'][$ltv],'$sonArray[\'teamMoney\']'=>$sonArray['teamMoney'],'$ltv'=>$ltv,'$teamRate'=>$teamRate),1));

                    $sonArray['teamBack'][$ltv][$kk] = bcadd($sonArray['teamBack'][$ltv][$kk], ($vv['teamMoney'][$ltv] * $teamRate / 100),2);

                    $limitTeamRate = getTeamRate($vv['limitTeamMoney'][$ltv],$ltv,$effectivePerson,$kk,2);
                    $sonArray['limitTeamMoney'] += $vv['limitTeamMoney'][$ltv];
                    $sonArray['limitTeamBack'] = bcadd( $array['limitTeamBack'],($vv['limitTeamMoney'][$ltv] * $limitTeamRate / 100),2);
                    lg('back_log','直属团队获取返率'.var_export(array('$kk'=>$kk,'$vv[\'limitTeamMoney\'][$ltv]'=>$vv['limitTeamMoney'][$ltv],'$ltv'=>$ltv,'$limitTeamRate'=>$limitTeamRate,'$sonRe'=>$sonRe),1));

                }
            }
        }
    }
    lg('back_log','团队和直属计算结果'.var_export(array('用户ID$userId'=>$userId,'$array'=>$array,'$sonArray'=>$sonArray,'$teamArray'=>$teamArray),1));


    foreach ($lotteryIds as $ltk=>$ltv){
        foreach(array('bet','win') as $vv){
            $re =  $teamArray['teamBack'][$ltv][$vv] - $sonArray['teamBack'][$ltv][$vv];
            if($re<0){
                $re=0;
            }
            $array['teamBack'] = bcadd($array['teamBack'],$re,2); //反水金额
        }
    }

    if($array['limitTeamMoney']<0){
        $array['limitTeamMoney']=0;
    }

    $array['limitTeamBack'] = bcsub($array['limitTeamBack'],$sonArray['limitTeamBack'],2); //反水金额

    if($array['limitTeamBack']<0){
        $array['limitTeamBack']=0;
    }

    $array['teamRate'] = 0;

    $array['limitTeamRate'] = 0;

    return $array;
}

//返水列表入库
function addBackLog($value, $db, $stime, $etime,$oids){
    global $ydate;
    $value['yesdayBack'] = yesdayBack($value['user_id'], $db);
    $value['state'] = 1;
    $value['addtime'] = date("Y-m-d", $stime);
    $rt = $db->insert("un_back_log", $value);
    if ($rt) { //记录插入成功,更新订单表已返水字段
        $order_ids = @implode(',',$oids);
        if(!empty($oids)){
            $sql  = "update un_orders set is_backwater = 1 where id in ({$order_ids})";
            lg('back_log',"优化SQL::".$sql);
            $db->query($sql);
        }
    }else{
        @file_put_contents('backlog.log', "返水失败: " . $value['user_id'] . " 用户返水: " . json_encode($value) . "\n", FILE_APPEND);
    }
}

//根据团队投注金额,返回团队代理等级对应的返率
function getTeamRate($money,$lottery_type,$effectivePerson=0,$type,$sonTeam){
    global $agent;

    if(!in_array($type,array('bet','win'))){
        return 0;
    }

    if($type=='bet'){
        $back_type = 1;
    }else{
        $back_type = 2;
    }

    
    foreach ($agent[$lottery_type] as $v) {
        if ($back_type==$v['back_type'] && $v['son_team']==$sonTeam && $money > $v['lower']  && $money <= $v['upper'] && $effectivePerson>=$v['effective_person']) {
            lg('back_log',var_export(array('$lottery_type'=>$lottery_type,'$effectivePerson'=>$effectivePerson,'$v[\'effective_person\']'=>$v['effective_person'],'$money'=>$money,'$type'=>$type,'$sonTeam'=>$sonTeam,'$v'=>$v),1));
            return $v['backwater'];
        }
    }

    return 0;
}

//会员昨日返水
function yesdayBack($user_id, $db){
    $day = date("Y-m-d", strtotime('-2 day'));
    $rt = $db->getone("select cntBack from un_back_log where user_id = {$user_id} and addtime = '{$day}'");
    return empty($rt['cntBack']) ? 0 : $rt['cntBack'];
}

//团队集合ID  包含自身
function teamLists($userId){
    global $db;
    $sql = "SELECT user_id FROM `un_user_tree` WHERE `pids` LIKE '%,{$userId},%' ";
    $res = $db->getall($sql);
    $self = array('user_id' => $userId);
    if (empty($res)) {
        return array($self);
    } else {
        array_push($res, $self);
        return $res;
    }
}

//直属id集合
function sonsList($userId){
    global $db;
    $sql = "SELECT id as user_id FROM `un_user` WHERE `parent_id` = {$userId}";
    $res = $db->getall($sql);
    return $res;
}

//三无玩家返水限制
function three_no_return_limit($userId, $type, $lottery_type) {
    global $orders;//订单数据
    global $config;//配置信息

    if (!in_array($lottery_type,['1','3'])){
        return false;
    }
    if ($lottery_type == 1) {
        $lottery_name = "xy28";
    } elseif($lottery_type == 3) {
        $lottery_name = "jnd28";
    }

    if (!empty($config['three_no_return_limit'])) {

        $three_no = json_decode($config['three_no_return_limit'], true);

        foreach ($three_no as $val) {
            if ($val['setType'] == $type) {
                $three_no_config = $val;
            }

        }
        
        lg("back_debug","三无返水配置信息::".var_export($three_no_config,true));

        //该用户订单数据
        if (!isset($orders[$userId])) {
            return false;
        } else {
            $result = $orders[$userId];
        }

        //三无限制规则1
        if (!empty($three_no_config['condition1'])) {

            $amount = 0;
            $toTalMoney = 0;
            $ways = [
                'way1'=>['大双', '大单', '小双', '小单'],//组合
                'way2'=>['极大', '极小'],//极值
                'way3'=>[
                    '0','1','2','3','4','5','6','7','8','9','10','11', '12','13','14',
                    '15','16','17','18','19','20','21', '22','23','24','25','26','27'
                ],//单点
                'way4'=>['红','绿','蓝','豹子','正顺','对子','倒顺','半顺','乱顺']//特殊
            ];

            foreach ($result as $val) {

                if (isset($val['way']) && $val['way'] == "") {
                    lg("back_debug","玩法不存在::{$val['way']}->订单主键::{$val['id']}->金额::{$val['money']}->用户ID::{$val['user_id']}->彩种::{$lottery_name}");
                    continue;
                }

                $cof_type = explode(",", $three_no_config['condition1'][$lottery_name]['type']);
                $cof_amount = $three_no_config['condition1'][$lottery_name]['amount'];

                if ($val['lottery_type'] == $lottery_type) {

                    $toTalMoney += $val['money'];

                    foreach ($cof_type as $v) {

                        if ($v == 1 && in_array($val['way'],$ways['way1'])) {
                            lg("back_debug","订单号::{$val['order_no']}->订单主键::{$val['id']}->玩法::{$val['way']}->金额::{$val['money']}->用户ID::{$val['user_id']}->彩种::{$lottery_name}->玩法集合::".json_encode($ways['way1'],JSON_UNESCAPED_UNICODE));
                            $amount += $val['money'];
                        }

                        if ($v == 2 && in_array($val['way'],$ways['way2'])) {
                            lg("back_debug","订单号::{$val['order_no']}->订单主键::{$val['id']}->玩法::{$val['way']}->金额::{$val['money']}->用户ID::{$val['user_id']}->彩种::{$lottery_name}->玩法集合::".json_encode($ways['way2'],JSON_UNESCAPED_UNICODE));
                            $amount += $val['money'];
                        }

                        if ($v == 3 && in_array($val['way'],$ways['way3'])) {
                            lg("back_debug","订单号::{$val['order_no']}->订单主键::{$val['id']}->玩法::{$val['way']}->金额::{$val['money']}->用户ID::{$val['user_id']}->彩种::{$lottery_name}->玩法集合::".json_encode($ways['way3'],JSON_UNESCAPED_UNICODE));
                            $amount += $val['money'];
                        }

                        if ($v == 4 && in_array($val['way'],$ways['way4'])) {
                            lg("back_debug","订单号::{$val['order_no']}->订单主键::{$val['id']}->玩法::{$val['way']}->金额::{$val['money']}->用户ID::{$val['user_id']}->彩种::{$lottery_name}->玩法集合::".json_encode($ways['way4'],JSON_UNESCAPED_UNICODE));
                            $amount += $val['money'];
                        }

                    }

                }else{
                    lg("back_debug","彩种不相同,订单号::{$val['order_no']}->订单主键::{$val['id']}->玩法::{$val['way']}->金额::{$val['money']}->用户ID::{$val['user_id']}->彩种::{$lottery_name}->玩法集合::".json_encode($ways['way4'],JSON_UNESCAPED_UNICODE).var_export(array('$val[\'lottery_type\']'=>$val['lottery_type'],'$lottery_type'=>$lottery_type),1));
                }

            }

            $aaa = bcadd(($toTalMoney * intval($cof_amount) / 100),0,2);//昨天总投注额的百分比值

            lg("back_debug","当天投注总额::{$toTalMoney}->当天【{$lottery_name}】三无限制玩法投注总额::{$amount}");

            //bccomp($a, $b, 2) == 0
            if (bccomp($amount , $aaa,2) == -1) {
                lg("back_debug","用户ID::{$userId},被【{$lottery_name}】三无规则1限制住，不返水");
                return true;
            }

        }

        //三无限制规则2
        if (!empty($three_no_config['condition2'])) {

            $qiHao = [];

            foreach ($result as $val) {

                if ($val['lottery_type'] == $lottery_type) {

                    if (array_key_exists('issue',$val) && !in_array($val['issue'], $qiHao)) {
                        $qiHao[] = $val['issue'];
                    }

                }

            }

            lg("back_debug","用户ID::{$userId},【{$lottery_name}】当天投注总注数::".count($qiHao));

            if(count($qiHao) < intval($three_no_config['condition2'][$lottery_name])) {
                lg("back_debug","用户ID::{$userId},被【{$lottery_name}】三无规则2限制住，不返水");
                return true;
            }

        }

    }
    lg("back_debug","用户ID::{$userId},没有被三无规则限制住，有返水");
    return false;
}