<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/11/17
 * Time: 11:12
 */
//引入系统
define('S_ROOT', dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR);
require S_ROOT . 'core' . DIRECTORY_SEPARATOR . 'base.php';

//redis取出数据
if (empty($argv[1])) {
    $redis = initCacheRedis();
    $re = $redis->rPop("list");
//    lg("person","redis取出数据：".var_export($re,true)."------开始执行");
    deinitCacheRedis($redis);
} else {
    $re = $argv[1];
//    lg("person","手动取出数据：".var_export($re,true)."------开始执行");
}


//获取参数
if (!empty($re)) {
    $re = explode(':',$re);
    $conf_id = $re[0];
    $state = $re[1];
    $type = $re[2];
    lg("person","取出数据->配置ID::{$conf_id} 状态::{$state} 类型::{$type}");

}

if($conf_id == ""){
    exit;
}

//设置php系统变量
ini_set('max_execution_time', '0');
ini_set('memory_limit','2048M');

//获取DB连接
$personDb = getconn();

//停止的时候删除数据
if ($state == 0) {
    if ($type == 1) {
        $sql = "delete from un_bet_list where conf_id = {$conf_id}";
        $rs = $personDb->query($sql);
        lg("person","停止投注机器人删除数据: 执行sql->{$sql}  执行结果：".var_export($rs,true));
    } elseif ($type == 2) {
        $sql = "delete from un_barrage_auto where conf_id = {$conf_id}";
        $rs = $personDb->query($sql);
        lg("person","停止飘窗机器人删除数据: 执行sql->{$sql}  执行结果：".var_export($rs,true));
    }
}

