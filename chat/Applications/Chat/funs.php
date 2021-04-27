<?php
/**
 * Created by PhpStorm.
 * User: HCHT
 * Date: 2018/8/17
 * Time: 21:03
 */

use \Workerman\Connection\AsyncTcpConnection;

require_once __DIR__.'/../config.php';

/**
 * json编码
 * @param $data array
 */
function encode($data){
    return json_encode($data,JSON_UNESCAPED_UNICODE);
}

/**
 *
 * json解码
 * @param $data array
 * 返回数组
 */
function decode($str){
    return json_decode($str,1);
}


/**
 * @desc 连接CacheRedis缓存数据库
 */
function initCacheRedis() {
    echo 'eeee';
    die;
    //该参数待放入配置文件
    $cache_redis = new redis();
    $ret = $cache_redis->connect(RD_HOST, RD_PORT);
    if (!$ret) {
        //ErrorCode::errorResponse(9001, 'redis connect error');
        echo 'redis connect error';

        return;
    }
    // 关闭redis密码认证
    $ret = $cache_redis->auth(RD_PASS);
    if (!$ret) {
        echo 'redis auth error';

        return;
        //ErrorCode::errorResponse(9002, 'redis auth error');
    }
    //如查有配置Redis的库
    if(!empty($redis_config['db'])){
        $cache_redis->select($redis_config['db']);
    }
    return $cache_redis;
}

/**
 * @desc 关闭CacheRedis缓存
 */
function deinitCacheRedis($redis) {
    $redis->close();
}

function sendToTask($task_data){
    // 与远程task服务建立异步链接，ip为远程task服务的ip，如果是本机就是127.0.0.1，如果是集群就是lvs的ip
    $task_connection = new AsyncTcpConnection('Text://127.0.0.1:1271');
//    var_dump(array('$task_connection'=>$task_connection));
//    var_dump('进入函数');
    // 任务及参数数据 --执行逻辑
//    $task_data = array(
//        'function' => 'getOnlineUser',
//    );
    // 发送数据
    $task_connection->send(json_encode($task_data));


    // 执行异步链接
    $task_connection->connect();
    return $task_connection;
}

