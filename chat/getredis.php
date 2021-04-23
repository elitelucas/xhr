<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/3/18
 * Time: 16:27
 */

$roomInfo = redisfuns('get','allroom:1', 1);
print_r($roomInfo);

function redisfuns($funs,$key='',$type=0){
    static $cache_redis,$config;
    if (empty($config)){
        define("IN_SNYNI",1);
        $config = require_once "../caches/config.php";
    }
    if (empty($cache_redis)){
        $cache_redis = new redis();
        $cache_redis->connect($config['redis_config']['host'], $config['redis_config']['port']);
        $cache_redis->auth($config['redis_config']['pass']);
    }
    switch ($funs){
        case 'set':
            foreach ($key as $k=>$v){
                $cache_redis->set($k,$v);
            }
        case 'getall':
            $Ids = $cache_redis->lRange($key, 0, -1);
            $key_str = str_replace("Ids",':',$key);
            $info = array();
            foreach ($Ids as $v){
                $info[$v] = $cache_redis->hGetAll($key_str.$v);
            }
            return $info;
        case 'get':
            if ($type){
                $data = $cache_redis->hGetAll($key);
                if (substr($key,0,7)=='Config:'){
                    return $data['value'];
                }
                return $cache_redis->hGetAll($key);
            }else{
                return $cache_redis->get($key);
            }
        case 'del':
            return $cache_redis->del($key);
        case 'expire':
            return $cache_redis->expire($key, $type);
        case 'ttl':
            return $cache_redis->ttl($key);
        case 'close':
            $cache_redis->close();
            $cache_redis=false;
    }
}