//读取配置
$sql = "select * from un_person_config where id = {$conf_id} and state = 1";
lg("person","执行sql语句->{$sql}");
$rows = $personDb->getone($sql);
lg("person","获取的配置信息->".var_export($rows,true));
if (!empty($rows)) {
    if ($rows['type'] == 1) { //投注机器人

        //灌数据
        $config = json_decode($rows['value'],true);
        $bet['conf_id'] = $rows['id'];
        $bet['room_id'] = $config['room'];
        $bet['lottery_type'] = $config['lottery_type'];

        $wayArr = null;
        if(in_array($bet['lottery_type'],['1','3'])) {
            $wayArr = getWayInfo();
        } elseif(in_array($bet['lottery_type'],['2','4','9','14'])) {
            $wayArr = getPK10WayInfo();
        } elseif(in_array($bet['lottery_type'],['5','6','11'])) {
            $wayArr = getSscWayInfo();
        } elseif(in_array($bet['lottery_type'],['7','8'])) {
            $wayArr = getLiuheWayInfo();
        } elseif(in_array($bet['lottery_type'],['10'])) {
            $wayArr = getNnWayInfo();
        } elseif(in_array($bet['lottery_type'],['12'])) {
            $wayArr = getCupWayInfo();
        } elseif(in_array($bet['lottery_type'],['13'])) {
            $wayArr = getSbWayInfo();
        }

        //跨天处理
        if ($config['startTime'] > $config['endTime']){
            $startTime = strtotime('today') + $config['startTime']*3600;
            $endTime =  strtotime('today +1 day') + $config['endTime']*3600;
        } else {
            $startTime = strtotime('today') + $config['startTime']*3600;
            $endTime =  strtotime('today') + $config['endTime']*3600;
        }

        $timeArr = range($startTime,$endTime);//下注时间集合

        if($config['num']['type'] == 1) {
            $count = $config['num']['data']/count($config['ids']);
            for($a=1;$a<=$count;$a++) {
                shuffle($timeArr);
                if($config['money']['type'] == 1) {
                    foreach($config['ids'] as $v) {
                        $moneyArr = [];
                        if($config['multiple'] == 1) {//判断下注金额是否开启5的倍数
                            $startNum = getBetStartMoney($config['money']['data']['start_money']);
                            if($config['money']['data']['start_money'] == $config['money']['data']['end_money'] || $config['money']['data']['end_money'] < $startNum) {
                                for($x=$startNum;$x<=getBetStartMoney($config['money']['data']['start_money'])+5;$x+=5) {
                                    $moneyArr[] = $x;
                                }
                            } else if($config['money']['data']['end_money'] < 5){
                                $moneyArr[] = 5;
                            } else {
                                for($x=$startNum;$x<=$config['money']['data']['end_money'];$x+=5) {
                                    $moneyArr[] = $x;
                                }
                            }
                        } else {
                            $moneyArr = range($config['money']['data']['start_money'],$config['money']['data']['end_money']);
                        }
                        shuffle($moneyArr);
                        $key = array_rand($timeArr,1);
                        $g = get_rand($config['way']);//确定玩法类型
                        shuffle($wayArr[$g]);
                        $bet['way'] = $wayArr[$g][array_rand($wayArr[$g],1)];
                        if (in_array($bet['lottery_type'],[2,4,9,14])) {
                            if ($g == 5){
                                //如果是猜冠亚，则组装玩法
                                $a1 = $wayArr[$g][array_rand($wayArr[$g])];
                                $a2 = $wayArr[$g][array_rand($wayArr[$g])];
                                while($a1 == $a2) {
                                    $a2 = $wayArr[$g][array_rand($wayArr[$g])];
                                }
                                $bet['way'] = "冠亚_{$a1}_{$a2}";
                            }
                        } elseif (in_array($bet['lottery_type'],[7,8])) {
                            //连尾
                            $arr = explode("_",$bet['way']);
                            if ($g == 4){ //连码
                                $bet['way'] = $arr[0]."_".implode(",",getXXX(range(4,10),range(1,49)));
                            } elseif ($g == 9) { //连肖
                                $sheng_xiao_arr = ['猪', '狗', '鸡', '猴', '羊', '马', '蛇', '龙', '兔', '虎', '牛', '鼠',];
                                $bet['way'] = $arr[0]."_".implode(",",getXXX(range(4,8),$sheng_xiao_arr));
                            } elseif ($g == 11) { //不中
                                if (in_array($arr[0],['五不中','六不中'])) {
                                    $bet['way'] = $arr[0]."_".implode(",",getXXX(range(6,8),range(1,49)));
                                } elseif (in_array($arr[0],['七不中','八不中'])) {
                                    $bet['way'] = $arr[0]."_".implode(",",getXXX(range(8,10),range(1,49)));
                                } elseif (in_array($arr[0],['九不中','十不中'])) {
                                    $bet['way'] = $arr[0]."_".implode(",",getXXX(range(10,12),range(1,49)));
                                }
                            } elseif ($g == 10) {
                                $lian_wei_arr = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
                                $bet['way'] = $arr[0]."_".implode(",",getXXX(range(4,8),$lian_wei_arr));
                            }
                        }
                        $bet['bet_time'] = $timeArr[$key];
                        $bet['user_id'] = $v['id'];
                        $bet['avatar'] = $v['avatar'];
                        $bet['username'] = $v['username'];
                        $bet['nickname'] = $v['nickname'];
                        $bet['bet_money'] = $moneyArr[array_rand($moneyArr,1)];
                        $rows = $personDb->insert("un_bet_list",$bet);
                        unset($timeArr[$key]);
                    }
                } else {
                    foreach($config['money']['data'] as $v) {
                        $moneyArr = [];
                        if($config['multiple'] == 1) {
                            $startNum = getBetStartMoney($v['money_start']);
                            if($v['money_start'] == $v['money_end'] || $v['money_end'] < $startNum) {
                                for($x=$startNum;$x<=$startNum+5;$x+=5) {
                                    $moneyArr[] = $x;
                                }
                            } else if ($v['money_end'] < 5){
                                $moneyArr[] = 5;
                            } else {
                                for($x=$startNum;$x<=$v['money_end'];$x+=5) {
                                    $moneyArr[] = $x;
                                }
                            }
                        } else {
                            $moneyArr = range($v['money_start'],$v['money_end']);
                        }
                        shuffle($moneyArr);
                        $key = array_rand($timeArr,1);
                        $g = get_rand($config['way']);//确定玩法类型
                        shuffle($wayArr[$g]);
                        $bet['way'] = $wayArr[$g][array_rand($wayArr[$g],1)];
                        if (in_array($bet['lottery_type'],[2,4,9,14])) {
                            if ($g == 5){
                                //如果是猜冠亚，则组装玩法
                                $a1 = $wayArr[$g][array_rand($wayArr[$g])];
                                $a2 = $wayArr[$g][array_rand($wayArr[$g])];
                                while($a1 == $a2) {
                                    $a2 = $wayArr[$g][array_rand($wayArr[$g])];
                                }
                                $bet['way'] = "冠亚_{$a1}_{$a2}";
                            }
                        } elseif (in_array($bet['lottery_type'],[7,8])) {
                            //连尾
                            $arr = explode("_",$bet['way']);
                            if ($g == 4){ //连码
                                $bet['way'] = $arr[0]."_".implode(",",getXXX(range(4,10),range(1,49)));
                            } elseif ($g == 9) { //连肖
                                $sheng_xiao_arr = ['猪', '狗', '鸡', '猴', '羊', '马', '蛇', '龙', '兔', '虎', '牛', '鼠',];
                                $bet['way'] = $arr[0]."_".implode(",",getXXX(range(4,8),$sheng_xiao_arr));
                            } elseif ($g == 11) { //不中
                                if (in_array($arr[0],['五不中','六不中'])) {
                                    $bet['way'] = $arr[0]."_".implode(",",getXXX(range(6,8),range(1,49)));
                                } elseif (in_array($arr[0],['七不中','八不中'])) {
                                    $bet['way'] = $arr[0]."_".implode(",",getXXX(range(8,10),range(1,49)));
                                } elseif (in_array($arr[0],['九不中','十不中'])) {
                                    $bet['way'] = $arr[0]."_".implode(",",getXXX(range(10,12),range(1,49)));
                                }
                            } elseif ($g == 10) {
                                $lian_wei_arr = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
                                $bet['way'] = $arr[0]."_".implode(",",getXXX(range(4,8),$lian_wei_arr));
                            }
                        }
                        $bet['bet_time'] = $timeArr[$key];
                        $bet['user_id'] = $v['id'];
                        $bet['username'] = $v['username'];
                        $bet['nickname'] = $v['nickname'];
                        $bet['avatar'] = $v['avatar'];
                        $bet['bet_money'] = $moneyArr[array_rand($moneyArr,1)];
                        $rows = $personDb->insert("un_bet_list",$bet);
                        unset($timeArr[$key]);
                    }
                }
            }
        } elseif ($config['num']['type'] == 2) {
            $count = $config['num']['data'];
            for($a=1;$a<=$count;$a++) {
                shuffle($timeArr);
                if($config['money']['type'] == 1) {
                    foreach($config['ids'] as $v) {
                        $moneyArr = [];
                        if($config['multiple'] == 1) {
                            $startNum = getBetStartMoney($config['money']['data']['start_money']);
                            if($config['money']['data']['start_money'] == $config['money']['data']['end_money'] || $config['money']['data']['end_money'] < $startNum){
                                for($x=$startNum;$x<=$startNum+5;$x+=5) {
                                    $moneyArr[] = $x;
                                }
                            } else if ($config['money']['data']['end_money'] < 5){
                                $moneyArr[] = 5;
                            }else{
                                for($x=$startNum;$x<=$config['money']['data']['end_money'];$x+=5) {
                                    $moneyArr[] = $x;
                                }
                            }
                        } else {
                            $moneyArr = range($config['money']['data']['start_money'],$config['money']['data']['end_money']);
                        }
                        shuffle($moneyArr);
                        $key = array_rand($timeArr,1);
                        $g = get_rand($config['way']);//确定玩法类型
                        shuffle($wayArr[$g]);
                        $bet['way'] = $wayArr[$g][array_rand($wayArr[$g],1)];
                        if (in_array($bet['lottery_type'],[2,4,9,14])) {
                            if ($g == 5){
                                //如果是猜冠亚，则组装玩法
                                $a1 = $wayArr[$g][array_rand($wayArr[$g])];
                                $a2 = $wayArr[$g][array_rand($wayArr[$g])];
                                while($a1 == $a2) {
                                    $a2 = $wayArr[$g][array_rand($wayArr[$g])];
                                }
                                $bet['way'] = "冠亚_{$a1}_{$a2}";
                            }
                        } elseif (in_array($bet['lottery_type'],[7,8])) {
                            //连尾
                            $arr = explode("_",$bet['way']);
                            if ($g == 4){ //连码
                                $bet['way'] = $arr[0]."_".implode(",",getXXX(range(4,10),range(1,49)));
                            } elseif ($g == 9) { //连肖
                                $sheng_xiao_arr = ['猪', '狗', '鸡', '猴', '羊', '马', '蛇', '龙', '兔', '虎', '牛', '鼠',];
                                $bet['way'] = $arr[0]."_".implode(",",getXXX(range(4,8),$sheng_xiao_arr));
                            } elseif ($g == 11) { //不中
                                if (in_array($arr[0],['五不中','六不中'])) {
                                    $bet['way'] = $arr[0]."_".implode(",",getXXX(range(6,8),range(1,49)));
                                } elseif (in_array($arr[0],['七不中','八不中'])) {
                                    $bet['way'] = $arr[0]."_".implode(",",getXXX(range(8,10),range(1,49)));
                                } elseif (in_array($arr[0],['九不中','十不中'])) {
                                    $bet['way'] = $arr[0]."_".implode(",",getXXX(range(10,12),range(1,49)));
                                }
                            } elseif ($g == 10) {
                                $lian_wei_arr = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
                                $bet['way'] = $arr[0]."_".implode(",",getXXX(range(4,8),$lian_wei_arr));
                            }
                        }
                        $bet['bet_time'] = $timeArr[$key];
                        $bet['user_id'] = $v['id'];
                        $bet['avatar'] = $v['avatar'];
                        $bet['username'] = $v['username'];
                        $bet['nickname'] = $v['nickname'];
                        $bet['bet_money'] = $moneyArr[array_rand($moneyArr,1)];
                        $rows = $personDb->insert("un_bet_list",$bet);
                        unset($timeArr[$key]);
                    }
                } else {
                    foreach($config['money']['data'] as $v) {
                        $moneyArr = [];
                        if($config['multiple'] == 1) {
                            $startNum = getBetStartMoney($v['money_start']);
                            if($v['money_start'] == $v['money_end'] || $v['money_end'] < $startNum){
                                for($x=$startNum;$x<=$startNum+5;$x+=5) {
                                    $moneyArr[] = $x;
                                }
                            } else if($v['money_end'] < 5){
                                $moneyArr[] = 5;
                            }else{
                                for($x=$startNum;$x<=$v['money_end'];$x+=5) {
                                    $moneyArr[] = $x;
                                }
                            }
                        } else {
                            $moneyArr = range($v['money_start'],$v['money_end']);
                        }
                        shuffle($moneyArr);
                        $key = array_rand($timeArr,1);
                        $g = get_rand($config['way']);//确定玩法类型
                        shuffle($wayArr[$g]);
                        $bet['way'] = $wayArr[$g][array_rand($wayArr[$g],1)];
                        if (in_array($bet['lottery_type'],[2,4,9,14])) {
                            if ($g == 5){
                                //如果是猜冠亚，则组装玩法
                                $a1 = $wayArr[$g][array_rand($wayArr[$g])];
                                $a2 = $wayArr[$g][array_rand($wayArr[$g])];
                                while($a1 == $a2) {
                                    $a2 = $wayArr[$g][array_rand($wayArr[$g])];
                                }
                                $bet['way'] = "冠亚_{$a1}_{$a2}";
                            }
                        } elseif (in_array($bet['lottery_type'],[7,8])) {
                            //连尾
                            $arr = explode("_",$bet['way']);
                            if ($g == 4){ //连码
                                $bet['way'] = $arr[0]."_".implode(",",getXXX(range(4,10),range(1,49)));
                            } elseif ($g == 9) { //连肖
                                $sheng_xiao_arr = ['猪', '狗', '鸡', '猴', '羊', '马', '蛇', '龙', '兔', '虎', '牛', '鼠',];
                                $bet['way'] = $arr[0]."_".implode(",",getXXX(range(4,8),$sheng_xiao_arr));
                            } elseif ($g == 11) { //不中
                                if (in_array($arr[0],['五不中','六不中'])) {
                                    $bet['way'] = $arr[0]."_".implode(",",getXXX(range(6,8),range(1,49)));
                                } elseif (in_array($arr[0],['七不中','八不中'])) {
                                    $bet['way'] = $arr[0]."_".implode(",",getXXX(range(8,10),range(1,49)));
                                } elseif (in_array($arr[0],['九不中','十不中'])) {
                                    $bet['way'] = $arr[0]."_".implode(",",getXXX(range(10,12),range(1,49)));
                                }
                            } elseif ($g == 10) {
                                $lian_wei_arr = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
                                $bet['way'] = $arr[0]."_".implode(",",getXXX(range(4,8),$lian_wei_arr));
                            }
                        }
                        $bet['bet_time'] = $timeArr[$key];
                        $bet['user_id'] = $v['id'];
                        $bet['username'] = $v['username'];
                        $bet['nickname'] = $v['nickname'];
                        $bet['avatar'] = $v['avatar'];
                        $bet['bet_money'] = $moneyArr[array_rand($moneyArr,1)];
                        $rows = $personDb->insert("un_bet_list",$bet);
                        unset($timeArr[$key]);
                    }
                }
            }
        } elseif ($config['num']['type'] == 3) {
            foreach($config['num']['data'] as $keys=>$val) {
                $count = $val['num'];
                for($a=1;$a<=$count;$a++) {
                    $moneyArr = [];
                    shuffle($timeArr);
                    if($config['money']['type'] == 1) {
                        if($config['multiple'] == 1) {
                            $startNum = getBetStartMoney($config['money']['data']['start_money']);
                            if($config['money']['data']['start_money'] == $config['money']['data']['end_money'] || $config['money']['data']['end_money'] < $startNum){
                                for($x=$startNum;$x<=$startNum+5;$x+=5) {
                                    $moneyArr[] = $x;
                                }
                            } else if($config['money']['data']['end_money'] < 5) {
                                $moneyArr[] = 5;
                            }else{
                                for($x=$startNum;$x<=$config['money']['data']['end_money'];$x+=5) {
                                    $moneyArr[] = $x;
                                }
                            }
                        } else {
                            $moneyArr = range($config['money']['data']['start_money'],$config['money']['data']['end_money']);
                        }
                        shuffle($moneyArr);
                        $key = array_rand($timeArr,1);
                        $g = get_rand($config['way']);//确定玩法类型
                        shuffle($wayArr[$g]);
                        $bet['way'] = $wayArr[$g][array_rand($wayArr[$g],1)];
                        if (in_array($bet['lottery_type'],[2,4,9,14])) {
                            if ($g == 5){
                                //如果是猜冠亚，则组装玩法
                                $a1 = $wayArr[$g][array_rand($wayArr[$g])];
                                $a2 = $wayArr[$g][array_rand($wayArr[$g])];
                                while($a1 == $a2) {
                                    $a2 = $wayArr[$g][array_rand($wayArr[$g])];
                                }
                                $bet['way'] = "冠亚_{$a1}_{$a2}";
                            }
                        } elseif (in_array($bet['lottery_type'],[7,8])) {
                            //连尾
                            $arr = explode("_",$bet['way']);
                            if ($g == 4){ //连码
                                $bet['way'] = $arr[0]."_".implode(",",getXXX(range(4,10),range(1,49)));
                            } elseif ($g == 9) { //连肖
                                $sheng_xiao_arr = ['猪', '狗', '鸡', '猴', '羊', '马', '蛇', '龙', '兔', '虎', '牛', '鼠',];
                                $bet['way'] = $arr[0]."_".implode(",",getXXX(range(4,8),$sheng_xiao_arr));
                            } elseif ($g == 11) { //不中
                                if (in_array($arr[0],['五不中','六不中'])) {
                                    $bet['way'] = $arr[0]."_".implode(",",getXXX(range(6,8),range(1,49)));
                                } elseif (in_array($arr[0],['七不中','八不中'])) {
                                    $bet['way'] = $arr[0]."_".implode(",",getXXX(range(8,10),range(1,49)));
                                } elseif (in_array($arr[0],['九不中','十不中'])) {
                                    $bet['way'] = $arr[0]."_".implode(",",getXXX(range(10,12),range(1,49)));
                                }
                            } elseif ($g == 10) {
                                $lian_wei_arr = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
                                $bet['way'] = $arr[0]."_".implode(",",getXXX(range(4,8),$lian_wei_arr));
                            }
                        }
                        $bet['bet_time'] = $timeArr[$key];
                        $bet['user_id'] = $val['id'];
                        $bet['username'] = $val['username'];
                        $bet['nickname'] = $val['nickname'];
                        $bet['avatar'] = $val['avatar'];
                        $bet['bet_money'] = $moneyArr[array_rand($moneyArr,1)];
                        $rows = $personDb->insert("un_bet_list",$bet);
                        unset($timeArr[$key]);
                    } else {
                        if($config['multiple'] == 1) {
                            $startNum = getBetStartMoney($config['money']['data'][$keys]['money_start']);
                            if($config['money']['data'][$keys]['money_start'] == $config['money']['data'][$keys]['money_end'] || $config['money']['data'][$keys]['money_end'] < $startNum){
                                for($x=$startNum;$x<=$startNum+5;$x+=5) {
                                    $moneyArr[] = $x;
                                }
                            }else if($config['money']['data'][$keys]['money_end'] < 5){
                                $moneyArr[] = 5;
                            }else{
                                for($x=$startNum;$x<=$config['money']['data'][$keys]['money_end'];$x+=5) {
                                    $moneyArr[] = $x;
                                }
                            }
                        } else {
                            $moneyArr = range($config['money']['data'][$keys]['money_start'],$config['money']['data'][$keys]['money_end']);
                        }
                        shuffle($moneyArr);
                        $key = array_rand($timeArr,1);
                        $g = get_rand($config['way']);//确定玩法类型
                        shuffle($wayArr[$g]);
                        $bet['way'] = $wayArr[$g][array_rand($wayArr[$g],1)];
                        if (in_array($bet['lottery_type'],[2,4,9,14])) {
                            if ($g == 5){
                                //如果是猜冠亚，则组装玩法
                                $a1 = $wayArr[$g][array_rand($wayArr[$g])];
                                $a2 = $wayArr[$g][array_rand($wayArr[$g])];
                                while($a1 == $a2) {
                                    $a2 = $wayArr[$g][array_rand($wayArr[$g])];
                                }
                                $bet['way'] = "冠亚_{$a1}_{$a2}";
                            }
                        } elseif (in_array($bet['lottery_type'],[7,8])) {
                            //连尾
                            $arr = explode("_",$bet['way']);
                            if ($g == 4){ //连码
                                $bet['way'] = $arr[0]."_".implode(",",getXXX(range(4,10),range(1,49)));
                            } elseif ($g == 9) { //连肖
                                $sheng_xiao_arr = ['猪', '狗', '鸡', '猴', '羊', '马', '蛇', '龙', '兔', '虎', '牛', '鼠',];
                                $bet['way'] = $arr[0]."_".implode(",",getXXX(range(4,8),$sheng_xiao_arr));
                            } elseif ($g == 11) { //不中
                                if (in_array($arr[0],['五不中','六不中'])) {
                                    $bet['way'] = $arr[0]."_".implode(",",getXXX(range(6,8),range(1,49)));
                                } elseif (in_array($arr[0],['七不中','八不中'])) {
                                    $bet['way'] = $arr[0]."_".implode(",",getXXX(range(8,10),range(1,49)));
                                } elseif (in_array($arr[0],['九不中','十不中'])) {
                                    $bet['way'] = $arr[0]."_".implode(",",getXXX(range(10,12),range(1,49)));
                                }
                            } elseif ($g == 10) {
                                $lian_wei_arr = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
                                $bet['way'] = $arr[0]."_".implode(",",getXXX(range(4,8),$lian_wei_arr));
                            }
                        }
                        $bet['bet_time'] = $timeArr[$key];
                        $bet['user_id'] = $val['id'];
                        $bet['username'] = $val['username'];
                        $bet['nickname'] = $val['nickname'];
                        $bet['avatar'] = $val['avatar'];
                        $bet['bet_money'] = $moneyArr[array_rand($moneyArr,1)];
                        $rows = $personDb->insert("un_bet_list",$bet);
                        unset($timeArr[$key]);
                    }
                }
            }
        }

        lg("person","投注机机器人任务执行结束");

    } elseif($rows['type'] == 2) { //飘窗机器人

        //灌数据
        $config = json_decode($rows['value'],true);

        $data['conf_id'] = $rows['id'];
        $data['lottery_type'] = $config['lottery_type'];

        //飘窗金额
        $money_array = range($config['barrage_type']['start_money'],$config['barrage_type']['end_money']);

        //飘窗玩法
        $way_array = $config['way'];

        //飘窗飘出时间
        $startTime = strtotime('today') + $config['start_time']*3600;
        $endTime =  strtotime('today') + $config['end_time']*3600;
        $time_array = range($startTime,$endTime);//下注时间集合

        foreach ($config['user_info'] as $val) {
            for ($a = 0; $a < $config['num']; $a++) {
                $data['user_id'] = $val['user_id'];
                $data['name'] = $config['barrage_type']['name'];
                $data['way'] = $way_array[array_rand($way_array,1)];

                $money_key = array_rand($money_array,1);
                $data['money'] = $money_array[$money_key];
                //unset($money_array[$money_key]);
                
                $time_key = array_rand($time_array,1);
                $data['barrage_time'] = $time_array[$time_key];
                unset($time_array[$time_key]);

                $personDb->insert("un_barrage_auto",$data);
            }
        }

        lg("person","飘窗机器人任务执行结束");
    }
}



