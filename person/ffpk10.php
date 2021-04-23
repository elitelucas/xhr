<?php
//引入系统
define('S_ROOT', dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR);
require S_ROOT . 'core' . DIRECTORY_SEPARATOR . 'base.php';

lg("auto_person","分分PK10定时任务执行开始");

//设置php系统变量
ini_set('max_execution_time', '0');
ini_set('memory_limit','2048M');

//获取DB连接
$personDb = getconn();

//玩法
$wayArr = [
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

//清楚过期的数据
$sql = "delete from un_bet_list where lottery_type = 14 and bet_time < ".time();
$rs = $personDb->query($sql);
lg("auto_person","删除分分PK10已经执行过的投注信息执行结果->".var_export($rs,true));

//获取机器人配置列表
$rows = $personDb->getall("select * from un_person_config where state = 1 and (type = 1 OR type = 3)");
lg("auto_person","获取开启中的机器人配置->".var_export($rows,true));

//添加机器人注单
if(!empty($rows)) {
    foreach($rows as $value) {
        $config = json_decode($value['value'],true);
        if($config['lottery_type'] == 14){
            lg("auto_person","获取分分PK10开启中的机器人配置->".var_export($value,true)."->开始生成数据");
            $bet['conf_id'] = $value['id'];
            $bet['room_id'] = $config['room'];
            $bet['lottery_type'] = $config['lottery_type'];

            $startTime = strtotime('today +1 day') + $config['startTime']*3600;
            $endTime =  strtotime('today +1 day') + $config['endTime']*3600;

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
                            if ($g == 5){
                                //如果是猜冠亚，则组装玩法
                                $a1 = $wayArr[$g][array_rand($wayArr[$g])];
                                $a2 = $wayArr[$g][array_rand($wayArr[$g])];
                                while($a1 == $a2) {
                                    $a2 = $wayArr[$g][array_rand($wayArr[$g])];
                                }
                                $bet['way'] = "冠亚_{$a1}_{$a2}";
                            }
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
                            if ($g == 5){
                                //如果是猜冠亚，则组装玩法
                                $a1 = $wayArr[$g][array_rand($wayArr[$g])];
                                $a2 = $wayArr[$g][array_rand($wayArr[$g])];
                                while($a1 == $a2) {
                                    $a2 = $wayArr[$g][array_rand($wayArr[$g])];
                                }
                                $bet['way'] = "冠亚_{$a1}_{$a2}";
                            }
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
                            if ($g == 5){
                                //如果是猜冠亚，则组装玩法
                                $a1 = $wayArr[$g][array_rand($wayArr[$g])];
                                $a2 = $wayArr[$g][array_rand($wayArr[$g])];
                                while($a1 == $a2) {
                                    $a2 = $wayArr[$g][array_rand($wayArr[$g])];
                                }
                                $bet['way'] = "冠亚_{$a1}_{$a2}";
                            }
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
                            if ($g == 5){
                                //如果是猜冠亚，则组装玩法
                                $a1 = $wayArr[$g][array_rand($wayArr[$g])];
                                $a2 = $wayArr[$g][array_rand($wayArr[$g])];
                                while($a1 == $a2) {
                                    $a2 = $wayArr[$g][array_rand($wayArr[$g])];
                                }
                                $bet['way'] = "冠亚_{$a1}_{$a2}";
                            }
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
                            if ($g == 5){
                                //如果是猜冠亚，则组装玩法
                                $a1 = $wayArr[$g][array_rand($wayArr[$g])];
                                $a2 = $wayArr[$g][array_rand($wayArr[$g])];
                                while($a1 == $a2) {
                                    $a2 = $wayArr[$g][array_rand($wayArr[$g])];
                                }
                                $bet['way'] = "冠亚_{$a1}_{$a2}";
                            }
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
                            if ($g == 5){
                                //如果是猜冠亚，则组装玩法
                                $a1 = $wayArr[$g][array_rand($wayArr[$g])];
                                $a2 = $wayArr[$g][array_rand($wayArr[$g])];
                                while($a1 == $a2) {
                                    $a2 = $wayArr[$g][array_rand($wayArr[$g])];
                                }
                                $bet['way'] = "冠亚_{$a1}_{$a2}";
                            }
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
lg("auto_person","分分PK10定时任务执行结束");

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