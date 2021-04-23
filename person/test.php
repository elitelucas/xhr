<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018\7\25 0025
 * Time: 15:35
 */

//引入系统
define('S_ROOT', dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR);
require S_ROOT . 'core' . DIRECTORY_SEPARATOR . 'base.php';


//设置php系统变量
ini_set('max_execution_time', '0');
ini_set('memory_limit','2048M');

//获取DB连接
$personDb = getconn();

if (empty($argv)) {
    lg("update_upper",'未传参数');
    exit;
}

if (in_array($argv[1],array('7','8'))) {
    $rows = $personDb->getall("SELECT id,upper,lottery_type FROM un_room WHERE lottery_type = $argv[1]");
    foreach ($rows as $val) {
        $upper = json_decode($val['upper'],true);
        foreach ($upper['limit'] as $key=>$value) {
            if ($value['remark'] == '特码A区段ABCDE') {
                $upper['limit'][$key]['contact'][1] = '特码A_区段B';
            }
            if ($value['remark'] == '特码B区段ABCDE') {
                $upper['limit'][$key]['contact'][1] = '特码B_区段B';
            }
        }
        $rows = $personDb->update("un_room",array('upper'=>encode($upper)),array('id'=>$val['id']));
        lg("update_upper",'更新投注限额->彩种ID::'.$argv[1].'->房间ID::'.$val['id'].'->执行结果::'.var_export($rows,true));
    }
} elseif (in_array($argv[1],array('2','4','9','14'))) {
    $rows = $personDb->getall("SELECT id,upper,lottery_type FROM un_room WHERE lottery_type = $argv[1]");
    foreach ($rows as $val) {
        $upper = json_decode($val['upper'],true);
        $arr = [];
        foreach ($upper['limit'] as $key=>$value) {
            if ($key > 8) {
                $arr[$key+1] = $value;
            } else {
                $arr[$key] = $value;
            }
        }
        $upper['limit'] = $arr;
        $rows = $personDb->update("un_room",array('upper'=>encode($upper)),array('id'=>$val['id']));
        lg("update_upper",'更新投注限额->彩种ID::'.$argv[1].'->房间ID::'.$val['id'].'->执行结果::'.var_export($rows,true));
    }
} else {
    lg("update_upper",'非法参数::'.json_encode($argv));
}