//是否开启五倍
function getBetStartMoney($start_money) {
    if($start_money > 5) {//判断下注金额是否大于5，则取当前值最接近5的倍数的值
        $startNum = $start_money%5;
        if($startNum == 0){
            $startNum = $start_money;
        }else{
            $startNum = 5- $startNum + $start_money;
        }
    } else if($start_money == 5) {//下注金额是否等于5，则取当前值
        $startNum = $start_money;
    } else {//如果小于5，则取5
        $startNum = 5;
    }
    return $startNum;
}

//概率计算
function get_rand($proArr) {
    $result = '';
    //概率数组的总概率精度
    $proSum = array_sum($proArr);
    //概率数组循环
    foreach ($proArr as $key => $proCur) {
        $randNum = mt_rand(1, $proSum);
        if ($randNum <= $proCur) {
            $result = $key;
            break;
        } else {
            $proSum -= $proCur;
        }
    }
    unset ($proArr);
    return $result;
}

//玩法 幸运28  加拿大28
function getWayInfo() {
    return $wayArr = [
        "1"=>["大","小","单","双"],//大小单双
        "2"=>["大单","小单","大双","小双"],
        "3"=>["极大","极小"],
        "4"=>range(0,27),
        "5"=>["红","绿","蓝","豹子","正顺","倒顺","半顺","乱顺","对子"]
    ];
}

