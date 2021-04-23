<?php
//引入系统
define('S_ROOT', dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR);
require S_ROOT . 'core' . DIRECTORY_SEPARATOR . 'base.php';

lg("auto_person","加拿大28定时任务执行开始");

//设置php系统变量
ini_set('max_execution_time', '0');
ini_set('memory_limit','2048M');


//获取DB连接
$personDb = getconn();

//玩法
$wayArr = [
    "1"=>["大","小","单","双"],
    "2"=>["大单","小单","大双","小双"],
    "3"=>["极大","极小"],
    "4"=>range(0,27),
    "5"=>["红","绿","蓝","豹子","正顺","倒顺","半顺","乱顺","对子"]
];

//清楚过期的数据
$sql = "delete from un_bet_list where lottery_type = 3 and bet_time < ".time();
$rs = $personDb->query($sql);
lg("auto_person","删除加拿大28已经执行过的投注信息执行结果->".var_export($rs,true));

//获取机器人配置列表
$rows = $personDb->getall("select * from un_person_config where state = 1 and (type = 1 OR type = 3)");
lg("auto_person","获取开启中的机器人配置->".var_export($rows,true));

//添加机器人注单
if(!empty($rows)) {
    foreach($rows as $value) {
        $config = json_decode($value['value'],true);
        if($config['lottery_type'] == 3){
            lg("auto_person","获取加拿大28开启中的机器人配置->".var_export($value,true)."->开始生成数据");
            $bet['conf_id'] = $value['id'];
            $bet['room_id'] = $config['room'];
            $bet['lottery_type'] = $config['lottery_type'];

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
                            if($config['multiple']) {
                                $multiple = $config['multiple'] == 1?5:$config['multiple'];         //开启的倍数
                                $startNum = getBetStartMoney($config['money']['data']['start_money'],$multiple);
                                if($config['money']['data']['start_money'] == $config['money']['data']['end_money'] || $config['money']['data']['end_money'] < $startNum) {
                                    for($x=$startNum;$x<=$startNum+$multiple;$x+=$multiple) {
                                        $moneyArr[] = $x;
                                    }
                                } else if($config['money']['data']['end_money'] < $multiple){
                                    $moneyArr[] = $multiple;
                                } else {
                                    for($x=$startNum;$x<=$config['money']['data']['end_money'];$x+=$multiple) {
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
                            $bet['bet_time'] = $timeArr[$key];
                            $bet['user_id'] = $v['id'];
                            $bet['avatar'] = $v['avatar'];
                            $bet['username'] = $v['username'];
                            $bet['nickname'] = $v['nickname'];
                            $bet['bet_money'] = $moneyArr[array_rand($moneyArr,1)];
                            $personDb->insert("un_bet_list",$bet);
                            unset($timeArr[$key]);
                        }
                    } else {
                        foreach($config['money']['data'] as $v) {
                            $moneyArr = [];
                            if($config['multiple']) {
                                $multiple = $config['multiple'] == 1?5:$config['multiple'];         //开启的倍数
                                $startNum = getBetStartMoney($v['money_start'],$multiple);
                                if($v['money_start'] == $v['money_end'] || $v['money_end'] < $startNum) {
                                    for($x=$startNum;$x<=$startNum+$multiple;$x+=$multiple) {
                                        $moneyArr[] = $x;
                                    }
                                } else if ($v['money_end'] < $multiple){
                                    $moneyArr[] = $multiple;
                                } else {
                                    for($x=$startNum;$x<=$v['money_end'];$x+=$multiple) {
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
                            $bet['bet_time'] = $timeArr[$key];
                            $bet['user_id'] = $v['id'];
                            $bet['username'] = $v['username'];
                            $bet['nickname'] = $v['nickname'];
                            $bet['avatar'] = $v['avatar'];
                            $bet['bet_money'] = $moneyArr[array_rand($moneyArr,1)];
                            $personDb->insert("un_bet_list",$bet);
                            unset($timeArr[$key]);
                        }
                    }
                }
            } else if($config['num']['type'] == 2) {
                $count = $config['num']['data'];
                for($a=1;$a<=$count;$a++) {
                    shuffle($timeArr);
                    if($config['money']['type'] == 1) {
                        foreach($config['ids'] as $v) {
                            $moneyArr = [];
                            if($config['multiple']) {
                                $multiple = $config['multiple'] == 1?5:$config['multiple'];         //开启的倍数
                                $startNum = getBetStartMoney($config['money']['data']['start_money'],$multiple);
                                if($config['money']['data']['start_money'] == $config['money']['data']['end_money'] || $config['money']['data']['end_money'] < $startNum){
                                    for($x=$startNum;$x<=$startNum+$multiple;$x+=$multiple) {
                                        $moneyArr[] = $x;
                                    }
                                } else if ($config['money']['data']['end_money'] < $multiple){
                                    $moneyArr[] = $multiple;
                                }else{
                                    for($x=$startNum;$x<=$config['money']['data']['end_money'];$x+=$multiple) {
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
                            $bet['bet_time'] = $timeArr[$key];
                            $bet['user_id'] = $v['id'];
                            $bet['avatar'] = $v['avatar'];
                            $bet['username'] = $v['username'];
                            $bet['nickname'] = $v['nickname'];
                            $bet['bet_money'] = $moneyArr[array_rand($moneyArr,1)];
                            $personDb->insert("un_bet_list",$bet);
                            unset($timeArr[$key]);
                        }
                    } else {
                        foreach($config['money']['data'] as $v) {
                            $moneyArr = [];
                            if($config['multiple']) {
                                $multiple = $config['multiple'] == 1?5:$config['multiple'];         //开启的倍数
                                $startNum = getBetStartMoney($v['money_start'],$multiple);
                                if($v['money_start'] == $v['money_end'] || $v['money_end'] < $startNum){
                                    for($x=$startNum;$x<=$startNum+$multiple;$x+=$multiple) {
                                        $moneyArr[] = $x;
                                    }
                                } else if($v['money_end'] < $multiple){
                                    $moneyArr[] = $multiple;
                                }else{
                                    for($x=$startNum;$x<=$v['money_end'];$x+=$multiple) {
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
                            $bet['bet_time'] = $timeArr[$key];
                            $bet['user_id'] = $v['id'];
                            $bet['username'] = $v['username'];
                            $bet['nickname'] = $v['nickname'];
                            $bet['avatar'] = $v['avatar'];
                            $bet['bet_money'] = $moneyArr[array_rand($moneyArr,1)];
                            $personDb->insert("un_bet_list",$bet);
                            unset($timeArr[$key]);
                        }
                    }
                }
            } else if($config['num']['type'] == 3) {
                foreach($config['num']['data'] as $keys=>$val) {
                    $count = $val['num'];
                    for($a=1;$a<=$count;$a++) {
                        $moneyArr = [];
                        shuffle($timeArr);
                        if($config['money']['type'] == 1) {
                            if($config['multiple']) {
                                $multiple = $config['multiple'] == 1?5:$config['multiple'];         //开启的倍数
                                $startNum = getBetStartMoney($config['money']['data']['start_money'],$multiple);
                                if($config['money']['data']['start_money'] == $config['money']['data']['end_money'] || $config['money']['data']['end_money'] < $startNum){
                                    for($x=$startNum;$x<=$startNum+$multiple;$x+=$multiple) {
                                        $moneyArr[] = $x;
                                    }
                                } else if($config['money']['data']['end_money'] < $multiple) {
                                    $moneyArr[] = $multiple;
                                }else{
                                    for($x=$startNum;$x<=$config['money']['data']['end_money'];$x+=$multiple) {
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
                            $bet['bet_time'] = $timeArr[$key];
                            $bet['user_id'] = $val['id'];
                            $bet['username'] = $val['username'];
                            $bet['nickname'] = $val['nickname'];
                            $bet['avatar'] = $val['avatar'];
                            $bet['bet_money'] = $moneyArr[array_rand($moneyArr,1)];
                            $personDb->insert("un_bet_list",$bet);
                            unset($timeArr[$key]);
                        } else {
                            if($config['multiple']) {
                                $multiple = $config['multiple'] == 1?5:$config['multiple'];         //开启的倍数
                                $startNum = getBetStartMoney($config['money']['data'][$keys]['money_start'],$multiple);
                                if($config['money']['data'][$keys]['money_start'] == $config['money']['data'][$keys]['money_end'] || $config['money']['data'][$keys]['money_end'] < $startNum){
                                    for($x=$startNum;$x<=$startNum+$multiple;$x+=$multiple) {
                                        $moneyArr[] = $x;
                                    }
                                }else if($config['money']['data'][$keys]['money_end'] < $multiple){
                                    $moneyArr[] = $multiple;
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
                            $bet['bet_time'] = $timeArr[$key];
                            $bet['user_id'] = $val['id'];
                            $bet['username'] = $val['username'];
                            $bet['nickname'] = $val['nickname'];
                            $bet['avatar'] = $val['avatar'];
                            $bet['bet_money'] = $moneyArr[array_rand($moneyArr,1)];
                            $personDb->insert("un_bet_list",$bet);
                            unset($timeArr[$key]);
                        }
                    }
                }
            }
            lg("auto_person","结束生成数据");
        }
    }
}
lg("auto_person","加拿大28定时任务执行结束");

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

function getBetStartMoney($start_money, $multiple = 5)
{
    if($start_money > $multiple) {//判断下注金额是否大于5，则取当前值最接近5的倍数的值
        $startNum = $start_money%$multiple;
        $startNum = $multiple- $startNum + $start_money;
    } else if($start_money == $multiple) {//下注金额是否等于5，则取当前值
        $startNum = $start_money;
    } else {//如果小于5，则取5
        $startNum = $multiple;
    }
    return $startNum;
}
?>