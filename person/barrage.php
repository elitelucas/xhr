<?php
//引入系统
define('S_ROOT', dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR);
require S_ROOT . 'core' . DIRECTORY_SEPARATOR . 'base.php';

lg("auto_barrage","中奖飘窗定时任务执行开始");

//设置php系统变量
ini_set('max_execution_time', '0');
ini_set('memory_limit','2048M');

//获取DB连接
$personDb = getconn();

//清除过期的数据
$sql = "delete from un_barrage_auto where barrage_time < ".time();
$rs = $personDb->query($sql);
lg("auto_barrage","删除已执行过的中奖飘窗已经信息结果->".var_export($rs,true));

//获取中奖飘窗配置列表
$rows = $personDb->getall("select * from un_person_config where state = 1 and type = 2");
lg("auto_barrage","获取开启中的中奖飘窗配置->".var_export($rows,true));

//添加中奖飘窗信息
if(!empty($rows)) {

    foreach ($rows as $value) {

        //灌数据
        $config = json_decode($value['value'],true);

        $data['conf_id'] = $value['id'];
        $data['lottery_type'] = $config['lottery_type'];

        //飘窗金额
        $money_array = range($config['barrage_type']['start_money'],$config['barrage_type']['end_money']);

        //飘窗玩法
        $way_array = $config['way'];

        //飘窗飘出时间
        $startTime = strtotime('today +1 day') + $config['start_time']*3600;
        $endTime =  strtotime('today +1 day') + $config['end_time']*3600;
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
    }

}
lg("auto_barrage","中奖飘窗定时任务执行结束");

?>