//玩法 北京PK10  幸运飞艇 急速赛车
function getPK10WayInfo() {
    return $wayArr = [
        "1"=>[
            '冠军_大', '冠军_小', '冠军_单', '冠军_双', '冠军_大单', '冠军_小单', '冠军_大双', '冠军_小双',
            '亚军_大', '亚军_小', '亚军_单', '亚军_双', '亚军_大单', '亚军_小单', '亚军_大双', '亚军_小双',
            '第三名_大', '第三名_小', '第三名_单', '第三名_双', '第三名_大单', '第三名_小单', '第三名_大双', '第三名_小双',
            '第四名_大', '第四名_小', '第四名_单', '第四名_双', '第四名_大单', '第四名_小单', '第四名_大双', '第四名_小双',
            '第五名_大', '第五名_小', '第五名_单', '第五名_双', '第五名_大单', '第五名_小单', '第五名_大双', '第五名_小双',
            '第六名_大', '第六名_小', '第六名_单', '第六名_双', '第六名_大单', '第六名_小单', '第六名_大双', '第六名_小双',
            '第七名_大', '第七名_小', '第七名_单', '第七名_双', '第七名_大单', '第七名_小单', '第七名_大双', '第七名_小双',
            '第八名_大', '第八名_小', '第八名_单', '第八名_双', '第八名_大单', '第八名_小单', '第八名_大双', '第八名_小双',
            '第九名_大', '第九名_小', '第九名_单', '第九名_双', '第九名_大单', '第九名_小单', '第九名_大双', '第九名_小双',
            '第十名_大', '第十名_小', '第十名_单', '第十名_双', '第十名_大单', '第十名_小单', '第十名_大双', '第十名_小双'
        ],//猜双面
        "2"=>[
            '冠军_1', '冠军_2', '冠军_3', '冠军_4', '冠军_5', '冠军_6', '冠军_7', '冠军_8', '冠军_9', '冠军_10',
            '亚军_1', '亚军_2', '亚军_3', '亚军_4', '亚军_5', '亚军_6', '亚军_7', '亚军_8', '亚军_9', '亚军_10',
            '第三名_1', '第三名_2', '第三名_3', '第三名_4', '第三名_5', '第三名_6', '第三名_7', '第三名_8', '第三名_9', '第三名_10',
            '第四名_1', '第四名_2', '第四名_3', '第四名_4', '第四名_5', '第四名_6', '第四名_7', '第四名_8', '第四名_9', '第四名_10',
            '第五名_1', '第五名_2', '第五名_3', '第五名_4', '第五名_5', '第五名_6', '第五名_7', '第五名_8', '第五名_9', '第五名_10',
            '第六名_1', '第六名_2', '第六名_3', '第六名_4', '第六名_5', '第六名_6', '第六名_7', '第六名_8', '第六名_9', '第六名_10',
            '第七名_1', '第七名_2', '第七名_3', '第七名_4', '第七名_5', '第七名_6', '第七名_7', '第七名_8', '第七名_9', '第七名_10',
            '第八名_1', '第八名_2', '第八名_3', '第八名_4', '第八名_5', '第八名_6', '第八名_7', '第八名_8', '第八名_9', '第八名_10',
            '第九名_1', '第九名_2', '第九名_3', '第九名_4', '第九名_5', '第九名_6', '第九名_7', '第九名_8', '第九名_9', '第九名_10',
            '第十名_1', '第十名_2', '第十名_3', '第十名_4', '第十名_5', '第十名_6', '第十名_7', '第十名_8', '第十名_9', '第十名_10'
        ],//猜车号
        "3"=>[
            "冠军_龙", "冠军_虎",
            "亚军_龙", "亚军_虎",
            "第三名_龙", "第三名_虎",
            "第四名_龙", "第四名_虎",
            "第五名_龙", "第五名_虎"
        ],//猜龙虎
        "4"=>["庄", "闲"],//猜庄闲
        "5"=>[1,2,3,4,5,6,7,8,9],
        "6"=>[
            "冠亚和_大", "冠亚和_小", "冠亚和_单", "冠亚和_双",
            "冠亚和_3", "冠亚和_4", "冠亚和_5", "冠亚和_6", "冠亚和_7", "冠亚和_8", "冠亚和_9", "冠亚和_10", "冠亚和_11", "冠亚和_12",
            "冠亚和_13", "冠亚和_14", "冠亚和_15", "冠亚和_16", "冠亚和_17", "冠亚和_18", "冠亚和_19",
            "冠亚和_A", "冠亚和_B", "冠亚和_C"
        ]
    ];
}

