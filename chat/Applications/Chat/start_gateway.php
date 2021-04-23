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

use GatewayWorker\Gateway;
use Workerman\Autoloader;
use Workerman\Worker;

// 自动加载类
require_once __DIR__ . '/../../Workerman/Autoloader.php';
Autoloader::setRootPath(__DIR__);
// WSS
$context = array(
    'ssl' => array(
        // 请使用绝对路径
        'local_cert'  => '/www/wwwroot/xhs/cert/365game.pem', // 或者crt文件
        'local_pk'    => '/www/wwwroot/xhs/cert/365game.key',
        'verify_peer'                => false,
    )
);
// websocket协议(端口任意，只要没有被其它程序占用就行)
$gateway = new Gateway("websocket://0.0.0.0:7272", $context);
// 开启SSL，websocket+SSL 即wss
$gateway->transport = 'ssl';
// gateway 进程
// 设置名称，方便status时查看
$gateway->name = 'ChatGateway';
// 设置进程数，gateway进程数建议与cpu核数相同
$gateway->count = 4;
// 分布式部署时请设置成内网ip（非127.0.0.1）
$gateway->lanIp = '127.0.0.1';
// 内部通讯起始端口，假如$gateway->count=4，起始端口为4000
// 则一般会使用4000 4001 4002 4003 4个端口作为内部通讯端口 
$gateway->startPort = 2300;
// 心跳间隔
$gateway->pingInterval = 60;
// 心跳数据
$gateway->pingData = '{"commandid":"3013"}';
// 服务注册地址
$gateway->registerAddress = '127.0.0.1:1236';
// 如果不是在根目录启动，则运行runAll方法
if(!defined('GLOBAL_START'))
{
    Worker::runAll();
}

