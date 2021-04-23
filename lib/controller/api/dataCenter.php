<?php

/**
 * Created by PhpStorm.
 * 修改人: Cloud
 * 用途:获取一些公共数据
 * Date: 2017/7/5
 * Time: 16:20
 */
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'web' . DS . 'action.php');

class DataCenterAction extends Action {
    protected $table0 = 'un_room';
    protected $table1 = 'un_orders';
    protected $table2 = 'un_user';
    protected $table3 = 'un_user_group';
    /*
      * 修改人: Cloud
      * 获取房间所有限额
      * 用途:web页面获取限额数据，ajax
      * params id int 房间号
      * output mix 房间所有限额
    */
    public function getRoomLimite(){
        session_start();
        @$id = $_GET["id"];
        $output = [];
        if(!isset($id)) {
            $id = $_SESSION["SN_"]['room_id'];
        }
        $id = intval($id);
        $data = $this->db->getone("SELECT upper,lower FROM " . $this->table0 . " WHERE id='$id'");
        $output = json_decode($data["upper"],true);
        @$user_id = $_SESSION["SN_"]['uid'];
        $group_id =  $this->db->getone("SELECT group_id FROM un_user WHERE id='$user_id'");
        $group_id =$group_id["group_id"];
        $redis = initCacheRedis();
        $group_info = $redis->hGetAll('group:'.$group_id);
        deinitCacheRedis($redis);
        if($group_info['limit_state']==1){
            $output["user_group_lower"] = $group_info["lower"];
            $output["user_group_upper"] = $group_info["upper"];
        }else{
            $output["user_group_lower"] = 0;
            $output["user_group_upper"] = 0;
        }
        $output["user_group_name"] = $group_info["name"];
        $output["lower"] = $data["lower"];
        $this->api_out($output);
    }

    /*
      * 修改人: Cloud
      * 获取房间所有限额
      * 用途:web页面获取限额数据，ajax
      * params id int 房间号
      * output mix 房间所有限额
    */
    public function getAllBet(){
        session_start();
        @$user_id = $_GET["user_id"];
        @$room_id = $_GET["room_id"];
        $output = [];
        if(!isset($user_id)) {
            @$user_id = $_SESSION["SN_"]['uid'];
            @$room_id = $_SESSION["SN_"]['room_id'];
        }
        $user_id = intval($user_id);
        $add_time = time()-3600;
        $sql = "SELECT issue,way,money FROM " . $this->table1 . " WHERE user_id='$user_id' AND addtime>$add_time AND state='0' AND room_no = '$room_id'";
        $data = $this->db->getall($sql);
        $order_total = [];

        foreach ($data as $i) {
            $is_exist = false;
            foreach ($order_total as $key => $li) {
                if ($li["issue"] == $i["issue"] && $li["way"] == $i["way"]) {
                    $is_exist = true;
                    $order_total[$key]["money"] = bcadd($li["money"], $i["money"], 2);
                }
            }
            if (!$is_exist){
                $order_total[] = $i;
            }
        }
        $this->api_out($order_total);
    }

    /*
      * 修改人: Cloud
      * 获取房间逆向投注信息
      * 用途:web页面获取获取房间逆向投注信息，ajax
      * output mix 逆向投注信息
    */
    public function getReverse(){
        session_start();
        @$room = $this->redisfuns('get','allroom:'. $_SESSION["SN_"]['room_id'], 1);
        $reverse = json_decode($room['reverse'],1);
        $this->api_out($reverse);
    }

    private function redisfuns($funs,$key='',$type=0){
        static $cache_redis,$config;
        if (empty($config)){
            !defined('IN_SNYNI') && define("IN_SNYNI",1);
            $config = require("caches/config.php");
        }
        if (empty($cache_redis)){
            $cache_redis = new redis();
            $cache_redis->connect($config['redis_config']['host'], $config['redis_config']['port']);
            $cache_redis->auth($config['redis_config']['pass']);
        }
        switch ($funs) {
            case 'get':
                if ($type) {
                    $data = $cache_redis->hGetAll($key);
                    if (substr($key, 0, 7) == 'Config:') {
                        return $data['value'];
                    }
                    return $cache_redis->hGetAll($key);
                } else {
                    return $cache_redis->get($key);
                }
        }
    }
    /*
        * 修改人: CLoud
        * 用于数组转换json，ajax数据返回
    */
    private function api_out($data){
        /*
         *1xxxx    成功提示
         * 10000   完全成功
         * 10001   成功，业务提示
         *
         *2xxxx    页面抓取错误
         * 20001   常规错误
         *
         *9xxxx    服务端错误
         * 90001   参数错误
         */
        $mix = [];
        $message = null;

        //9xxxx验证
        if(!is_array($data))
        {
            $mix['data'] = null;
            $mix['status'] = [];
            $mix['status']['code'] = "90001";
            $mix['status']['msg'] = "service params type error";
        }
        else
        {
            $mix['data'] = $data;
            $mix['status'] = [];
            if(isset($data['msg'])&&trim($data['msg'])!="") {
                $mix['status']['code']="10001";
                $mix['status']['msg'] = $data['msg'];
            }
            else
            {
                $mix['status']['code']="10000";
                $mix['status']['msg'] = 'success';
            }
            unset($mix['data']['msg']);
        }

        die(urldecode(json_encode($mix, JSON_UNESCAPED_UNICODE)));
    }
}