//玩法 重庆时时彩
function getSscWayInfo() {
    return $wayArr = [
        "1"=>[
            '第一球_大', '第一球_小', '第一球_单', '第一球_双',
            '第二球_大', '第二球_小', '第二球_单', '第二球_双',
            '第三球_大', '第三球_小', '第三球_单', '第三球_双',
            '第四球_大', '第四球_小', '第四球_单', '第四球_双',
            '第五球_大', '第五球_小', '第五球_单', '第五球_双',
        ],//猜双面
        "2"=>[
            '第一球_0', '第一球_1', '第一球_2', '第一球_3', '第一球_4', '第一球_5', '第一球_6', '第一球_7', '第一球_8', '第一球_9',
            '第二球_0', '第二球_1', '第二球_2', '第二球_3', '第二球_4', '第二球_5', '第二球_6', '第二球_7', '第二球_8', '第二球_9',
            '第三球_0', '第三球_1', '第三球_2', '第三球_3', '第三球_4', '第三球_5', '第三球_6', '第三球_7', '第三球_8', '第三球_9',
            '第四球_0', '第四球_1', '第四球_2', '第四球_3', '第四球_4', '第四球_5', '第四球_6', '第四球_7', '第四球_8', '第四球_9',
            '第五球_0', '第五球_1', '第五球_2', '第五球_3', '第五球_4', '第五球_5', '第五球_6', '第五球_7', '第五球_8', '第五球_9',
        ],//猜数字
        "3"=>["总和_大", "总和_小", "总和_单", "总和_双",],//猜总和
        "4"=>["龙", "虎", "和"],//猜龙虎
    ];
}

