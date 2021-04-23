<?php

/**
 * Created by PhpStorm.
 * User: wangrui
 * Date: 2016/11/18
 * Time: 21:05
 * desc: 获取赔率
 */
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'model' . DS . 'common.php');

class OddsModel extends CommonModel {

    function getOdds($room_id){
        $redis  = initCacheRedis();
        $ways  = $redis->get('way'.$room_id);
        deinitCacheRedis($redis);
        return $ways;
    }
}
