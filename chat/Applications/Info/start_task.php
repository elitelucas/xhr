<?php
/**
 * This file is part of workerman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link http://www.workerman.net/
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */

use Workerman\Autoloader;
use Workerman\Worker;
use \GatewayWorker\Lib\Db;

// 自动加载类
require_once __DIR__ . '/../../Workerman/Autoloader.php';
Autoloader::setRootPath(__DIR__);
require_once(__DIR__ . '/../function.php');

// task worker，使用Text协议
$task_worker = new Worker('Text://127.0.0.1:1270');
// task进程数可以根据需要多开一些
$task_worker->count = 20;
$task_worker->name = 'TaskWorker';
$task_worker->onMessage = function($connection, $task_data)
{
    $task_result = array();
    // 假设发来的是json数据
    $task_data = json_decode($task_data, true);

    // 根据task_data处理相应的任务逻辑.... 得到结果，这里省略....
    switch ($task_data['function']){
        case 'getOnlineUser':
            $time = time();
            lg('task_debug',var_export(array(
                '获取在线人数前',
                'time'=>$time,
            ),1));
            $task_result = getOnlineUser();
            lg('task_debug',var_export(array(
                '获取在线人数后',
                '费时'=>time()-$time,
            ),1));
            break;
    }
     // 发送结果
     $connection->send(json_encode($task_result));
};
if(!defined('GLOBAL_START'))
{
    Worker::runAll();
}

function getOnlineUser(){
    $res = array();
    $res = signa('?m=api&c=workerman&a=onlineUser', '');

    return array('commandid' => 4002, 'content' =>$res);
}


/**
 * 验证签名 公钥加密
 * @param $url string 请求地址
 * @param $data array 传入参数
 */
function taskSigna($url, $data)
{
    static $host, $signa, $config;
    if (empty($config)) {
        !defined('IN_SNYNI') && define("IN_SNYNI", 1);
        $config = require("../caches/config.php");
    }
    if (empty($host)) {
        $host = $config['api_host'];
    }
    if (empty($signa)) {
        $signa = $config['signa'];
    }
    //签名数据
    $key = $signa['key'];
    $secret_key = $signa['secret_key'];
    $param['timestamp'] = time();//时间戳
    $param['signature'] = md5(md5($param['timestamp']) . $secret_key);//签名
    $param['key'] = $key;//key
    $param['source'] = 0;//接口来源:1 ios;  2 安卓; 3 H5; 4 PC ; 0 服务器本身
    $param['project'] = 0;//项目来源(0:pc手游, 1:讯彩; 2其它;  [待存字典表])
    $param['method'] = "POST";
    $params = base64_encode(json_encode($param));


    //业务数据
    $encrypted = "";
    if (!empty($data)) {
        $datas = json_encode($data);
        $encrypted = taskDencrypt(base64_encode($datas), "ENCODE", $param['signature']);
    }
    //请求接口
    $res = task_curl_post($host . $url, array('param' => $params, 'data' => $encrypted));
    return $res;
}
//
/**
 * 加密解密处理
 * @param unknown_type $string 密文
 * @param unknown_type $operation 加密 或 解密
 * @param unknown_type $key 密匙
 * @return unknown
 */
function taskDencrypt($string, $operation = 'DECODE', $key = '')
{
    if (empty($string)) {
        return false;
    }
    $operation != 'ENCODE' && $string = base64_decode(substr($string, 16));  //如果是解密就截16位以后的字符 并base64解密
    $code = '';
    $key = md5($key); //md5密匙
    $keya = strtoupper(substr($key, 0, 16));      //截取新密匙的前16位并大写
    $keylen = strlen($key);                      //计算密匙长度
    $strlen = strlen($string);
    $rndkey = [];
    for ($i = 0; $i < 128; $i++) {
        $rndkey[$i] = ord($key[$i % $keylen]);  //生成128个加密因子  （按密匙中单个字符的ASCII 值）

    }
    for ($i = 0; $i < $strlen; $i++) {
        $code .= chr(ord($string[$i]) ^ $rndkey[$i * $strlen % 128]);  //用字条串的每个字符ASCII值和加密因子里的（当前循环次数*字符串长度 求于 128） 按位异或  最后 ASCII 值返回字符
    }
    return ($operation != 'DECODE' ? $keya . str_replace('=', '', base64_encode($code)) : $code);  // 如果是加密就截取新密匙的前16位并加上base64加密码生成的密文
}

/**
 * curl post
 * @param $url string 请求地址
 * @param $data array 传入参数
 * @param $header array 返回Header
 * @param $nobody array 返回body
 * @return mixed
 */
function task_curl_post($url, $data = [], $header = false, $nobody = false)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HEADER, $header);//返回Header
    curl_setopt($ch, CURLOPT_NOBODY, $nobody);//不需要内容
    curl_setopt($ch, CURLOPT_POST, true);//POST
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
    $output = curl_exec($ch);
    curl_close($ch);
    return $output;
}