//玩法 百人牛牛
function getNnWayInfo() {
    return $wayArr = [
        '1'=>['无牛', '牛一', '牛二', '牛三', '牛四', '牛五', '牛六', '牛七', '牛八', '牛九', '牛牛', '花色牛'],
        '2'=>[
            '第一张_A', '第一张_2', '第一张_3', '第一张_4', '第一张_5', '第一张_6', '第一张_7',
            '第一张_8', '第一张_9', '第一张_10', '第一张_J','第一张_Q','第一张_K',
            '第二张_A', '第二张_2', '第二张_3', '第二张_4', '第二张_5', '第二张_6', '第二张_7',
            '第二张_8', '第二张_9', '第二张_10', '第二张_J','第二张_Q','第二张_K',
            '第三张_A', '第三张_2', '第三张_3', '第三张_4', '第三张_5', '第三张_6', '第三张_7',
            '第三张_8', '第三张_9', '第三张_10', '第三张_J','第三张_Q','第三张_K',
            '第四张_A', '第四张_2', '第四张_3', '第四张_4', '第四张_5', '第四张_6', '第四张_7',
            '第四张_8', '第四张_9', '第四张_10', '第四张_J','第四张_Q','第四张_K',
            '第五张_A', '第五张_2', '第五张_3', '第五张_4', '第五张_5', '第五张_6', '第五张_7',
            '第五张_8', '第五张_9', '第五张_10', '第五张_J','第五张_Q','第五张_K',
        ],
        '3'=>[
            '第一张_大', '第一张_小', '第一张_单', '第一张_双', '第一张_大单', '第一张_大双', '第一张_小单', '第一张_小双',
            '第二张_大', '第二张_小', '第二张_单', '第二张_双', '第二张_大单', '第二张_大双', '第二张_小单', '第二张_小双',
            '第三张_大', '第三张_小', '第三张_单', '第三张_双', '第三张_大单', '第三张_大双', '第三张_小单', '第三张_小双',
            '第四张_大', '第四张_小', '第四张_单', '第四张_双', '第四张_大单', '第四张_大双', '第四张_小单', '第四张_小双',
            '第五张_大', '第五张_小', '第五张_单', '第五张_双', '第五张_大单', '第五张_大双', '第五张_小单', '第五张_小双'
        ],
        '4'=>[
            '第一张_黑桃', '第一张_梅花', '第一张_红心', '第一张_方块',
            '第二张_黑桃', '第二张_梅花', '第二张_红心', '第二张_方块',
            '第三张_黑桃', '第三张_梅花', '第三张_红心', '第三张_方块',
            '第四张_黑桃', '第四张_梅花', '第四张_红心', '第四张_方块',
            '第五张_黑桃', '第五张_梅花', '第五张_红心', '第五张_方块',
        ],
        '5' => ['龙', '虎'],
        '6' => ['有公牌','无公牌'],
        '7' => ['大','小','单','双', '大单','大双','小单','小双'],
        '8' => ['红方胜','蓝方胜']
    ];
}

