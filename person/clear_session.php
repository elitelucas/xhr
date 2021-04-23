<?php
exit;
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/6/2
 * Time: 20:22
 */
//引入系统
define('S_ROOT', dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR);
require S_ROOT . 'core' . DIRECTORY_SEPARATOR . 'base.php';

//设置php系统变量
ini_set('max_execution_time', '0');
ini_set('memory_limit','2048M');

//获取DB连接
$db = getconn();

$data = $db->getall("select sessionid,lastvisit from un_session where is_admin = 1");
foreach ($data as $val){
    if(time() > ($val['lastvisit']+86400)){
        $db->query("delete from un_session where sessionid = '".$val['sessionid']."'");
    }
}

//清除前台的超时用户
$data = $db->getall("select sessionid,lastvisit from un_session where is_admin = 0");
foreach ($data as $val){
    if(time() > ($val['lastvisit']+60*60*3)){
        $db->query("delete from un_session where sessionid = '".$val['sessionid']."'");
    }
}