//玩法 六合彩、急速六合彩
function getLiuheWayInfo() {
    $redis = initCacheRedis();
    $room_ids = $redis->lrange("allroomIds",0,-1);
    foreach ($room_ids as $val) {
        $room_info = $redis->hGetAll("allroom:".$val);
        if ($room_info['lottery_type'] == 7) {
            $way = json_decode($redis->get("way".$val),true);
            break;
        }
    }
    foreach ($way as $key=>$val) {
        $arr = [];
        foreach ($val as $va) {
            if (!empty($va['data']['text'])) {
                foreach ($va['data']['text'] as $v) {
                    $arr[] = $v['title'];
                }
            }
            if (!empty($va['data']['num'])) {
                foreach ($va['data']['num'] as $v) {
                    $arr[] = $v['title'];
                }
            }
            $way[$key] = $arr;
        }
    }
    deinitCacheRedis($redis);
    $ways = [];
    foreach ($way as $key => $val) {
        for ($a=1;$a<=13;$a++) {
            if ($key == "panel_".$a) {
                $ways[$a] = $val;
            }
        }
    }
    return $ways;
}

function getCupWayInfo(){
    return $wayArr = [
        '1'=>['全场单双_单','全场单双_双'],
        '2'=>['半场让球_A','半场让球_B','半场大小_大','半场大小_小'],
        '3'=>['全场让球_A','全场让球_B','全场大小_大','全场大小_小'],
        '4'=>['加时让球_A','加时让球_B','加时大小_大','加时大小_小'],
        '5'=>['点球让球_A','点球让球_B','点球大小_大','点球大小_小']
    ];
}

function getSbWayInfo(){
    return $wayArr = [
        '1'=>[
            '第一骰_1','第一骰_2','第一骰_3','第一骰_4','第一骰_5','第一骰_6',
            '第二骰_1','第二骰_2','第二骰_3','第二骰_4','第二骰_5','第二骰_6',
            '第三骰_1','第三骰_2','第三骰_3','第三骰_4','第三骰_5','第三骰_6'
        ],
        '2'=>[
            '第一骰_大','第一骰_小','第一骰_单','第一骰_双',
            '第二骰_大','第二骰_小','第二骰_单','第二骰_双',
            '第三骰_大','第三骰_小','第三骰_单','第三骰_双'
        ],
        '3'=>[
            '总和_大','总和_小','总和_单','总和_双',
            '总和_3','总和_4','总和_5','总和_6','总和_7','总和_8','总和_9','总和_10',
            '总和_11','总和_12','总和_13','总和_14','总和_15','总和_16','总和_17'
        ],
        '4'=>['对子_1','对子_2','对子_3','对子_4','对子_5','对子_6'],
        '5'=>['豹子_1','豹子_2','豹子_3','豹子_4','豹子_5','豹子_6','豹子_1-6'],
        '6'=>['单骰_1','单骰_2','单骰_3','单骰_4','单骰_5','单骰_6'],
        '7'=>[
            '双骰_1-2','双骰_1-3','双骰_1-4','双骰_1-5','双骰_1-6',
            '双骰_2-3','双骰_2-4','双骰_2-5','双骰_2-6','双骰_3-4',
            '双骰_3-5','双骰_3-6','双骰_4-5','双骰_4-6','双骰_5-6'
        ],
    ];
}



function getXXX($num_info,$num_info_num){
    $leng = $num_info[array_rand($num_info)];
    $x = [];
    for ($a=1;$a<=$leng;$a++) {
        $kk = array_rand($num_info_num);
        $x[]= $num_info_num[$kk];
        unset($num_info_num[$kk]);
    }
    return $x;
}
